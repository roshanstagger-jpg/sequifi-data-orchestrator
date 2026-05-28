<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ImportRun;
use App\Models\SnapshotJob;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Syncs a full set of Sequifi API records into the snapshot table.
 *
 * The snapshot is the single source of truth about what Sequifi currently holds.
 * It is ONLY written here — never by file imports. File imports read the snapshot
 * to compute their diff but never modify it.
 */
class SnapshotSyncService
{
    /**
     * Replace the tenant's snapshot with the given jobs, mark the run complete,
     * and return the total number of records synced.
     *
     * @param  array<int, array<string, string>>  $jobs  Normalised rows from the API
     */
    public function sync(Tenant $tenant, ImportRun $run, array $jobs): int
    {
        $watchedFields = $tenant->watchedFields()
            ->get(['column_name', 'change_mode'])
            ->pluck('change_mode', 'column_name')
            ->toArray();

        // The API uses its own field name for the unique record ID (default: 'pid').
        // This is intentionally separate from job_key_column, which is the name
        // that the *file* uses for the same value.
        $apiKeyField = $tenant->api_job_key_field ?: 'pid';
        $now         = now();

        // Deduplicate: last occurrence wins when the API returns the same key twice.
        $byKey = [];
        foreach ($jobs as $job) {
            $jobKey = (string) ($job[$apiKeyField] ?? '');
            if ($jobKey !== '') {
                $byKey[$jobKey] = $job;
            }
        }

        $upsertRows = [];
        foreach ($byKey as $jobKey => $job) {
            $upsertRows[] = [
                'tenant_id'     => $tenant->id,
                'import_run_id' => $run->id,
                'job_key'       => $jobKey,
                'data'          => json_encode($job),
                'snapshot_hash' => $this->computeHash($job, $watchedFields),
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        $total = count($upsertRows);

        DB::transaction(function () use ($tenant, $run, $upsertRows, $total) {
            foreach (array_chunk($upsertRows, 200) as $chunk) {
                SnapshotJob::upsert(
                    $chunk,
                    ['tenant_id', 'job_key'],
                    ['data', 'snapshot_hash', 'import_run_id', 'updated_at']
                );
            }

            // Remove jobs that no longer exist in Sequifi.
            if (!empty($upsertRows)) {
                $incomingKeys = array_column($upsertRows, 'job_key');
                SnapshotJob::where('tenant_id', $tenant->id)
                    ->whereNotIn('job_key', $incomingKeys)
                    ->delete();
            }

            $run->update([
                'total_jobs'     => $total,
                'new_jobs'       => 0,
                'changed_jobs'   => 0,
                'unchanged_jobs' => 0,
                'status'         => 'completed',
            ]);
        });

        return $total;
    }

    /**
     * Compute a deterministic hash over watched fields only (respecting fill_only
     * sentinels) so the snapshot hash is directly comparable to hashes produced
     * by DiffEngineService for incoming file rows.
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
                ? ($raw === '' ? '' : '1')   // binary sentinel — mirrors DiffEngineService
                : $raw;
        }
        ksort($values);

        return md5(json_encode($values));
    }
}
