<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpBlock extends Model
{
    protected $table = 'blocked_ips';

    protected $fillable = [
        'ip_address',
        'attempts',
        'reason',
        'blocked_at',
        'blocked_until',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'blocked_at' => 'datetime',
            'blocked_until' => 'datetime',
        ];
    }

    public function isCurrentlyBlocked(): bool
    {
        return $this->blocked_until !== null && $this->blocked_until->isFuture();
    }
}
