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
        $watchedFields = $tenant->watchedFields()->pluck('column_name')->toArray();
        $jobKeyColumn = $tenant->job_key_column;

        $existingSnapshot = SnapshotJob::where('tenant_id', $tenant->id)
            ->pluck('snapshot_hash', 'job_key')
            ->toArray();

        $counts = ['new' => 0, 'changed' => 0, 'unchanged' => 0];
        $snapshotUpserts = [];
        $runJobInserts = [];
        $incomingKeys = [];
        $now = now();

        foreach ($jobs as $job) {
            $jobKey = (string) ($job[$jobKeyColumn] ?? '');
            if ($jobKey === '') {
                continue;
            }

            $incomingKeys[] = $jobKey;
            $hash = $this->computeHash($job, $watchedFields);

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

            $snapshotUpserts[] = [
                'tenant_id' => $tenant->id,
                'import_run_id' => $run->id,
                'job_key' => $jobKey,
                'data' => json_encode($job),
                'snapshot_hash' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $runJobInserts[] = [
                'import_run_id' => $run->id,
                'job_key' => $jobKey,
                'change_type' => $changeType,
            ];
        }

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
                'total_jobs' => $counts['new'] + $counts['changed'] + $counts['unchanged'],
                'new_jobs' => $counts['new'],
                'changed_jobs' => $counts['changed'],
                'unchanged_jobs' => $counts['unchanged'],
                'status' => 'completed',
            ]);
        });

        return $counts;
    }

    private function computeHash(array $job, array $watchedFields): string
    {
        if (empty($watchedFields)) {
            return md5(json_encode($job));
        }

        $values = [];
        foreach ($watchedFields as $field) {
            $values[$field] = (string) ($job[$field] ?? '');
        }
        ksort($values);

        return md5(json_encode($values));
    }
}
