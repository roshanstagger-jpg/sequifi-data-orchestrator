<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportTemplateColumn extends Model
{
    protected $fillable = ['tenant_id', 'source_column', 'output_column', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
