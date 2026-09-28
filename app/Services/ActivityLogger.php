<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Pile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Record a meaningful business activity for the audit trail.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $module,
        string $action,
        string $description,
        ?Model $auditable = null,
        array $metadata = [],
        ?User $user = null,
    ): AuditLog {
        $user ??= auth()->user();

        return AuditLog::create([
            'user_id' => $user?->getKey(),
            'role' => $user?->role,
            'branch_id' => $user?->branch_id,
            'branch_name' => $user?->branch?->name,
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'ip_address' => Request::ip(),
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'pile_id' => $auditable instanceof Pile
                ? $auditable->getKey()
                : $auditable?->getAttribute('pile_id'),
            'metadata' => $metadata,
        ]);
    }
}
