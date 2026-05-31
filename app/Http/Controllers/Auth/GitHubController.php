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
        // Явно указываем redirect_uri для гарантии соответствия
        return Socialite::driver('github')
            ->redirectUrl(config('services.github.redirect'))
            ->scopes(['read:user', 'user:email'])
            ->redirect();
    }

    /**
     * Обрабатывает callback от GitHub
     */
    public function handleCallback(Request $request)
    {
        try {
            // Логируем входящий запрос для отладки
            Log::info('GitHub callback received', [
                'code_present' => $request->has('code'),
                'url' => $request->fullUrl()
            ]);

            // Получаем пользователя из GitHub с явным указанием redirect_uri
            $githubUser = Socialite::driver('github')
                ->redirectUrl(config('services.github.redirect'))
                ->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))
                ->user();

            Log::info('GitHub user retrieved', [
                'id' => $githubUser->getId(),
                'name' => $githubUser->getName(),
                'email' => $githubUser->getEmail()
            ]);

            // Получаем email (может быть null, если email приватный)
            $email = $githubUser->getEmail();
            
            if (!$email) {
                // Пробуем получить email через отдельный API запрос
                $email = $this->getGitHubEmailFromApi($githubUser->token);
                
                if (!$email) {
                    return redirect('/login')->with('error', 
                        'GitHub не передал email. Убедитесь, что в настройках аккаунта указан публичный email или предоставлен доступ к email.');
                }
            }

            // Разделяем имя и фамилию (GitHub обычно не разделяет)
            $userNames = $this->splitGitHubName($githubUser->getName() ?? $githubUser->getNickname());

            // Поиск пользователя
            $user = User::withTrashed()->where('email', $email)->first();
            
            if ($user?->trashed()) {
                return redirect('/login')->with('error', 
                    'Аккаунт с этой почтой был удалён. Обратитесь в поддержку для восстановления доступа.');
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
                
                Log::info('New user created from GitHub', ['user_id' => $user->id, 'email' => $email]);
            } elseif (!$user->name && $userNames['name']) {
                // Обновляем имя, если его нет
                $user->update([
                    'name' => $userNames['name'],
                    'last_name' => $userNames['last_name']
                ]);
            }

            // Авторизация
            Auth::login($user, true);
            $request->session()->regenerate();
            app(LoginHistoryRecorder::class)->record($request, $user, 'github');

            return redirect('/profile')->with('success', 'Успешный вход через GitHub');
            
        } catch (\Exception $e) {
            Log::error('GitHub auth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            // Более информативное сообщение об ошибке
            $errorMessage = 'Ошибка авторизации через GitHub';
            if (str_contains($e->getMessage(), '401')) {
                $errorMessage .= ': Проблема с аутентификацией. Проверьте настройки приложения в GitHub и убедитесь, что redirect_uri совпадает.';
            } elseif (str_contains($e->getMessage(), 'code')) {
                $errorMessage .= ': Проблема с получением токена.';
            }
            
            return redirect('/login')->with('error', $errorMessage);
        }
    }

    /**
     * Получение email через API GitHub (если Socialite не вернул)
     */
    private function getGitHubEmailFromApi(string $accessToken): ?string
    {
        try {
            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/vnd.github.v3+json',
                ]
            ]);
            
            $response = $client->get('https://api.github.com/user/emails');
            $emails = json_decode($response->getBody(), true);
            
            // Ищем primary и verified email
            foreach ($emails as $emailData) {
                if (isset($emailData['primary']) && $emailData['primary'] === true && 
                    isset($emailData['verified']) && $emailData['verified'] === true) {
                    return $emailData['email'];
                }
            }
            
            // Если primary нет, берем первый verified
            foreach ($emails as $emailData) {
                if (isset($emailData['verified']) && $emailData['verified'] === true) {
                    return $emailData['email'];
                }
            }
            
        } catch (\Exception $e) {
            Log::warning('Failed to get GitHub email via API: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Разделяет имя GitHub на имя и фамилию
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