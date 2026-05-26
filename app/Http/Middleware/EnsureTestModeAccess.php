<?php

namespace App\Http\Middleware;

use App\Support\TestModeAccess;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureTestModeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! TestModeAccess::isEnabled()) {
            return $next($request);
        }

        if ($request->routeIs('test-mode.access.show', 'test-mode.access.submit')) {
            return $next($request);
        }

        if (TestModeAccess::isGranted($request->session())) {
            return $next($request);
        }

        $url = route('test-mode.access.show');

        // Blade-страница пароля не в Inertia: иначе HTML встраивается внутрь текущего экрана.
        if ($request->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect($url);
    }
}
