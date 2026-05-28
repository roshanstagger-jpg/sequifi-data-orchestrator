<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ImportRun;
use App\Models\SnapshotJob;
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

    /**
     * @param  array<string>|array<string, string>  $watchedFields
     *   Either a flat list of column names (defaults to 'any_change'), or a map
     *   of column_name => change_mode, e.g. ['Status' => 'any_change', 'Date' => 'fill_only'].
     */
    private function makeTenant(string $jobKey = 'Job ID', array $watchedFields = ['Status']): Tenant
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 'test', 'job_key_column' => $jobKey]);

        // Support both flat arrays and associative arrays with explicit change_mode.
        foreach ($watchedFields as $key => $value) {
            if (is_int($key)) {
                // Flat array: $value is the column name, default change_mode
                WatchedField::create(['tenant_id' => $tenant->id, 'column_name' => $value, 'change_mode' => 'any_change']);
            } else {
                // Associative: $key is column name, $value is change_mode
                WatchedField::create(['tenant_id' => $tenant->id, 'column_name' => $key, 'change_mode' => $value]);
            }
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

    // --- fill_only change_mode tests ---

    public function test_fill_only_blank_to_value_flags_changed(): void
    {
        $tenant = $this->makeTenant(watchedFields: ['Milestone Date' => 'fill_only']);
        $run1 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run1, [['Job ID' => '1', 'Milestone Date' => '']]);

        $run2 = $this->makeRun($tenant);
        $counts = $this->service->diff($tenant, $run2, [['Job ID' => '1', 'Milestone Date' => '2024-06-01']]);

        $this->assertSame(1, $counts['changed'], 'blank→value should flag as changed');
    }

    public function test_fill_only_value_to_different_value_does_not_flag_changed(): void
    {
        $tenant = $this->makeTenant(watchedFields: ['Milestone Date' => 'fill_only']);
        $run1 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run1, [['Job ID' => '1', 'Milestone Date' => '2024-01-15']]);

        $run2 = $this->makeRun($tenant);
        $counts = $this->service->diff($tenant, $run2, [['Job ID' => '1', 'Milestone Date' => '2024-02-01']]);

        $this->assertSame(0, $counts['changed'], 'value→different value should not flag changed');
        $this->assertSame(1, $counts['unchanged']);
    }

    public function test_fill_only_blank_stays_blank_is_unchanged(): void
    {
        $tenant = $this->makeTenant(watchedFields: ['Milestone Date' => 'fill_only']);
        $run1 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run1, [['Job ID' => '1', 'Milestone Date' => '']]);

        $run2 = $this->makeRun($tenant);
        $counts = $this->service->diff($tenant, $run2, [['Job ID' => '1', 'Milestone Date' => '']]);

        $this->assertSame(0, $counts['changed']);
        $this->assertSame(1, $counts['unchanged']);
    }

    public function test_mixed_modes_only_any_change_field_triggers(): void
    {
        // Status = any_change, Milestone Date = fill_only
        $tenant = $this->makeTenant(watchedFields: ['Status' => 'any_change', 'Milestone Date' => 'fill_only']);
        $run1 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run1, [
            ['Job ID' => '1', 'Status' => 'Active', 'Milestone Date' => '2024-01-15'],
        ]);

        // Milestone Date changes (non-blank→non-blank), Status unchanged
        $run2 = $this->makeRun($tenant);
        $counts = $this->service->diff($tenant, $run2, [
            ['Job ID' => '1', 'Status' => 'Active', 'Milestone Date' => '2024-06-01'],
        ]);

        $this->assertSame(0, $counts['changed'], 'only milestone changed (fill_only, non-blank→non-blank) — should not trigger');
        $this->assertSame(1, $counts['unchanged']);
    }

    public function test_mixed_modes_any_change_field_change_triggers(): void
    {
        $tenant = $this->makeTenant(watchedFields: ['Status' => 'any_change', 'Milestone Date' => 'fill_only']);
        $run1 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run1, [
            ['Job ID' => '1', 'Status' => 'Active', 'Milestone Date' => '2024-01-15'],
        ]);

        // Status changes (any_change) — should trigger even though Milestone Date didn't
        $run2 = $this->makeRun($tenant);
        $counts = $this->service->diff($tenant, $run2, [
            ['Job ID' => '1', 'Status' => 'Installed', 'Milestone Date' => '2024-01-15'],
        ]);

        $this->assertSame(1, $counts['changed']);
    }

    public function test_fill_only_value_change_preserves_old_value_in_snapshot(): void
    {
        // Status (any_change) + Milestone Date (fill_only)
        $tenant = $this->makeTenant(watchedFields: ['Status' => 'any_change', 'Milestone Date' => 'fill_only']);
        $run1 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run1, [
            ['Job ID' => '1', 'Status' => 'Active', 'Milestone Date' => '2024-01-15'],
        ]);

        // Status changes (triggers export); Milestone Date also changes non-blank→non-blank (ignored for diffing).
        $run2 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run2, [
            ['Job ID' => '1', 'Status' => 'Installed', 'Milestone Date' => '2024-06-01'],
        ]);

        $snapshot = SnapshotJob::where('tenant_id', $tenant->id)->where('job_key', '1')->first();

        // fill_only field: old non-blank value must be frozen in the snapshot
        $this->assertSame('2024-01-15', $snapshot->data['Milestone Date'],
            'fill_only value-to-value change must not overwrite the stored value');

        // any_change field: new value should be stored normally
        $this->assertSame('Installed', $snapshot->data['Status']);
    }

    public function test_fill_only_blank_to_value_stores_new_value_in_snapshot(): void
    {
        $tenant = $this->makeTenant(watchedFields: ['Milestone Date' => 'fill_only']);
        $run1 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run1, [['Job ID' => '1', 'Milestone Date' => '']]);

        $run2 = $this->makeRun($tenant);
        $this->service->diff($tenant, $run2, [['Job ID' => '1', 'Milestone Date' => '2024-06-01']]);

        $snapshot = SnapshotJob::where('tenant_id', $tenant->id)->where('job_key', '1')->first();

        // blank→value is an intentional fill — the new date must be stored
        $this->assertSame('2024-06-01', $snapshot->data['Milestone Date']);
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
