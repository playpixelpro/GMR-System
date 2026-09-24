<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmrRecord extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pile_id',
        'warehouse_name',
        'pile_number',
        'variety',
        'rice_millers',
        'purity',
        'mc',
        'quality',
        'aged_months',
        'volume_bags',
        'trial_number',
        'test_milling_date',
        'palay_input_kg',
        'rice_recovery_kg',
        'milling_recovery',
        'is_outlier',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purity' => 'decimal:2',
            'mc' => 'decimal:2',
            'aged_months' => 'integer',
            'volume_bags' => 'decimal:3',
            'trial_number' => 'integer',
            'test_milling_date' => 'date:Y-m-d',
            'palay_input_kg' => 'decimal:2',
            'rice_recovery_kg' => 'decimal:2',
            'milling_recovery' => 'decimal:2',
            'is_outlier' => 'boolean',
        ];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(Pile::class);
    }

    public function getRecoveryRatePercentageAttribute(): float
    {
        if ((float) $this->palay_input_kg === 0.0) {
            return 0.0;
        }

        return ((float) $this->rice_recovery_kg /
            (float) $this->palay_input_kg) *
            100;
    }
}
