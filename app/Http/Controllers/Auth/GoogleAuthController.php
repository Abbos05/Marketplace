<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LoginHistoryRecorder;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->with([
                'prompt' => 'select_account',
                'force_confirm_account' => 'true'
            ])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $guzzleClient = new GuzzleClient([
                'verify' => false,
            ]);
            $googleUser = Socialite::driver('google')
                ->setHttpClient($guzzleClient)
                ->stateless()
                ->user();
            
            // Получаем имя и фамилию из Google
            $userNames = $this->getGoogleUserNames($googleUser);
            
            $email = $googleUser->getEmail() ?? $googleUser->email;
            if (! $email) {
                return redirect('/login')->with('error', 'Google не передал email. Выберите аккаунт с подтвержденной почтой.');
            }

            $user = User::withTrashed()->where('email', $email)->first();
            if ($user?->trashed()) {
                return redirect('/login')->with('error', 'Аккаунт с этой почтой был удалён. Обратитесь в поддержку для восстановления доступа.');
            }

            if (!$user) {
                $userData = [
                    'newPassw' => true,
                    'name' => $userNames['name'],      // Имя
                    'last_name' => $userNames['last_name'], // Фамилия
                    'avatar' => $googleUser->avatar,
                    'email' => $email,
                    'password' => Hash::make('temp_password_' . rand(1000, 9999)),
                ];
                $user = User::create($userData);
            } else {
                // Если пользователь существует, но нет имени/фамилии - обновим
                if (!$user->name && $userNames['name']) {
                    $user->update([
                        'name' => $userNames['name'],
                        'last_name' => $userNames['last_name']
                    ]);
                }
            }

            Auth::login($user, true);
            $request->session()->regenerate();
            app(LoginHistoryRecorder::class)->record($request, $user, 'google');
            return redirect('/profile')->with('success', 'Успешный вход через Google');
        } catch (\Exception $e) {
            Log::error('Google auth error: ' . $e->getMessage());
            return redirect('/')->with('error', 'Ошибка авторизации через Google: ' . $e->getMessage());
        }
    }

    /**
     * Получает имя и фамилию из объекта Google User
     * 
     * @param \Laravel\Socialite\Two\User $googleUser
     * @return array{name: string|null, last_name: string|null}
     */
    private function getGoogleUserNames($googleUser): array
    {
        $result = [
            'name' => null,
            'last_name' => null
        ];

        // Способ 1: Используем raw данные от Google
        $raw = $googleUser->getRaw();
        
        if (isset($raw['given_name'])) {
            $result['name'] = $raw['given_name'];      // Имя
        }
        
        if (isset($raw['family_name'])) {
            $result['last_name'] = $raw['family_name']; // Фамилия
        }

        // Способ 2: Если raw данные не дали результат, пробуем разобрать полное имя
        if (!$result['name'] && $googleUser->name) {
            $nameParts = $this->splitFullName($googleUser->name);
            $result['name'] = $nameParts['name'];
            $result['last_name'] = $nameParts['last_name'];
        }

        // Способ 3: Если имя всё ещё пустое, ставим значение по умолчанию
        if (!$result['name']) {
            $result['name'] = 'Пользователь';
        }

        Log::info('Google user names - name: ' . $result['name'] . ', last_name: ' . $result['last_name']);
        
        return $result;
    }

    /**
     * Разделяет полное имя на имя и фамилию
     * 
     * @param string|null $fullName
     * @return array{name: string|null, last_name: string|null}
     */
    private function splitFullName(?string $fullName): array
    {
        $result = [
            'name' => null,
            'last_name' => null
        ];

        if (empty($fullName)) {
            return $result;
        }

        $parts = explode(' ', trim($fullName), 2);
        
        if (count($parts) === 2) {
            // Два слова - первое имя, второе фамилия
            $result['name'] = $parts[0];
            $result['last_name'] = $parts[1];
        } else {
            // Одно слово или больше - всё в имя
            $result['name'] = $fullName;
        }

        return $result;
    }
}