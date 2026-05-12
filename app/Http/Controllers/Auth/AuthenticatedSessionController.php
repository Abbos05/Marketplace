<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeController;
use App\Http\Requests\Auth\LoginRequest;
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
        $homeController = new HomeController();
        // Передаём true для $returnDataOnly — получаем массив данных
        $data = $homeController->index($request, true);
        $data['showModal'] = 'login';

        return Inertia::render('Home', $data);
    }

    public function showRegister(Request $request)
    {
        $homeController = new HomeController();
        // Передаём true для $returnDataOnly — получаем массив данных
        $data = $homeController->index($request, true);
        $data['showModal'] = 'register';

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
        return redirect(route('profile', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('profile');
    }
}
