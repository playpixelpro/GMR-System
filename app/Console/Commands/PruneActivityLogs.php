<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('activity-logs:prune')]
#[Description('Permanently delete activity log records older than one month')]
class PruneActivityLogs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = AuditLog::query()
            ->where('created_at', '<', now()->subMonth())
            ->delete();

        $this->info("Deleted {$deleted} activity log record(s) older than one month.");

        return self::SUCCESS;
    }
}
