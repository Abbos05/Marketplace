<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Notifications\MarketplaceAlert;
use App\Support\NotificationCategory;
use App\Services\LoginHistoryRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function showLogin(Request $request)
    {
        $homeController = app(HomeController::class);
        $data = $homeController->index($request, true);
        $data['showModal'] = 'phone_auth';

        return Inertia::render('Home', $data);
    }

    public function showRegister(Request $request)
    {
        $homeController = app(HomeController::class);
        $data = $homeController->index($request, true);
        $data['showModal'] = 'phone_auth';

        return Inertia::render('Home', $data);
    }


    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->save(); // ← добавь эту строку
        $user = $request->user();
        if ($user instanceof User) {
            app(LoginHistoryRecorder::class)->record($request, $user, 'password');
            $user->notify(new MarketplaceAlert(
                'Вход в аккаунт',
                'Вход выполнен '.now()->timezone('Europe/Moscow')->format('d.m.Y H:i').' (MSK). Если это были не вы, смените пароль.',
                null,
                NotificationCategory::Security,
            ));
        }

        return redirect(route('profile', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $sessionKey = (string) config('test_mode.session_key', 'test_mode_access_granted');
        $testModeGranted = (bool) $request->session()->get($sessionKey, false);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($testModeGranted && (string) config('test_mode.password', '') !== '') {
            $request->session()->put($sessionKey, true);
        }

        return redirect()->route('home');
    }
}
