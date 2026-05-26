<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TouchSessionActivity
{
    private const DEBOUNCE_SECONDS = 90;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user() || ! $request->hasSession()) {
            return $response;
        }

        if ($request->routeIs('session.heartbeat')) {
            return $response;
        }

        $sessionId = $request->session()->getId();
        if (! $sessionId) {
            return $response;
        }

        $cacheKey = 'session_touch:'.$sessionId;
        if (Cache::has($cacheKey)) {
            return $response;
        }

        DB::table('sessions')
            ->where('id', $sessionId)
            ->update(['last_activity' => time()]);

        Cache::put($cacheKey, true, self::DEBOUNCE_SECONDS);

        return $response;
    }
}
