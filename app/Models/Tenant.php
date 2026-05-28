<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'job_key_column',
        'api_token',
        'sequifi_api_url',
        'sequifi_bearer_token',
        'api_lookback_days',
    ];

    protected $hidden = ['api_token', 'sequifi_bearer_token'];

    protected $casts = [
        'sequifi_bearer_token' => 'encrypted',
    ];

    public function importRuns(): HasMany
    {
        return $this->hasMany(ImportRun::class);
    }

    public function watchedFields(): HasMany
    {
        return $this->hasMany(WatchedField::class);
    }

    public function exportTemplateColumns(): HasMany
    {
        return $this->hasMany(ExportTemplateColumn::class)->orderBy('sort_order');
    }

    public function snapshotJobs(): HasMany
    {
        return $this->hasMany(SnapshotJob::class);
    }

    public function isConfigured(): bool
    {
        return $this->job_key_column !== null
            && $this->watchedFields()->exists()
            && $this->exportTemplateColumns()->exists();
    }

    public function hasApiConfig(): bool
    {
        return !empty($this->sequifi_bearer_token);
    }
}
