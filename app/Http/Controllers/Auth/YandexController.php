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
        // Add phone scope if you need phone numbers
        return Socialite::driver('yandex')
            ->scopes(['login:email', 'login:phone']) // Added phone scope
            ->with(['force_confirm' => true])
            ->redirect();
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
            return redirect('/login')->with('error', 'Yandex не передал email. Выберите аккаунт с подтвержденной почтой.');
        }

        // Get phone number from Yandex API
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
                'name' => $yandexUser->getName(),
                'newPassw' => true,
                'email' => $email,
                'phone' => $phone,
                'avatar' => $yandexUser->getAvatar(),
                'password' => Hash::make(uniqid()),
                'email_verified_at' => now(),
            ]);
        } elseif (! $user->phone && $phone) {
            $user->update(['phone' => $phone]);
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
     * Get phone number from Yandex API
     */
    private function getYandexPhoneNumber(string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://login.yandex.ru/info', [
                    'format' => 'json',
                    'with_phone' => 'yes' // Request phone info
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Yandex returns phone in format: "+7XXXXXXXXXX"
                return $data['phone'] ?? null;
            }
        } catch (\Exception $e) {
            // Log error but continue without phone
            \Log::warning('Failed to get Yandex phone: ' . $e->getMessage());
        }

        return null;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // Remove all non-digits
        $phone = preg_replace('/\D/', '', $phone);
        
        // Remove leading 8 or 7 and add 7
        if (strlen($phone) === 11 && ($phone[0] === '8' || $phone[0] === '7')) {
            $phone = '7' . substr($phone, 1);
        }
        
        // Handle international format with country code
        if (strlen($phone) === 12 && substr($phone, 0, 2) === '79') {
            // Already has 7 and 9, keep as is
        } elseif (strlen($phone) === 11 && $phone[0] === '9') {
            $phone = '7' . $phone;
        }

        return preg_match('/^7\d{10}$/', $phone) ? $phone : null;
    }
}