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
        // Явно указываем полный URL для callback
        $redirectUrl = url('/auth/github/callback');
        
        Log::info('GitHub redirect URL', ['url' => $redirectUrl]);
        
        return Socialite::driver('github')
            ->redirectUrl($redirectUrl) // ← КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ
            ->scopes(['read:user', 'user:email'])
            ->redirect();
    }

    /**
     * Обрабатывает callback от GitHub
     */
    public function handleCallback(Request $request)
    {
        try {
            // Тот же URL должен совпадать
            $redirectUrl = url('/auth/github/callback');
            
            Log::info('GitHub callback started', [
                'redirect_url' => $redirectUrl,
                'has_code' => $request->has('code'),
                'full_url' => $request->fullUrl()
            ]);

            $githubUser = Socialite::driver('github')
                ->redirectUrl($redirectUrl) // ← ДОЛЖНО СОВПАДАТЬ
                ->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))
                ->user();

            Log::info('GitHub user retrieved', [
                'id' => $githubUser->getId(),
                'name' => $githubUser->getName(),
                'email' => $githubUser->getEmail()
            ]);

            $email = $githubUser->getEmail();
            if (! $email) {
                return redirect('/login')->with('error', 'GitHub не передал email. Выберите аккаунт с публичной или подтвержденной почтой.');
            }

            // Разделяем имя и фамилию
            $userNames = $this->splitGitHubName($githubUser->getName() ?? $githubUser->getNickname());

            $user = User::withTrashed()->where('email', $email)->first();
            
            if ($user?->trashed()) {
                return redirect('/login')->with('error', 'Аккаунт с этой почтой был удалён. Обратитесь в поддержку для восстановления доступа.');
            }

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
            } elseif (!$user->name && $userNames['name']) {
                $user->update([
                    'name' => $userNames['name'],
                    'last_name' => $userNames['last_name']
                ]);
            }

            Auth::login($user, true);
            $request->session()->regenerate();
            app(LoginHistoryRecorder::class)->record($request, $user, 'github');

            return redirect('/profile')->with('success', 'Успешный вход через GitHub');
            
        } catch (\Exception $e) {
            Log::error('GitHub auth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect('/login')->with('error', 'Ошибка авторизации через GitHub: ' . $e->getMessage());
        }
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