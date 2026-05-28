<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_run_jobs', function (Blueprint $table) {
            // Export-ready row data for new/changed records.
            // Null for unchanged rows (they are never exported).
            // Populated by DiffEngineService with fill_only preservation applied.
            $table->jsonb('data')->nullable()->after('change_type');
        });
    }

    public function down(): void
    {
        Schema::table('import_run_jobs', function (Blueprint $table) {
            $table->dropColumn('data');
        });
    }
};
