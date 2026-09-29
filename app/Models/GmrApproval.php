<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GmrApproval extends Model
{
    protected $fillable = [
        'branch_id',
        'reference_number',
        'co_approval_memo_no',
        'status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'remarks',
        'rejection_reason',
        'report_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'report_snapshot' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function piles(): HasMany
    {
        return $this->hasMany(GmrApprovalPile::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }
}
