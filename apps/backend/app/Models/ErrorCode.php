<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErrorCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'code',
        'normalized_code',
        'family',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function definitions(): HasMany
    {
        return $this->hasMany(ErrorCodeDefinition::class);
    }

}
