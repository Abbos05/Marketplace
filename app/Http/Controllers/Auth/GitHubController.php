<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GitHubController extends Controller
{
    /**
     * Перенаправляет пользователя на страницу авторизации GitHub
     */
    public function redirectToProvider()
    {
        return Socialite::driver('github')
            ->scopes(['read:user', 'public_repo'])->with([
                'prompt' => 'select_account',
                'force_confirm_account' => 'true'
            ]) // необязательные области доступа
            ->redirect();
    }

    /**
     * Обрабатывает callback от GitHub, авторизует пользователя
     */
    public function handleCallback()
    {
        $githubUser = Socialite::driver('github')->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))->user();


        $user = User::where('email', $githubUser->getEmail())->first();

        if (!$user) {
            $userData = [
                'newPassw' => true,
                'name' => $githubUser->getName() ?? 'user_' . substr(md5($githubUser->getEmail()), 0, 8),
                'avatar' => $githubUser->getAvatar(),
                'email' => $githubUser->getEmail(),
                'password' => Hash::make('temp_password_' . rand(1000, 9999)),
            ];
            $user = User::create($userData);
        }

        Auth::login($user);

        return redirect('/profile')->with('success', 'Успешный вход через GitHub');
    }
}
