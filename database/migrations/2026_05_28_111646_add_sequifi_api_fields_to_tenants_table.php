<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('sequifi_api_url')->nullable()->default('https://api.sequifi.com')->after('api_token');
            $table->text('sequifi_bearer_token')->nullable()->after('sequifi_api_url');
            $table->integer('api_lookback_days')->default(90)->after('sequifi_bearer_token');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['sequifi_api_url', 'sequifi_bearer_token', 'api_lookback_days']);
        });
    }
};
