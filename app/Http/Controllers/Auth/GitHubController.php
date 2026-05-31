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
        // Явно указываем HTTPS URL
        $redirectUrl = 'https://alvoraplace.ru/auth/github/callback';
        
        Log::info('GitHub Redirect', ['redirect_url' => $redirectUrl]);
        
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
            // Тот же URL для callback
            $redirectUrl = 'https://alvoraplace.ru/auth/github/callback';
            
            Log::info('GitHub Callback', [
                'full_url' => $request->fullUrl(),
                'has_code' => $request->has('code'),
                'expected_redirect' => $redirectUrl
            ]);

            if (!$request->has('code')) {
                return redirect('/login')->with('error', 'Не получен код авторизации');
            }

            // Получаем пользователя
            $githubUser = Socialite::driver('github')
                ->redirectUrl($redirectUrl)
                ->setHttpClient(new \GuzzleHttp\Client(['verify' => true])) // На продакшене verify = true
                ->user();

            Log::info('GitHub User', [
                'id' => $githubUser->getId(),
                'email' => $githubUser->getEmail()
            ]);

            $email = $githubUser->getEmail();
            if (!$email) {
                $email = $this->getGitHubEmailFromApi($githubUser->token);
                
                if (!$email) {
                    return redirect('/login')->with('error', 'Не удалось получить email из GitHub');
                }
            }

            // Разделяем имя
            $userNames = $this->splitGitHubName($githubUser->getName());

            // Поиск пользователя
            $user = User::withTrashed()->where('email', $email)->first();
            
            if ($user?->trashed()) {
                return redirect('/login')->with('error', 'Аккаунт был удалён');
            }

            if (!$user) {
                $user = User::create([
                    'newPassw' => true,
                    'name' => $userNames['name'] ?? $githubUser->getNickname(),
                    'last_name' => $userNames['last_name'] ?? null,
                    'avatar' => $githubUser->getAvatar(),
                    'email' => $email,
                    'password' => Hash::make(uniqid()),
                ]);
            }

            Auth::login($user, true);
            $request->session()->regenerate();
            app(LoginHistoryRecorder::class)->record($request, $user, 'github');

            return redirect('/profile')->with('success', 'Успешный вход через GitHub');
            
        } catch (\Exception $e) {
            Log::error('GitHub Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect('/login')->with('error', 'Ошибка авторизации GitHub. Проверьте настройки приложения.');
        }
    }

    private function getGitHubEmailFromApi(string $accessToken): ?string
    {
        try {
            $client = new \GuzzleHttp\Client([
                'verify' => true,
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/vnd.github.v3+json',
                ]
            ]);
            
            $response = $client->get('https://api.github.com/user/emails');
            $emails = json_decode($response->getBody(), true);
            
            foreach ($emails as $emailData) {
                if (($emailData['primary'] ?? false) && ($emailData['verified'] ?? false)) {
                    return $emailData['email'];
                }
            }
        } catch (\Exception $e) {
            Log::error('GitHub API Error: ' . $e->getMessage());
        }
        
        return null;
    }

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