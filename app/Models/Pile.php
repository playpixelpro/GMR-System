<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pile extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'pile_number',
        'number',
        'variety',
        'purity',
        'aged_months',
        'mc',
        'quality',
        'volume_kg',
        'amr_status',
        'pmr_status',
    ];

    protected function casts(): array
    {
        return [
            'purity' => 'decimal:2',
            'mc' => 'decimal:2',
            'aged_months' => 'integer',
            'volume_kg' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Pile $pile) {
            if ($pile->warehouse_id && ! $pile->branch_id) {
                $pile->branch_id = $pile->warehouse?->branch_id ?? Warehouse::find($pile->warehouse_id)?->branch_id;
            }

            if (! empty($pile->pile_number) && empty($pile->number)) {
                $pile->number = $pile->pile_number;
            } elseif (! empty($pile->number) && empty($pile->pile_number)) {
                $pile->pile_number = $pile->number;
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function amrRecords(): HasMany
    {
        return $this->hasMany(AmrRecord::class);
    }

    public function pmrRecords(): HasMany
    {
        return $this->hasMany(PmrRecord::class);
    }

    public function amrCalculation(): HasOne
    {
        return $this->hasOne(AmrCalculation::class);
    }

    public function pmrCalculation(): HasOne
    {
        return $this->hasOne(PmrCalculation::class);
    }

    public function getVolumeAttribute()
    {
        return $this->volume_kg;
    }

    public function setVolumeAttribute($value): void
    {
        $this->attributes['volume_kg'] = $value;
    }

    public function getAgedAttribute()
    {
        return $this->aged_months;
    }

    public function setAgedAttribute($value): void
    {
        $this->attributes['aged_months'] = $value;
    }
}
