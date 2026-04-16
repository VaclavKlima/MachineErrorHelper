<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class RepairHint extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'machine_id',
        'error_code_id',
        'error_code_definition_id',
        'diagnostic_entry_id',
        'title',
        'body',
        'steps',
        'safety_warning',
        'tools_required',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'tools_required' => 'array',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function errorCode(): BelongsTo
    {
        return $this->belongsTo(ErrorCode::class);
    }

    public function errorCodeDefinition(): BelongsTo
    {
        return $this->belongsTo(ErrorCodeDefinition::class);
    }

    public function diagnosticEntry(): BelongsTo
    {
        return $this->belongsTo(DiagnosticEntry::class);
    }
}
