<?php

namespace App\Models;

use Database\Factories\MillerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Miller extends Model
{
    /** @use HasFactory<MillerFactory> */
    use HasFactory;

    /**
     * Human-readable labels for the stored category values.
     *
     * @var array<string, string>
     */
    public const CATEGORY_LABELS = [
        'nfa_owned' => 'NFA Owned',
        'private' => 'Private',
    ];

    protected $fillable = [
        'name',
        'category',
        'capacity_12h_bags',
    ];

    protected function casts(): array
    {
        return [
            'capacity_12h_bags' => 'decimal:3',
        ];
    }

    public function millings(): HasMany
    {
        return $this->hasMany(Milling::class);
    }
}
