<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // The field name that the Sequifi API uses as the unique record ID.
            // Defaults to 'pid' which is the standard Sequifi Marketplace API key.
            // This is intentionally separate from job_key_column (the file column
            // name), because the file and the API almost always use different names
            // for the same underlying ID value.
            $table->string('api_job_key_field')->nullable()->default('pid')->after('job_key_column');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('api_job_key_field');
        });
    }
};
