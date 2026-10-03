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

    public const TEST_MILLING_REQUIRED_VOLUME_KG = 50000;

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
        'test_milling_volume_kg',
        'amr_status',
        'pmr_status',
        'gmr_status',
        'gmr_approval_pile_id',
        'gmr_locked_at',
    ];

    protected function casts(): array
    {
        return [
            'purity' => 'decimal:2',
            'mc' => 'decimal:2',
            'aged_months' => 'float',
            'volume_kg' => 'decimal:3',
            'test_milling_volume_kg' => 'decimal:3',
            'gmr_locked_at' => 'datetime',
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

    public function gmrApprovalPile(): BelongsTo
    {
        return $this->belongsTo(GmrApprovalPile::class, 'gmr_approval_pile_id');
    }

    public function millings(): HasMany
    {
        return $this->hasMany(Milling::class);
    }

    /**
     * A pile is permanently locked once its GMR has been approved by the
     * Central Office. While locked, no AMR/PMR/GMR changes are permitted.
     */
    public function isGmrLocked(): bool
    {
        return $this->gmr_status === 'approved';
    }

    public function isGmrApproved(): bool
    {
        return $this->gmr_status === 'approved';
    }

    public function isGmrSubmitted(): bool
    {
        return $this->gmr_status === 'submitted';
    }

    /**
     * Only piles with an approved GMR may be assigned to a rice milling.
     */
    public function canBeMilled(): bool
    {
        return $this->isGmrApproved();
    }

    /**
     * The official Final GMR for this pile.
     *
     * When a Central-Office approved GMR exists on the linked approval-pile
     * record, it takes precedence over the system-recommended GMR. Returns
     * null when the pile has no approved-GMR record.
     */
    public function finalGmr(): ?float
    {
        if ($this->gmrApprovalPile) {
            return $this->gmrApprovalPile->finalGmr();
        }

        return null;
    }

    /**
     * Whether the Final GMR is sourced from the Central-Office approved value
     * rather than the system recommendation.
     */
    public function finalGmrIsCoApproved(): bool
    {
        return $this->gmrApprovalPile?->co_approved_gmr !== null;
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
