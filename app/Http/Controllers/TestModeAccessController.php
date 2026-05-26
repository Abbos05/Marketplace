<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TestModeAccessController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if ((string) config('test_mode.password', '') === '') {
            return redirect()->route('home');
        }

        if ((bool) session((string) config('test_mode.session_key', 'test_mode_access_granted'), false)) {
            return redirect()->intended(route('home'));
        }

        return view('test-mode-access');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'Введите тестовый пароль.',
        ]);

        $password = (string) config('test_mode.password', '');
        $inputPassword = (string) $request->input('password');

        if (!hash_equals($password, $inputPassword)) {
            return back()
                ->withErrors(['password' => 'Неверный тестовый пароль.'])
                ->withInput($request->except('password'));
        }

        $request->session()->put((string) config('test_mode.session_key', 'test_mode_access_granted'), true);

        return redirect()->intended(route('home'));
    }
}
