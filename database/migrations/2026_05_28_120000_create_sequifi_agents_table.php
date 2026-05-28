<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequifi_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Identity from Sequifi API
            $table->string('sequifi_id');          // Sequifi's user/employee ID
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable(); // first + last, denormalized for easy display
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Employment metadata
            $table->string('worker_type')->nullable();   // e.g. W2, 1099
            $table->string('position')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->nullable();        // active, terminated, etc.

            // Full raw payload for forward-compatibility
            $table->jsonb('raw_data')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // One record per user per tenant; re-sync is an upsert
            $table->unique(['tenant_id', 'sequifi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequifi_agents');
    }
};
