<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SellerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || $user->role !== 'seller') {
            return redirect()->route('profile');
        }

        if ($user->is_blocked) {
            return redirect()->route('profile')
                ->with('error', 'Аккаунт заблокирован. Панель продавца недоступна.');
        }
        
        return $next($request);
    }
}