<?php

namespace App\Http\Controllers;

use App\Support\TestModeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TestModeAccessController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! TestModeAccess::isEnabled()) {
            return redirect()->route('home');
        }

        if (TestModeAccess::isGranted($request->session())) {
            return redirect()->intended(route('home'));
        }

        return view('test-mode-access', [
            'telegramUrl' => (string) config('test_mode.telegram_url', ''),
            'telegramLabel' => (string) config('test_mode.telegram_label', ''),
        ]);
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

        if (! hash_equals($password, $inputPassword)) {
            return back()
                ->withErrors(['password' => 'Неверный тестовый пароль.'])
                ->withInput($request->except('password'));
        }

        TestModeAccess::grant($request->session());

        return redirect()->intended(route('home'));
    }
}
