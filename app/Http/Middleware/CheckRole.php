<?php 

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Closure;
use Inertia\Inertia;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, $role = null)
    {
        if (!auth()->check()) {
            abort(403, 'Вы должны быть авторизованы для доступа к этой странице.');
        }

        if ($role && !(auth()->user()->role === $role || auth()->user()->role === 'admin')) {
            return Inertia::render('errors.403');

        }
        
        return $next($request);
    }
}
