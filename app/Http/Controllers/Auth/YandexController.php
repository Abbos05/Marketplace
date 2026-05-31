<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginHistoryRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

class YandexController extends Controller
{
    public function redirectToYandex()
    {
       return Socialite::driver('yandex')->scopes(['login:email'])->with(['force_confirm' => true])->redirect();

    }

    public function handleYandexCallback(Request $request)
    {
        try {
            $yandexUser = Socialite::driver('yandex')
                ->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))
                ->user();
        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Ошибка авторизации через Yandex.');
        }

        $email = $yandexUser->getEmail();
        if (! $email) {
            return redirect('/login')->with('error', 'Yandex не передал email. Выберите аккаунт с подтверждённой почтой.');
        }

        // Разделяем полное имя на имя и фамилию
        $fullName = $yandexUser->getName();
        $nameParts = $this->splitFullName($fullName);
        
        // Получение номера телефона (если нужно)
        $phone = $this->getYandexPhoneNumber($yandexUser->token);
        $phone = $this->normalizePhone($phone);
        $phoneWarning = null;

        if ($phone && User::withTrashed()->where('phone', $phone)
            ->where(function ($query) use ($email) {
                $query->where('email', '!=', $email)
                    ->orWhereNull('email');
            })
            ->exists()) {
            $phone = null;
            $phoneWarning = 'Телефон из Yandex уже привязан к другому аккаунту. Вход выполнен без привязки телефона.';
        }

        $user = User::withTrashed()->where('email', $email)->first();

        if ($user?->trashed()) {
            return redirect('/login')->with('error', 'Аккаунт с этой почтой был удалён. Обратитесь в поддержку для восстановления доступа.');
        }

        if (! $user) {
            $user = User::create([
                'name' => $nameParts['name'],      // Имя
                'last_name' => $nameParts['last_name'], // Фамилия (может быть null)
                'newPassw' => true,
                'email' => $email,
                'phone' => $phone,
                'avatar' => $yandexUser->getAvatar(),
                'password' => Hash::make(uniqid()),
                'email_verified_at' => now(),
            ]);
        } elseif (! $user->phone && $phone) {
            $user->update(['phone' => $phone]);
            
            // Если у пользователя нет имени/фамилии, обновим из Яндекса
            if (!$user->name && $nameParts['name']) {
                $user->update([
                    'name' => $nameParts['name'],
                    'last_name' => $nameParts['last_name']
                ]);
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        app(LoginHistoryRecorder::class)->record($request, $user, 'yandex');

        $redirect = redirect('/profile');

        return $phoneWarning
            ? $redirect->with('error', $phoneWarning)
            : $redirect->with('success', 'Успешный вход через Yandex');
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
            // last_name остаётся null
        }

        return $result;
    }

    /**
     * Получение номера телефона из API Яндекса
     * Требует модерации приложения и права login:phone
     */
    private function getYandexPhoneNumber(string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://login.yandex.ru/info', [
                    'format' => 'json',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['default_phone']['number'] ?? null;
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get Yandex phone: ' . $e->getMessage());
        }

        return null;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $phone = preg_replace('/\D/', '', $phone);
        
        if (str_starts_with($phone, '8')) {
            $phone = '7' . substr($phone, 1);
        }

        return preg_match('/^7\d{10}$/', $phone) ? $phone : null;
    }
}