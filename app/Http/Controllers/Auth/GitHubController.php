<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginHistoryRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Exception;

class GitHubController extends Controller
{
    /**
     * Перенаправляет пользователя на страницу авторизации GitHub
     */
    public function redirectToProvider()
    {
        return Socialite::driver('github')
            ->scopes(['read:user', 'user:email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * Обрабатывает callback от GitHub, авторизует пользователя
     */
    public function handleCallback(Request $request)
    {
        try {
            // Получаем пользователя из GitHub с правильной конфигурацией
            $githubUser = Socialite::driver('github')->user();
            
            // Логируем полученные данные для отладки
            Log::info('GitHub user data:', [
                'id' => $githubUser->getId(),
                'email' => $githubUser->getEmail(),
                'name' => $githubUser->getName()
            ]);

            $email = $githubUser->getEmail();
            if (!$email) {
                // Пробуем получить email через альтернативный метод
                $email = $githubUser->getEmail() ?? $githubUser->email;
                
                if (!$email) {
                    return redirect('/login')->with('error', 
                        'GitHub не передал email. Убедитесь, что email подтвержден и не скрыт в настройках приватности.'
                    );
                }
            }

            $user = User::withTrashed()->where('email', $email)->first();
            
            if ($user && $user->trashed()) {
                return redirect('/login')->with('error', 
                    'Аккаунт с этой почтой был удалён. Обратитесь в поддержку для восстановления доступа.'
                );
            }

            if (!$user) {
                $userData = [
                    'name' => $githubUser->getName() ?? 'user_' . substr(md5($email), 0, 8),
                    'avatar' => $githubUser->getAvatar(),
                    'email' => $email,
                    'password' => Hash::make('temp_password_' . bin2hex(random_bytes(16))),
                ];
                $user = User::create($userData);
            }

            Auth::login($user, true);
            $request->session()->regenerate();
            app(LoginHistoryRecorder::class)->record($request, $user, 'github');

            return redirect('/profile')->with('success', 'Успешный вход через GitHub');
            
        } catch (Exception $e) {
            Log::error('GitHub auth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect('/login')->with('error', 
                'Ошибка авторизации через GitHub: ' . $e->getMessage()
            );
        }
    }
}