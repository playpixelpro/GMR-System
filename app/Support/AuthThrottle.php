<?php

namespace App\Support;

use App\Models\IpBlock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AuthThrottle
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_HOURS = 24;

    public const REASON_LOGIN = 'login';

    public const REASON_PASSWORD_RESET = 'password_reset';

    public function isBlocked(string $ip): bool
    {
        $block = IpBlock::where('ip_address', $ip)->first();

        return $block !== null && $block->isCurrentlyBlocked();
    }

    public function blockedUntil(string $ip): ?Carbon
    {
        $block = IpBlock::where('ip_address', $ip)->first();

        return $block !== null && $block->isCurrentlyBlocked()
            ? $block->blocked_until
            : null;
    }

    public function recordFailure(string $ip, string $reason): void
    {
        $block = IpBlock::firstOrCreate(['ip_address' => $ip]);

        if ($block->blocked_until !== null && $block->blocked_until->isPast()) {
            $block->attempts = 0;
            $block->blocked_at = null;
            $block->blocked_until = null;
        }

        $block->attempts++;
        $block->reason = $reason;

        if ($block->attempts >= self::MAX_ATTEMPTS) {
            $block->blocked_at = now();
            $block->blocked_until = now()->addHours(self::LOCK_HOURS);
        }

        $block->save();
    }

    public function clear(string $ip): void
    {
        IpBlock::where('ip_address', $ip)->delete();
    }

    /**
     * @return Collection<int, IpBlock>
     */
    public function blockedIps(): Collection
    {
        return IpBlock::query()
            ->where('attempts', '>', 0)
            ->orderByDesc('updated_at')
            ->get();
    }
}
