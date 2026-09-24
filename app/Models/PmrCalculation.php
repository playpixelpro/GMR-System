<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmrCalculation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pile_id',
        'group_key',
        'trial_inputs',
        'trial_recoveries',
        'median',
        'lower_limit',
        'upper_limit',
        'outlier_results',
        'valid_trial_count',
        'outlier_count',
        'standard_deviation',
        'coefficient_of_variation',
        'is_outlier_valid',
        'is_cv_valid',
        'is_valid',
        'status',
        'status_message',
        'pmr_rate',
        'snapshot',
        'calculated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_inputs' => 'array',
            'trial_recoveries' => 'array',
            'outlier_results' => 'array',
            'snapshot' => 'array',
            'valid_trial_count' => 'integer',
            'outlier_count' => 'integer',
            'is_outlier_valid' => 'boolean',
            'is_cv_valid' => 'boolean',
            'is_valid' => 'boolean',
            'median' => 'decimal:4',
            'lower_limit' => 'decimal:4',
            'upper_limit' => 'decimal:4',
            'standard_deviation' => 'decimal:4',
            'coefficient_of_variation' => 'decimal:4',
            'pmr_rate' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(Pile::class);
    }
}
