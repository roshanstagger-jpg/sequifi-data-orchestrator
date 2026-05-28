<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('snapshot_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->string('job_key');
            $table->json('data');
            $table->string('snapshot_hash', 32);
            $table->timestamps();
            $table->unique(['tenant_id', 'job_key']);
            $table->index(['tenant_id', 'job_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snapshot_jobs');
    }
};
