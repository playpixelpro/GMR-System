<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use App\Support\AuthThrottle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SettingsController extends Controller
{
    public function blockedIps(AuthThrottle $throttle): View
    {
        return view('settings.blocked-ips', [
            'blocks' => $throttle->blockedIps(),
        ]);
    }

    public function unlockIp(Request $request, AuthThrottle $throttle, string $ip): RedirectResponse
    {
        $throttle->clear($ip);

        AuditLog::create([
            'module' => 'settings',
            'action' => 'IP_UNLOCKED',
            'description' => "IP address {$ip} unlocked",
            'ip_address' => $request->ip(),
            'metadata' => [
                'ip_address' => $ip,
                'unlocked_by' => $request->user()?->id,
            ],
        ]);

        return back()->with('status', "IP address {$ip} has been unlocked.");
    }

    public function activityLogs(Request $request): View
    {
        $logs = $this->filteredQuery($request)
            ->with(['user', 'branch'])
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('settings.activity-logs', [
            'logs' => $logs,
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'modules' => $this->distinctColumn('module'),
            'actions' => $this->distinctColumn('action'),
            'filters' => $request->only(['from', 'to', 'branch_id', 'user_id', 'module', 'action']),
        ]);
    }

    public function downloadActivityLogs(Request $request): Response
    {
        $records = $this->filteredQuery($request)
            ->with(['user', 'branch'])
            ->orderByDesc('created_at')
            ->get();

        $data = $records->map(function (AuditLog $log): array {
            return [
                'timestamp' => $log->created_at?->format('Y-m-d H:i:s'),
                'user' => $log->user?->name ?? ($log->user_id ? "User #{$log->user_id}" : 'System'),
                'role' => $log->role,
                'branch' => $log->branch_name ?? $log->branch?->name,
                'module' => $log->module,
                'action' => $log->action,
                'record_id' => $log->auditable_id,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'metadata' => $log->metadata,
            ];
        })->values();

        return response()->json($data);
    }

    /**
     * Build the combinable filter query used by the page and the JSON download.
     *
     * @return Builder<AuditLog>
     */
    private function filteredQuery(Request $request): Builder
    {
        return AuditLog::query()
            ->betweenDates($request->input('from'), $request->input('to'))
            ->when($request->input('branch_id'), fn (Builder $q, $value) => $q->where('branch_id', $value))
            ->when($request->input('user_id'), fn (Builder $q, $value) => $q->where('user_id', $value))
            ->when($request->input('module'), fn (Builder $q, $value) => $q->where('module', $value))
            ->when($request->input('action'), fn (Builder $q, $value) => $q->where('action', $value));
    }

    /**
     * @return Collection<int, string>
     */
    private function distinctColumn(string $column): Collection
    {
        return AuditLog::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }
}
