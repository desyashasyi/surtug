<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AbsoluteSessionTimeout
{
    /**
     * Maximum session lifetime in seconds, regardless of activity.
     * 8 hours = 28800 seconds.
     */
    private const MAX_LIFETIME = 28800;

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $startedAt = session('_auth_started_at');

            if ($startedAt === null) {
                session(['_auth_started_at' => time()]);
            } elseif ((time() - $startedAt) > self::MAX_LIFETIME) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('status', 'Your session has expired. Please sign in again.');
            }
        }

        return $next($request);
    }
}