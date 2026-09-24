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

    protected $fillable = ['warehouse_id', 'number', 'amr_status', 'pmr_status'];

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
        return $this->hasOne(AmrCalculation::class)->latestOfMany();
    }

    public function pmrCalculation(): HasOne
    {
        return $this->hasOne(PmrCalculation::class)->latestOfMany();
    }
}
