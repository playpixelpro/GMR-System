<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GmrApprovalPile extends Model
{
    protected $fillable = [
        'gmr_approval_id',
        'pile_id',
        'amr',
        'pmr',
        'emr_display',
        'gmr',
        'co_approved_gmr',
        'volume_kg',
        'volume_bags',
        'quality',
        'variety',
    ];

    protected function casts(): array
    {
        return [
            'amr' => 'decimal:2',
            'pmr' => 'decimal:2',
            'gmr' => 'decimal:2',
            'co_approved_gmr' => 'decimal:2',
            'volume_kg' => 'decimal:3',
            'volume_bags' => 'decimal:3',
        ];
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(GmrApproval::class, 'gmr_approval_id');
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(Pile::class);
    }

    /**
     * The Central-Office approved GMR takes precedence over the
     * system-recommended GMR as the official Final GMR.
     */
    public function finalGmr(): ?float
    {
        if ($this->co_approved_gmr !== null) {
            return (float) $this->co_approved_gmr;
        }

        return $this->gmr !== null ? (float) $this->gmr : null;
    }

    /**
     * Indicate which value the Final GMR is drawn from.
     */
    public function finalGmrSource(): string
    {
        return $this->co_approved_gmr !== null ? 'co_approved' : 'recommended';
    }
}
