<?php 

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (! auth()->check()) {
            abort(403, 'Вы должны быть авторизованы для доступа к этой странице.');
        }

        if ($roles !== []) {
            $userRole = auth()->user()->role;

            if ($userRole === 'admin' || in_array($userRole, $roles, true)) {
                return $next($request);
            }

            abort(403);
        }

        return $next($request);
    }
}
