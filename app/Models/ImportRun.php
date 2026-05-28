<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRun extends Model
{
    protected $fillable = [
        'tenant_id', 'filename', 'status',
        'total_jobs', 'new_jobs', 'changed_jobs', 'unchanged_jobs', 'error_message',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function runJobs(): HasMany
    {
        return $this->hasMany(ImportRunJob::class);
    }
}
