<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRunJob extends Model
{
    public $timestamps = false;

    protected $fillable = ['import_run_id', 'job_key', 'change_type', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }
}
