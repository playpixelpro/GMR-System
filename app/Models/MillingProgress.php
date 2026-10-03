<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MillingProgress extends Model
{
    protected $fillable = [
        'milling_id',
        'pile_id',
        'progress_date',
        'batch_number',
        'palay_input_kg',
        'milled_rice_kg',
        'recovery_percentage',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'progress_date' => 'date',
            'palay_input_kg' => 'decimal:3',
            'milled_rice_kg' => 'decimal:3',
            'recovery_percentage' => 'decimal:2',
        ];
    }

    public function milling(): BelongsTo
    {
        return $this->belongsTo(Milling::class);
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(Pile::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
