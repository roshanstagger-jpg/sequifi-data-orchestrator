<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnapshotJob extends Model
{
    protected $fillable = ['tenant_id', 'import_run_id', 'job_key', 'data', 'snapshot_hash'];

    protected $casts = [
        'data' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
