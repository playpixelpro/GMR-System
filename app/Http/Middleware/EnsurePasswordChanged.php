<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response|RedirectResponse {
        if (
            $request->user()?->must_change_password &&
            ! $request->routeIs('password.change', 'password.update')
        ) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
