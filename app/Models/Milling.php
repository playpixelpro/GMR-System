<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Milling extends Model
{
    protected $fillable = [
        'branch_id',
        'pile_id',
        'miller',
        'reference_number',
        'status',
        'target_volume_kg',
        'target_volume_bags',
        'final_gmr',
        'final_gmr_source',
        'assigned_by',
        'assigned_at',
        'started_at',
        'completed_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'target_volume_kg' => 'decimal:3',
            'target_volume_bags' => 'decimal:3',
            'final_gmr' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(Pile::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(MillingProgress::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['assigned', 'ongoing'], true);
    }

    /**
     * Cumulative milled rice across all progress entries.
     */
    public function cumulativeMilledKg(): float
    {
        return (float) $this->progress()->sum('milled_rice_kg');
    }

    /**
     * Cumulative palay input across all progress entries.
     */
    public function cumulativePalayKg(): float
    {
        return (float) $this->progress()->sum('palay_input_kg');
    }

    public function progressPercentage(): ?float
    {
        $target = (float) $this->target_volume_kg;

        if ($target <= 0) {
            return null;
        }

        return round($this->cumulativeMilledKg() / $target * 100, 2);
    }
}
