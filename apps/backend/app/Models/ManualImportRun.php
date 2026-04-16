<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualImportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'manual_id',
        'status',
        'started_at',
        'finished_at',
        'error_message',
        'extractor_versions',
        'stats',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'extractor_versions' => 'array',
            'stats' => 'array',
        ];
    }

    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class);
    }
}
