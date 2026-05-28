<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginHistoryRecorder;
use Illuminate\Http\Request;
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
    public function handleCallback(Request $request)
    {
        $githubUser = Socialite::driver('github')->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))->user();

        $email = $githubUser->getEmail();
        if (! $email) {
            return redirect('/login')->with('error', 'GitHub не передал email. Выберите аккаунт с публичной или подтвержденной почтой.');
        }

        $user = User::withTrashed()->where('email', $email)->first();
        if ($user?->trashed()) {
            return redirect('/login')->with('error', 'Аккаунт с этой почтой был удалён. Обратитесь в поддержку для восстановления доступа.');
        }

        if (!$user) {
            $userData = [
                'newPassw' => true,
                'name' => $githubUser->getName() ?? 'user_' . substr(md5($email), 0, 8),
                'avatar' => $githubUser->getAvatar(),
                'email' => $email,
                'password' => Hash::make('temp_password_' . rand(1000, 9999)),
            ];
            $user = User::create($userData);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        app(LoginHistoryRecorder::class)->record($request, $user, 'github');

        return redirect('/profile')->with('success', 'Успешный вход через GitHub');
    }
}
