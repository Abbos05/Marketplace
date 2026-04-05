<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Home');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            [
                'name' => 'required|string|max:25',
                'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ],
            [
                'name.required' => 'Имя обязательно для заполнения.',
                'name.string' => 'Имя должно быть текстом.',
                'name.max' => 'Имя не может быть длиннее 25 символов.',

                'email.required' => 'Email обязателен для заполнения.',
                'email.string' => 'Email должен быть текстом.',
                'email.lowercase' => 'Email должен быть в нижнем регистре.',
                'email.email' => 'Email должен быть действительным адресом электронной почты.',
                'email.max' => 'Email не может быть длиннее 255 символов.',
                'email.unique' => 'Этот email уже зарегистрирован.',

                'password.required' => 'Пароль обязателен для заполнения.',
                'password.confirmed' => 'Пароли не совпадают.',
            ]
        );

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('profile', absolute: false));
    }
}
