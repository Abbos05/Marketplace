<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class YandexController extends Controller
{
    public function redirectToYandex()
    {
        return Socialite::driver('yandex')->scopes(['login:email'])->with(['force_confirm' => true])->redirect();
    }

    public function handleYandexCallback()
    {
        try {
            $yandexUser = Socialite::driver('yandex')->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))
                ->user();
        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Ошибка авторизации через Yandex.');
        }
        $phone = $yandexUser->getPhone();
        // Ищем пользователя по Yandex ID или email
        $user = User::firstOrCreate(
            ['email' => $yandexUser->getEmail()],
            [
                'name' => $yandexUser->getName(),
                'newPassw' => true,
                'phone' => $phone,
                'avatar' => $yandexUser->getAvatar(),
                'password' => Hash::make(uniqid()),  // Рандомный пароль
                'email_verified_at' => now(),  // Автоверификация
            ]
        );

        Auth::login($user);

        return redirect('/profile');  // Или ваша страница после логина
    }
}
