<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PhoneAuthController extends Controller
{
    /**
     * Шаг 1: принять номер телефона, найти или создать пользователя,
     * сохранить OTP в сессии и вернуть информацию о пользователе.
     */
    public function sendCode(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:11|max:15',
        ]);

        $phone = preg_replace('/\D/', '', $request->phone);

        // Нормализуем: если начинается на 8 — заменяем на 7
        if (str_starts_with($phone, '8')) {
            $phone = '7' . substr($phone, 1);
        }

        $user = User::where('phone', $phone)->first();
        $isNew = $user === null;

        if ($isNew) {
            // Создаём нового пользователя — email/name заполнятся при верификации
            $user = User::create([
                'phone'    => $phone,
                'password' => Hash::make(uniqid('phone_', true)),
                'newPassw' => true,
            ]);
        }

        // Сохраняем OTP в сессии (всегда 000000 до подключения SMS-сервиса)
        $otp = '000000';
        $request->session()->put("phone_otp_{$phone}", $otp);
        $request->session()->put("phone_otp_{$phone}_expires", now()->addMinutes(10)->timestamp);

        return response()->json([
            'success'     => true,
            'is_new'      => $isNew,
            'has_password' => !$user->newPassw,
            'has_email'   => (bool) $user->email,
        ]);
    }

    /**
     * Шаг 2а: подтвердить SMS-код и залогинить.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'code'  => 'required|string|size:6',
        ]);

        $phone = preg_replace('/\D/', '', $request->phone);
        if (str_starts_with($phone, '8')) {
            $phone = '7' . substr($phone, 1);
        }

        if (!$this->checkOtp($request, $phone, $request->code)) {
            return response()->json(['success' => false, 'message' => 'Неверный или истёкший код'], 422);
        }

        $user = User::where('phone', $phone)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Пользователь не найден'], 422);
        }

        $this->clearOtp($request, $phone);
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['success' => true, 'redirect' => route('profile')]);
    }

    /**
     * Шаг 2б: запросить OTP на email (для альтернативного входа).
     */
    public function sendEmailCode(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'email' => 'required|email',
        ]);

        $phone = preg_replace('/\D/', '', $request->phone);
        if (str_starts_with($phone, '8')) {
            $phone = '7' . substr($phone, 1);
        }

        $user = User::where('phone', $phone)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Пользователь не найден'], 422);
        }

        // Для существующих пользователей проверяем совпадение email
        if ($user->email && strtolower($user->email) !== strtolower($request->email)) {
            return response()->json(['success' => false, 'message' => 'Email не совпадает с зарегистрированным'], 422);
        }

        // Сохраняем email и OTP для этого шага
        $otp = '000000';
        $request->session()->put("email_otp_{$phone}", $otp);
        $request->session()->put("email_otp_{$phone}_email", $request->email);
        $request->session()->put("email_otp_{$phone}_expires", now()->addMinutes(10)->timestamp);

        // TODO: отправить реальное письмо с кодом, когда подключат email-сервис
        // Mail::to($request->email)->send(new OtpMail($otp));

        return response()->json(['success' => true]);
    }

    /**
     * Шаг 2б (финал): подтвердить email-код и залогинить.
     */
    public function verifyEmailCode(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $phone = preg_replace('/\D/', '', $request->phone);
        if (str_starts_with($phone, '8')) {
            $phone = '7' . substr($phone, 1);
        }

        $sessionOtp     = $request->session()->get("email_otp_{$phone}");
        $sessionEmail   = $request->session()->get("email_otp_{$phone}_email");
        $sessionExpires = $request->session()->get("email_otp_{$phone}_expires");

        if (
            $sessionOtp !== $request->code ||
            strtolower($sessionEmail) !== strtolower($request->email) ||
            !$sessionExpires || now()->timestamp > $sessionExpires
        ) {
            return response()->json(['success' => false, 'message' => 'Неверный или истёкший код'], 422);
        }

        $user = User::where('phone', $phone)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Пользователь не найден'], 422);
        }

        // Если email ещё не сохранён — сохраняем
        if (!$user->email) {
            $user->update(['email' => strtolower($request->email)]);
        }

        $request->session()->forget(["email_otp_{$phone}", "email_otp_{$phone}_email", "email_otp_{$phone}_expires"]);
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['success' => true, 'redirect' => route('profile')]);
    }

    /**
     * Шаг 2в: войти с паролем (для пользователей, установивших пароль).
     */
    public function loginWithPassword(Request $request): JsonResponse
    {
        $request->validate([
            'phone'    => 'required|string',
            'password' => 'required|string',
        ]);

        $phone = preg_replace('/\D/', '', $request->phone);
        if (str_starts_with($phone, '8')) {
            $phone = '7' . substr($phone, 1);
        }

        $user = User::where('phone', $phone)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Неверный пароль'], 422);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['success' => true, 'redirect' => route('profile')]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function checkOtp(Request $request, string $phone, string $code): bool
    {
        $storedOtp = $request->session()->get("phone_otp_{$phone}");
        $expires   = $request->session()->get("phone_otp_{$phone}_expires");

        return $storedOtp === $code && $expires && now()->timestamp <= $expires;
    }

    private function clearOtp(Request $request, string $phone): void
    {
        $request->session()->forget(["phone_otp_{$phone}", "phone_otp_{$phone}_expires"]);
    }
}
