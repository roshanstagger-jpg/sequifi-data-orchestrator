<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ImportRun;
use App\Models\ImportRunJob;
use App\Models\SnapshotJob;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class DiffEngineService
{
    public function diff(Tenant $tenant, ImportRun $run, array $jobs): array
    {
        // Load watched fields as [ column_name => change_mode ]
        // change_mode: 'any_change' | 'fill_only'
        $watchedFields = $tenant->watchedFields()
            ->get(['column_name', 'change_mode'])
            ->pluck('change_mode', 'column_name')
            ->toArray();

        $jobKeyColumn = $tenant->job_key_column;

        // Fields whose value-to-value changes are intentionally ignored.
        // For these we also need to preserve the old snapshot value so that
        // exports never silently overwrite them with a changed (but ignored) value.
        $fillOnlyFields = array_keys(array_filter($watchedFields, fn($mode) => $mode === 'fill_only'));

        // Load existing snapshot — hash for change detection, data only when
        // fill_only fields exist (so we can preserve old values in the upsert).
        if (!empty($fillOnlyFields)) {
            $snapshotRows = SnapshotJob::where('tenant_id', $tenant->id)
                ->get(['job_key', 'snapshot_hash', 'data']);
            $existingSnapshot     = $snapshotRows->pluck('snapshot_hash', 'job_key')->toArray();
            $existingSnapshotData = $snapshotRows->pluck('data', 'job_key')->toArray();
        } else {
            $existingSnapshot     = SnapshotJob::where('tenant_id', $tenant->id)
                ->pluck('snapshot_hash', 'job_key')
                ->toArray();
            $existingSnapshotData = [];
        }

        $counts = ['new' => 0, 'changed' => 0, 'unchanged' => 0];
        // Keyed by job_key so that duplicate job_keys in the same import
        // (e.g. paginated API returning the same record twice) are deduplicated.
        // PostgreSQL's ON CONFLICT DO UPDATE rejects duplicate keys in a single
        // INSERT batch with "command cannot affect row a second time".
        $snapshotUpserts = [];
        $runJobInserts   = [];
        $seenKeys        = [];
        $now = now();

        foreach ($jobs as $job) {
            $jobKey = (string) ($job[$jobKeyColumn] ?? '');
            if ($jobKey === '') {
                continue;
            }

            // Last occurrence wins for snapshot; skip duplicate run-job entries.
            $alreadySeen = isset($seenKeys[$jobKey]);
            $seenKeys[$jobKey] = true;

            $hash = $this->computeHash($job, $watchedFields);

            if (!$alreadySeen) {
                if (!isset($existingSnapshot[$jobKey])) {
                    $changeType = 'new';
                    $counts['new']++;
                } elseif ($existingSnapshot[$jobKey] !== $hash) {
                    $changeType = 'changed';
                    $counts['changed']++;
                } else {
                    $changeType = 'unchanged';
                    $counts['unchanged']++;
                }

                $runJobInserts[] = [
                    'import_run_id' => $run->id,
                    'job_key'       => $jobKey,
                    'change_type'   => $changeType,
                ];
            }

            // Build the data to persist in the snapshot.
            // For fill_only fields: if the old snapshot had a non-blank value and the
            // incoming row also has a non-blank value, keep the OLD value.  This means
            // a value-to-value change on a fill_only field is both ignored for diffing
            // AND invisible in exports — the downstream system always sees the original
            // value, not the silently-changed one.
            $snapshotData = $job;
            if (!empty($fillOnlyFields) && isset($existingSnapshotData[$jobKey])) {
                $oldData = $existingSnapshotData[$jobKey]; // already decoded (model casts to array)
                if (is_array($oldData)) {
                    foreach ($fillOnlyFields as $field) {
                        $oldVal = (string) ($oldData[$field] ?? '');
                        $newVal = (string) ($job[$field]  ?? '');
                        if ($oldVal !== '' && $newVal !== '') {
                            // Non-blank → non-blank: silently ignored change — freeze the old value.
                            $snapshotData[$field] = $oldData[$field];
                        }
                        // blank → non-blank: intentional fill — store the new value (default).
                        // non-blank → blank:  allow the clear — store the new blank (default).
                    }
                }
            }

            // Pass $snapshotData as a PHP array — the model's 'array' cast will
            // json_encode() it exactly once when building the INSERT statement.
            // (Passing json_encode() here would cause the cast to double-encode it.)
            $snapshotUpserts[$jobKey] = [
                'tenant_id'       => $tenant->id,
                'import_run_id'   => $run->id,
                'job_key'         => $jobKey,
                'data'            => $snapshotData,
                'snapshot_hash'   => $hash,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        // Re-index to a plain array for chunking (keys were job_key strings).
        $snapshotUpserts = array_values($snapshotUpserts);

        $incomingKeys = array_keys($seenKeys);

        DB::transaction(function () use ($tenant, $run, $snapshotUpserts, $runJobInserts, $incomingKeys, $counts) {
            foreach (array_chunk($snapshotUpserts, 200) as $chunk) {
                SnapshotJob::upsert(
                    $chunk,
                    ['tenant_id', 'job_key'],
                    ['data', 'snapshot_hash', 'import_run_id', 'updated_at']
                );
            }

            if (!empty($incomingKeys)) {
                SnapshotJob::where('tenant_id', $tenant->id)
                    ->whereNotIn('job_key', $incomingKeys)
                    ->delete();
            }

            foreach (array_chunk($runJobInserts, 200) as $chunk) {
                ImportRunJob::insert($chunk);
            }

            $run->update([
                'total_jobs'     => $counts['new'] + $counts['changed'] + $counts['unchanged'],
                'new_jobs'       => $counts['new'],
                'changed_jobs'   => $counts['changed'],
                'unchanged_jobs' => $counts['unchanged'],
                'status'         => 'completed',
            ]);
        });

        return $counts;
    }

    /**
     * Compute a deterministic hash over the watched fields of a job row.
     *
     * @param  array<string, mixed>   $job           Raw row from the import file
     * @param  array<string, string>  $watchedFields Map of column_name => change_mode
     */
    private function computeHash(array $job, array $watchedFields): string
    {
        if (empty($watchedFields)) {
            return md5(json_encode($job));
        }

        $values = [];
        foreach ($watchedFields as $field => $mode) {
            $raw = (string) ($job[$field] ?? '');

            if ($mode === 'fill_only') {
                // Normalise to a binary sentinel: blank stays blank, any non-blank
                // value becomes '1'.  This makes value-to-value changes invisible to
                // the hash while still detecting blank→value transitions.
                $values[$field] = $raw === '' ? '' : '1';
            } else {
                $values[$field] = $raw;
            }
        }
        ksort($values);

        return md5(json_encode($values));
    }
}
