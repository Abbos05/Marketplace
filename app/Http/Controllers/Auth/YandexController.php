<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginHistoryRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            $yandexUser = Socialite::driver('yandex')->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))
                ->user();
        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Ошибка авторизации через Yandex.');
        }

        $email = $yandexUser->getEmail();
        if (! $email) {
            return redirect('/login')->with('error', 'Yandex не передал email. Выберите аккаунт с подтвержденной почтой.');
        }

        $phone = $this->normalizePhone($yandexUser->getPhone());
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
                'password' => Hash::make(uniqid()),  // Рандомный пароль
                'email_verified_at' => now(),  // Автоверификация
            ]);
        } elseif (! $user->phone && $phone) {
            $user->update(['phone' => $phone]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        app(LoginHistoryRecorder::class)->record($request, $user, 'yandex');

        $redirect = redirect('/profile');

        return $phoneWarning
            ? $redirect->with('error', $phoneWarning)
            : $redirect->with('success', 'Успешный вход через Yandex');
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
