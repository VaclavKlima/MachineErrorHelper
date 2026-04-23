<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoftwareVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'version',
        'released_at',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
