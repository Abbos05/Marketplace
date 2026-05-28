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
use Illuminate\Support\Facades\Log; // Добавляем импорт

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
            // Форматируем имя пользователя
            $formattedName = $this->formatUserName($googleUser->name);
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
                    'name' => $formattedName,
                    'avatar' => $googleUser->avatar,
                    'email' => $email,
                    'password' => Hash::make('temp_password_' . rand(1000, 9999)),
                ];
                $user = User::create($userData);
            }

            Auth::login($user, true);
            $request->session()->regenerate();
            app(LoginHistoryRecorder::class)->record($request, $user, 'google');
            return redirect('/profile')->with('success', 'Успешный вход через Google');
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Ошибка авторизации через Google: ' . $e->getMessage());
        }
    }


    function formatUserName($fullName)
    {
        $maxLength = 25;
        $fullName = trim($fullName);

        Log::info('Форматирование имени: ' . $fullName);

        // Пустое имя
        if (empty($fullName)) {
            Log::info('Имя пустое → Пользователь');
            return 'Пользователь';
        }
        if(mb_strlen($fullName) < $maxLength){
            return $fullName;
        }
        // Разбиваем по пробелам (поддержка кириллицы)
        $parts = preg_split('/\s+/', $fullName);
        $parts = array_filter($parts);

        Log::info('Части имени: ' . json_encode($parts, JSON_UNESCAPED_UNICODE));
    
        if (count($parts) === 1) {
            $word = $parts[0];

            if (mb_strlen($word) > $maxLength) {
                Log::info('Одно слово > 10 символов → Пользователь');
                return 'Пользователь';
            }

            Log::info('Одно слово ≤ 10 → ' . $word);
            return $word;
        }

        // === НЕСКОЛЬКО СЛОВ ===
        $lastName = array_pop($parts); // Последнее слово — фамилия
        $initials = [];

        foreach ($parts as $part) {
            $initials[] = mb_substr($part, 0, 1); // Первая буква (кириллица!)
        }

        $formatted = $lastName . ' ' . implode(' ', $initials);

        Log::info('Сформировано: ' . $formatted . ' (длина: ' . mb_strlen($formatted) . ')');

        if (mb_strlen($formatted) > $maxLength) {
            $formatted = mb_substr($formatted, 0, $maxLength);
            Log::info('Обрезано до 10 символов: ' . $formatted);
        }
        Log::info('Итог: ' . $formatted);
        return $formatted;
    }
}
