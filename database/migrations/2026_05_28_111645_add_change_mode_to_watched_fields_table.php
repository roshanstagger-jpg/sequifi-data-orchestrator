<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * change_mode controls when a field triggers a "changed" classification:
     *   any_change — any difference in value flags the job (default behaviour)
     *   fill_only  — only a blank→non-blank transition flags the job;
     *                a change between two non-blank values is ignored
     */
    public function up(): void
    {
        Schema::table('watched_fields', function (Blueprint $table) {
            $table->string('change_mode')->default('any_change')->after('column_name');
        });
    }

    public function down(): void
    {
        Schema::table('watched_fields', function (Blueprint $table) {
            $table->dropColumn('change_mode');
        });
    }
};
