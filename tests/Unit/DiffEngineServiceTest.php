<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ImportRun;
use App\Models\Tenant;
use App\Models\WatchedField;
use App\Services\DiffEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiffEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private DiffEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DiffEngineService();
    }

    private function makeTenant(string $jobKey = 'Job ID', array $watchedFields = ['Status']): Tenant
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 'test', 'job_key_column' => $jobKey]);
        foreach ($watchedFields as $field) {
            WatchedField::create(['tenant_id' => $tenant->id, 'column_name' => $field]);
        }
        return $tenant;
    }

    private function makeRun(Tenant $tenant): ImportRun
    {
        return ImportRun::create(['tenant_id' => $tenant->id, 'filename' => 'test.csv', 'status' => 'processing']);
    }

    public function test_all_jobs_flagged_new_on_first_import(): void
    {
        $tenant = $this->makeTenant();
        $run = $this->makeRun($tenant);

        $jobs = [
            ['Job ID' => '1', 'Status' => 'Active', 'Amount' => '100'],
            ['Job ID' => '2', 'Status' => 'Pending', 'Amount' => '200'],
        ];

        $counts = $this->service->diff($tenant, $run, $jobs);

        $this->assertSame(2, $counts['new']);
        $this->assertSame(0, $counts['changed']);
        $this->assertSame(0, $counts['unchanged']);
    }

    public function test_unchanged_watched_field_produces_unchanged_job(): void
    {
        $tenant = $this->makeTenant();
        $run1 = $this->makeRun($tenant);
        $jobs = [['Job ID' => '1', 'Status' => 'Active', 'Amount' => '100']];
        $this->service->diff($tenant, $run1, $jobs);

        $run2 = $this->makeRun($tenant);
        $counts = $this->service->diff($tenant, $run2, $jobs);

        $this->assertSame(0, $counts['new']);
        $this->assertSame(0, $counts['changed']);
        $this->assertSame(1, $counts['unchanged']);
    }

    public function test_changed_watched_field_flags_job_as_changed(): void
    {
        $tenant = $this->makeTenant();
        $run1 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run1, [['Job ID' => '1', 'Status' => 'Active', 'Amount' => '100']]);

        $run2 = $this->makeRun($tenant);
        $counts = $this->service->diff($tenant, $run2, [['Job ID' => '1', 'Status' => 'Installed', 'Amount' => '100']]);

        $this->assertSame(0, $counts['new']);
        $this->assertSame(1, $counts['changed']);
        $this->assertSame(0, $counts['unchanged']);
    }

    public function test_non_watched_field_change_does_not_flag_job(): void
    {
        $tenant = $this->makeTenant(watchedFields: ['Status']);
        $run1 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run1, [['Job ID' => '1', 'Status' => 'Active', 'Amount' => '100']]);

        $run2 = $this->makeRun($tenant);
        $counts = $this->service->diff($tenant, $run2, [['Job ID' => '1', 'Status' => 'Active', 'Amount' => '999']]);

        $this->assertSame(0, $counts['changed']);
        $this->assertSame(1, $counts['unchanged']);
    }

    public function test_job_absent_from_new_file_is_dropped_from_snapshot(): void
    {
        $tenant = $this->makeTenant();
        $run1 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run1, [
            ['Job ID' => '1', 'Status' => 'Active'],
            ['Job ID' => '2', 'Status' => 'Active'],
        ]);

        $run2 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run2, [['Job ID' => '1', 'Status' => 'Active']]);

        $this->assertDatabaseMissing('snapshot_jobs', ['tenant_id' => $tenant->id, 'job_key' => '2']);
    }
}
