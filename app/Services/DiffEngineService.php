<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ImportRun;
use App\Models\ImportRunJob;
use App\Models\SnapshotJob;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Diffs an incoming file import against the Sequifi snapshot.
 *
 * The snapshot is treated as READ-ONLY here — it is exclusively written by
 * SnapshotSyncService (API pulls).  This service classifies each incoming row
 * as new / changed / unchanged and stores export-ready data alongside each
 * new or changed record in import_run_jobs.data.
 */
class DiffEngineService
{
    public function diff(Tenant $tenant, ImportRun $run, array $jobs): array
    {
        // Load watched fields as [ column_name => change_mode ]
        $watchedFields = $tenant->watchedFields()
            ->get(['column_name', 'change_mode'])
            ->pluck('change_mode', 'column_name')
            ->toArray();

        $jobKeyColumn = $tenant->job_key_column;

        $fillOnlyFields = array_keys(
            array_filter($watchedFields, fn($mode) => $mode === 'fill_only')
        );

        // Load the snapshot.  We always need hashes; we only need the full data
        // when fill_only fields exist (to preserve old values in the export).
        if (!empty($fillOnlyFields)) {
            $snapshotRows         = SnapshotJob::where('tenant_id', $tenant->id)
                ->get(['job_key', 'snapshot_hash', 'data']);
            $existingHashes       = $snapshotRows->pluck('snapshot_hash', 'job_key')->toArray();
            $existingSnapshotData = $snapshotRows->pluck('data', 'job_key')->toArray();
        } else {
            $existingHashes       = SnapshotJob::where('tenant_id', $tenant->id)
                ->pluck('snapshot_hash', 'job_key')
                ->toArray();
            $existingSnapshotData = [];
        }

        $counts        = ['new' => 0, 'changed' => 0, 'unchanged' => 0];
        $runJobInserts = [];
        $seenKeys      = [];

        foreach ($jobs as $job) {
            $jobKey = (string) ($job[$jobKeyColumn] ?? '');
            if ($jobKey === '') {
                continue;
            }

            // Deduplicate: only the last occurrence of a job_key is processed.
            $alreadySeen      = isset($seenKeys[$jobKey]);
            $seenKeys[$jobKey] = true;
            if ($alreadySeen) {
                continue;
            }

            $hash = $this->computeHash($job, $watchedFields);

            if (!isset($existingHashes[$jobKey])) {
                $changeType = 'new';
                $counts['new']++;
            } elseif ($existingHashes[$jobKey] !== $hash) {
                $changeType = 'changed';
                $counts['changed']++;
            } else {
                $changeType = 'unchanged';
                $counts['unchanged']++;
            }

            // Build export-ready data for new/changed rows only.
            // For fill_only fields with a non-blank value in both the snapshot
            // and the incoming row, preserve the OLD snapshot value so the export
            // never overwrites an already-filled field in Sequifi.
            $exportData = null;
            if ($changeType !== 'unchanged') {
                $exportData = $job;

                if (!empty($fillOnlyFields) && isset($existingSnapshotData[$jobKey])) {
                    $oldData = $existingSnapshotData[$jobKey];
                    if (is_array($oldData)) {
                        foreach ($fillOnlyFields as $field) {
                            $oldVal = (string) ($oldData[$field] ?? '');
                            $newVal = (string) ($job[$field]  ?? '');
                            if ($oldVal !== '' && $newVal !== '') {
                                $exportData[$field] = $oldData[$field];
                            }
                        }
                    }
                }
            }

            $runJobInserts[] = [
                'import_run_id' => $run->id,
                'job_key'       => $jobKey,
                'change_type'   => $changeType,
                // json_encode manually: ImportRunJob::insert() bypasses model casts.
                'data'          => $exportData !== null ? json_encode($exportData) : null,
            ];
        }

        DB::transaction(function () use ($run, $runJobInserts, $counts) {
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
     * Compute a deterministic hash over watched fields only.
     * Must produce the same value as SnapshotSyncService::computeHash()
     * for the same job row and watched-field configuration.
     *
     * @param  array<string, mixed>   $job
     * @param  array<string, string>  $watchedFields  column_name => change_mode
     */
    private function computeHash(array $job, array $watchedFields): string
    {
        if (empty($watchedFields)) {
            return md5(json_encode($job));
        }

        $values = [];
        foreach ($watchedFields as $field => $mode) {
            $raw = (string) ($job[$field] ?? '');
            $values[$field] = ($mode === 'fill_only')
                ? ($raw === '' ? '' : '1')
                : $raw;
        }
        ksort($values);

        return md5(json_encode($values));
    }
}
