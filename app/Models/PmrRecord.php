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
        'volume_kg',
        'trial_number',
        'test_milling_date',
        'palay_input_kg',
        'rice_recovery_kg',
        'milling_recovery',
        'is_outlier',
        'conduct_number',
        'status',
        'included_in_computation',
        'is_locked',
        'created_by',
        'confirmed_by',
        'actioned_by',
        'confirmed_at',
        'actioned_at',
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
            'volume_kg' => 'decimal:3',
            'trial_number' => 'integer',
            'test_milling_date' => 'date:Y-m-d',
            'palay_input_kg' => 'decimal:2',
            'rice_recovery_kg' => 'decimal:2',
            'milling_recovery' => 'decimal:2',
            'is_outlier' => 'boolean',
            'conduct_number' => 'integer',
            'included_in_computation' => 'boolean',
            'is_locked' => 'boolean',
            'confirmed_at' => 'datetime',
            'actioned_at' => 'datetime',
        ];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(Pile::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    public function scopeEligible($query)
    {
        return $query
            ->where('status', 'RECOMMENDED')
            ->where('included_in_computation', true);
    }

    public function scopeForConduct($query, int $conductNumber)
    {
        return $query->where('conduct_number', $conductNumber);
    }

    public function getWarehouseNameAttribute(?string $value): ?string
    {
        return $this->pile?->warehouse?->name ?? $value;
    }

    public function getPileNumberAttribute(?string $value): ?string
    {
        return $this->pile?->pile_number ?? ($this->pile?->number ?? $value);
    }

    public function getVarietyAttribute(?string $value): ?string
    {
        return $this->pile?->variety ?? $value;
    }

    public function getPurityAttribute($value)
    {
        return $this->pile?->purity ?? $value;
    }

    public function getMcAttribute($value)
    {
        return $this->pile?->mc ?? $value;
    }

    public function getQualityAttribute(?string $value): ?string
    {
        return $this->pile?->quality ?? $value;
    }

    public function getAgedMonthsAttribute($value)
    {
        return $this->pile?->aged_months ?? $value;
    }

    public function getVolumeBagsAttribute($value)
    {
        return $this->pile?->volume_kg ?? $value;
    }

    public function getRecoveryRatePercentageAttribute(): float
    {
        if ($this->milling_recovery !== null) {
            return (float) $this->milling_recovery;
        }

        if (
            $this->palay_input_kg === null ||
            (float) $this->palay_input_kg === 0.0
        ) {
            return 0.0;
        }

        return ((float) $this->rice_recovery_kg /
            (float) $this->palay_input_kg) *
            100;
    }
}
