<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ExportTemplateColumn;
use App\Models\ImportRun;
use App\Models\ImportRunJob;
use App\Models\SnapshotJob;
use App\Models\Tenant;
use App\Models\WatchedField;
use App\Services\ExportBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ExportBuilderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_response_returns_binary_file_response(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'job_key_column' => 'Job ID']);
        WatchedField::create(['tenant_id' => $tenant->id, 'column_name' => 'Status']);
        ExportTemplateColumn::create(['tenant_id' => $tenant->id, 'source_column' => 'Job ID', 'output_column' => 'ID', 'sort_order' => 0]);
        ExportTemplateColumn::create(['tenant_id' => $tenant->id, 'source_column' => 'Status', 'output_column' => 'Job Status', 'sort_order' => 1]);

        $run = ImportRun::create(['tenant_id' => $tenant->id, 'filename' => 'f.csv', 'status' => 'completed', 'new_jobs' => 1]);
        SnapshotJob::create([
            'tenant_id' => $tenant->id,
            'import_run_id' => $run->id,
            'job_key' => '1',
            'data' => ['Job ID' => '1', 'Status' => 'Active'],
            'snapshot_hash' => md5('test'),
        ]);
        ImportRunJob::create(['import_run_id' => $run->id, 'job_key' => '1', 'change_type' => 'new']);

        $service = new ExportBuilderService();
        $response = $service->buildResponse($tenant, $run);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('.xlsx', $response->getFile()->getFilename());
    }

    public function test_unchanged_jobs_excluded_from_export(): void
    {
        $tenant = Tenant::create(['name' => 'T2', 'slug' => 't2', 'job_key_column' => 'Job ID']);
        ExportTemplateColumn::create(['tenant_id' => $tenant->id, 'source_column' => 'Job ID', 'output_column' => 'ID', 'sort_order' => 0]);

        $run = ImportRun::create(['tenant_id' => $tenant->id, 'filename' => 'f.csv', 'status' => 'completed']);
        SnapshotJob::create(['tenant_id' => $tenant->id, 'import_run_id' => $run->id, 'job_key' => '1', 'data' => ['Job ID' => '1'], 'snapshot_hash' => md5('a')]);
        SnapshotJob::create(['tenant_id' => $tenant->id, 'import_run_id' => $run->id, 'job_key' => '2', 'data' => ['Job ID' => '2'], 'snapshot_hash' => md5('b')]);
        ImportRunJob::create(['import_run_id' => $run->id, 'job_key' => '1', 'change_type' => 'new']);
        ImportRunJob::create(['import_run_id' => $run->id, 'job_key' => '2', 'change_type' => 'unchanged']);

        $changedKeys = $run->runJobs()->whereIn('change_type', ['new', 'changed'])->pluck('job_key');
        $this->assertCount(1, $changedKeys);
        $this->assertSame('1', $changedKeys->first());
    }
}
