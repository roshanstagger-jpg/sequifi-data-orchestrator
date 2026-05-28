<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'job_key_column', 'api_token'];

    protected $hidden = ['api_token'];

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
}
