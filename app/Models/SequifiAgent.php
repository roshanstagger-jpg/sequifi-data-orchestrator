<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequifiAgent extends Model
{
    protected $fillable = [
        'tenant_id',
        'sequifi_id',
        'first_name',
        'last_name',
        'full_name',
        'email',
        'phone',
        'worker_type',
        'position',
        'location',
        'status',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'raw_data'  => 'array',
        'synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Display name with fallback to email, then sequifi_id.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: $this->email ?: "Agent #{$this->sequifi_id}";
    }
}
