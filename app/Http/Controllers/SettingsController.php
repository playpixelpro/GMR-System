<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\AuthThrottle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
            'action' => 'IP_UNLOCKED',
            'metadata' => [
                'ip_address' => $ip,
                'unlocked_by' => $request->user()?->id,
            ],
        ]);

        return back()->with('status', "IP address {$ip} has been unlocked.");
    }
}
