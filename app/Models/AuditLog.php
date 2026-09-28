<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'branch_id',
        'branch_name',
        'module',
        'action',
        'description',
        'ip_address',
        'auditable_type',
        'auditable_id',
        'pile_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(Pile::class);
    }

    public static function record(
        string $action,
        Model $auditable,
        array $metadata = [],
        ?string $module = null,
        ?string $description = null,
    ): self {
        $user = auth()->user();

        return static::create([
            'user_id' => $user?->getKey(),
            'role' => $user?->role,
            'branch_id' => $user?->branch_id,
            'branch_name' => $user?->branch?->name,
            'module' => $module ?? self::guessModule($auditable),
            'action' => $action,
            'description' => $description,
            'ip_address' => Request::ip(),
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'pile_id' => $auditable instanceof Pile
                    ? $auditable->getKey()
                    : $auditable->getAttribute('pile_id'),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Derive a module name from the auditable model class when none is given.
     */
    private static function guessModule(Model $auditable): ?string
    {
        return match (true) {
            $auditable instanceof AmrRecord => 'amr',
            $auditable instanceof PmrRecord => 'pmr',
            $auditable instanceof Pile => 'pile',
            $auditable instanceof User => 'user',
            $auditable instanceof GmrReportConfiguration,
            $auditable instanceof GmrReportSignatory => 'gmr',
            default => null,
        };
    }

    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        return $query;
    }
}
