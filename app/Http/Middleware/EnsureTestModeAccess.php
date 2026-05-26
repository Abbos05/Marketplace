<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTestModeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $password = (string) config('test_mode.password', '');
        $sessionKey = (string) config('test_mode.session_key', 'test_mode_access_granted');

        if ($password === '') {
            return $next($request);
        }

        if ($request->routeIs('test-mode.access.show', 'test-mode.access.submit')) {
            return $next($request);
        }

        if ((bool) $request->session()->get($sessionKey, false)) {
            return $next($request);
        }

        return redirect()->route('test-mode.access.show');
    }
}
