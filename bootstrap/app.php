<?php
 
use App\Http\Middleware\EnsureNotBlocked;
use App\Http\Middleware\PvzMiddleware;
use App\Http\Middleware\SellerMiddleware;
use App\Http\Middleware\TouchSessionActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\EnsureNotBlocked::class,
            TouchSessionActivity::class,
        ]);
         $middleware->alias([
            'seller' => SellerMiddleware::class,
            'pvz' => PvzMiddleware::class,
            'not_blocked' => EnsureNotBlocked::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            $message = 'Страница была открыта слишком долго. Обновите страницу и повторите действие.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'reload' => true,
                ], 419);
            }

            if ($request->is('login') || $request->is('register') || $request->is('auth/phone/*')) {
                return redirect()->route('login')->with('error', $message);
            }

            return back()->with('error', $message);
        });
    })->create();
