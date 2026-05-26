<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBlocked
{
    protected const ALLOWED_ROUTE_NAMES = [
        'profile',
        'profile.update',
        'profile.default-pickup',
        'profile.orders',
        'profile.filter',
        'favorites.index',
        'favorites.toggle',
        'messages.index',
        'messages.embed',
        'messages.open',
        'messages.poll',
        'messages.messages.store',
        'messages.messages.update',
        'messages.messages.destroy',
        'messages.hide',
        'messages.conversations',
        'notifications.index',
        'notifications.read-all',
        'notifications.read',
        'order.show',
        'order.receipt',
        'order.document',
        'order.cancel',
        'order.refund',
        'order.refund.checkout',
        'order.refund.complete',
        'order.repeat',
        'pvz.queue',
        'pvz.orders',
        'pvz.orders.status',
        'logout',
        'login',
        'register',
    ];

    protected const BLOCKED_PREFIXES = [
        'cart.',
        'order.create',
        'seller.',
        'pickup.apply',
        'payment.',
        'stripe.',
        'seller-profile',
        'promo.validate',
        'reviews.store',
        'reviews.vote',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_blocked) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';

        if (str_starts_with($routeName, 'admin.')) {
            return $next($request);
        }

        if (in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json(['message' => 'Аккаунт заблокирован.'], 403);
                }

                return redirect()->route('profile')
                    ->with('error', 'Аккаунт заблокирован. Оформление заказов и продажа недоступны.');
            }
        }

        return $next($request);
    }
}
