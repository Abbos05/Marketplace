<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginHistoryRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;

class GitHubController extends Controller
{
    /**
     * Перенаправляет пользователя на страницу авторизации GitHub
     */
    public function redirectToProvider()
    {
        $redirectUrl = url('/auth/github/callback');
        
        Log::info('GitHub Redirect Started', [
            'redirect_url' => $redirectUrl,
            'client_id' => config('services.github.client_id')
        ]);
        
        return Socialite::driver('github')
            ->redirectUrl($redirectUrl)
            ->scopes(['read:user', 'user:email'])
            ->redirect();
    }

    /**
     * Обрабатывает callback от GitHub
     */
    public function handleCallback(Request $request)
    {
        try {
            Log::info('GitHub Callback Started', [
                'full_url' => $request->fullUrl(),
                'has_code' => $request->has('code'),
                'code' => $request->has('code') ? substr($request->code, 0, 10) . '...' : null,
                'session_id' => session()->getId()
            ]);

            // Проверяем, есть ли код
            if (!$request->has('code')) {
                Log::error('No code in GitHub callback');
                return redirect('/login')->with('error', 'Ошибка авторизации: не получен код подтверждения');
            }

            $redirectUrl = url('/auth/github/callback');
            
            Log::info('Attempting to get GitHub user', ['redirect_url' => $redirectUrl]);
            
            // Пробуем получить пользователя
            $githubUser = Socialite::driver('github')
                ->redirectUrl($redirectUrl)
                ->stateless() // Временно для отладки
                ->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))
                ->user();

            Log::info('GitHub User Retrieved', [
                'id' => $githubUser->getId(),
                'name' => $githubUser->getName(),
                'email' => $githubUser->getEmail(),
                'nickname' => $githubUser->getNickname()
            ]);

            // Получаем email
            $email = $githubUser->getEmail();
            
            if (!$email) {
                Log::warning('No email from GitHub, trying to get from API');
                $email = $this->getGitHubEmailFromApi($githubUser->token);
                
                if (!$email) {
                    return redirect('/login')->with('error', 'GitHub не передал email. Убедитесь, что в настройках аккаунта указан публичный email.');
                }
            }

            // Разделяем имя
            $userNames = $this->splitGitHubName($githubUser->getName() ?? $githubUser->getNickname());

            // Поиск пользователя
            $user = User::withTrashed()->where('email', $email)->first();
            
            if ($user?->trashed()) {
                return redirect('/login')->with('error', 'Аккаунт с этой почтой был удалён.');
            }

            // Создание или обновление пользователя
            if (!$user) {
                $userData = [
                    'newPassw' => true,
                    'name' => $userNames['name'] ?? $githubUser->getNickname(),
                    'last_name' => $userNames['last_name'] ?? null,
                    'avatar' => $githubUser->getAvatar(),
                    'email' => $email,
                    'password' => Hash::make('temp_password_' . rand(1000, 9999)),
                ];
                $user = User::create($userData);
                Log::info('New user created', ['user_id' => $user->id]);
            } elseif (!$user->name && $userNames['name']) {
                $user->update([
                    'name' => $userNames['name'],
                    'last_name' => $userNames['last_name']
                ]);
                Log::info('User updated', ['user_id' => $user->id]);
            }

            // Авторизация
            Auth::login($user, true);
            $request->session()->regenerate();
            app(LoginHistoryRecorder::class)->record($request, $user, 'github');

            return redirect('/profile')->with('success', 'Успешный вход через GitHub');
            
        } catch (\Exception $e) {
            Log::error('GitHub Auth Error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Более информативные сообщения об ошибке
            if (str_contains($e->getMessage(), '401')) {
                $error = 'Ошибка аутентификации GitHub. Проверьте настройки приложения:';
                $error .= ' 1) Client ID и Secret правильные?';
                $error .= ' 2) В настройках GitHub указан callback URL: ' . url('/auth/github/callback');
                $error .= ' 3) Попробуйте пересоздать Client Secret в GitHub';
            } elseif (str_contains($e->getMessage(), 'code')) {
                $error = 'Ошибка при получении токена. Попробуйте войти снова.';
            } else {
                $error = 'Ошибка авторизации через GitHub: ' . $e->getMessage();
            }
            
            return redirect('/login')->with('error', $error);
        }
    }

    /**
     * Получение email через API GitHub
     */
    private function getGitHubEmailFromApi(string $accessToken): ?string
    {
        try {
            Log::info('Trying to get email from GitHub API');
            
            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'Laravel-App'
                ]
            ]);
            
            $response = $client->get('https://api.github.com/user/emails');
            $emails = json_decode($response->getBody(), true);
            
            Log::info('GitHub API emails response', ['emails' => $emails]);
            
            foreach ($emails as $emailData) {
                if (isset($emailData['primary']) && $emailData['primary'] === true && 
                    isset($emailData['verified']) && $emailData['verified'] === true) {
                    return $emailData['email'];
                }
            }
            
            foreach ($emails as $emailData) {
                if (isset($emailData['verified']) && $emailData['verified'] === true) {
                    return $emailData['email'];
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to get GitHub email via API: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Разделяет имя
     */
    private function splitGitHubName(?string $fullName): array
    {
        $result = ['name' => null, 'last_name' => null];
        
        if (empty($fullName)) {
            return $result;
        }
        
        $parts = explode(' ', trim($fullName), 2);
        
        if (count($parts) === 2) {
            $result['name'] = $parts[0];
            $result['last_name'] = $parts[1];
        } else {
            $result['name'] = $fullName;
        }
        
        return $result;
    }
}