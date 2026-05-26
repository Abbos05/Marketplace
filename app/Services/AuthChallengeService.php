<?php

namespace App\Services;

use App\Models\LoginChallenge;
use App\Models\User;
use App\Notifications\MarketplaceAlert;
use App\Support\NotificationCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthChallengeService
{
    private const SESSION_PHONE = 'phone_auth.phone';

    private const SESSION_CHALLENGE = 'phone_auth.challenge_id';

    private const SESSION_COOLDOWN_UNTIL = 'phone_auth.cooldown_until';

    private const SESSION_SEND_STRIKES = 'phone_auth.send_strikes';

    public function __construct(
        private readonly OtpCodeGenerator $otp,
        private readonly TransactionalMailService $mail,
    ) {}

    public function getCooldownRemaining(Request $request): int
    {
        $until = (int) $request->session()->get(self::SESSION_COOLDOWN_UNTIL, 0);

        return max(0, $until - time());
    }

    public function clearPhoneAuthSession(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_PHONE,
            self::SESSION_CHALLENGE,
            self::SESSION_COOLDOWN_UNTIL,
            self::SESSION_SEND_STRIKES,
        ]);
    }

    /**
     * @return array{challenge: LoginChallenge, reused: bool, cooldown_seconds: int, code_sent: bool}|array{error: true, message: string, cooldown_seconds: int}
     */
    public function sendLoginCode(Request $request, User $user, string $phone, bool $forceResend = false): array
    {
        $remaining = $this->getCooldownRemaining($request);
        $sessionPhone = $request->session()->get(self::SESSION_PHONE);
        $challengeId = $request->session()->get(self::SESSION_CHALLENGE);

        if ($forceResend && $remaining > 0) {
            return [
                'error' => true,
                'message' => "Подождите {$remaining} с перед повторной отправкой",
                'cooldown_seconds' => $remaining,
            ];
        }

        if (! $forceResend && $sessionPhone === $phone && $challengeId && $remaining > 0) {
            $challenge = LoginChallenge::find($challengeId);
            if (
                $challenge
                && ! $challenge->isExpired()
                && $challenge->phone === $phone
                && $challenge->purpose === LoginChallenge::PURPOSE_LOGIN
            ) {
                return [
                    'challenge' => $challenge,
                    'reused' => true,
                    'cooldown_seconds' => $remaining,
                    'code_sent' => false,
                ];
            }
        }

        $strikes = $this->incrementSendStrike($request);
        $cooldownSec = $this->cooldownSecondsForStrike($strikes);
        $result = $this->createLoginChallenge($user, $phone);

        $request->session()->put([
            self::SESSION_PHONE => $phone,
            self::SESSION_CHALLENGE => $result['challenge']->id,
        ]);
        $this->applyCooldown($request, $cooldownSec);

        return [
            'challenge' => $result['challenge'],
            'reused' => false,
            'cooldown_seconds' => $cooldownSec,
            'code_sent' => true,
        ];
    }

    /**
     * @return array{success: true, cooldown_seconds: int}|array{error: true, message: string, cooldown_seconds: int}
     */
    public function resendSmsWithCooldown(Request $request, LoginChallenge $challenge): array
    {
        $remaining = $this->getCooldownRemaining($request);
        if ($remaining > 0) {
            return [
                'error' => true,
                'message' => "Подождите {$remaining} с перед повторной отправкой",
                'cooldown_seconds' => $remaining,
            ];
        }

        $this->resendViaSms($challenge);

        $strikes = $this->incrementSendStrike($request);
        $cooldownSec = $this->cooldownSecondsForStrike($strikes);
        $this->applyCooldown($request, $cooldownSec);

        return [
            'success' => true,
            'cooldown_seconds' => $cooldownSec,
        ];
    }

    public function normalizePhone(string $raw): string
    {
        $phone = preg_replace('/\D/', '', $raw);
        if (str_starts_with($phone, '8')) {
            $phone = '7' . substr($phone, 1);
        }

        return $phone;
    }

    public function maskPhone(string $phone): string
    {
        if (strlen($phone) < 11) {
            return $phone;
        }

        return '+7 *** *** ' . substr($phone, -4);
    }

    /**
     * @return array{challenge: LoginChallenge, code: string}
     */
    public function createLoginChallenge(User $user, string $phone, ?string $forceChannel = null): array
    {
        $this->invalidateActiveChallenges($user, LoginChallenge::PURPOSE_LOGIN);

        $channel = $forceChannel ?? $this->detectDeliveryChannel($user);
        $code = $channel === LoginChallenge::CHANNEL_NOTIFICATION
            ? $this->otp->real()
            : $this->otp->forSms();
        $ttl = (int) config('marketplace.auth.otp_ttl_minutes', 10);

        $challenge = LoginChallenge::create([
            'user_id' => $user->id,
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'channel' => $channel,
            'purpose' => LoginChallenge::PURPOSE_LOGIN,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->deliverCode($user, $phone, $code, $channel);

        return ['challenge' => $challenge, 'code' => $code];
    }

    public function detectDeliveryChannel(User $user): string
    {
        $thresholdMinutes = (int) config('marketplace.auth.active_session_threshold_minutes', 30);
        $cutoff = now()->subMinutes($thresholdMinutes)->timestamp;

        $hasActiveSession = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', $cutoff)
            ->exists();

        return $hasActiveSession
            ? LoginChallenge::CHANNEL_NOTIFICATION
            : LoginChallenge::CHANNEL_SMS;
    }

    public function resendViaSms(LoginChallenge $challenge): void
    {
        if ($challenge->purpose !== LoginChallenge::PURPOSE_LOGIN) {
            throw new \InvalidArgumentException('SMS resend only for login challenges');
        }

        $code = $this->otp->forSms();
        $ttl = (int) config('marketplace.auth.otp_ttl_minutes', 10);

        $challenge->update([
            'code_hash' => Hash::make($code),
            'channel' => LoginChallenge::CHANNEL_SMS,
            'attempts' => 0,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->deliverViaSms($challenge->user, $challenge->phone, $code);
    }

    /**
     * @return array{success: bool, message?: string, challenge?: LoginChallenge}
     */
    public function verifyCode(string $challengeId, string $code): array
    {
        $challenge = LoginChallenge::find($challengeId);

        if (! $challenge || $challenge->purpose !== LoginChallenge::PURPOSE_LOGIN) {
            return ['success' => false, 'message' => 'Сессия входа не найдена. Запросите код снова.'];
        }

        if ($challenge->isExpired()) {
            $challenge->delete();

            return ['success' => false, 'message' => 'Код истёк. Запросите новый.'];
        }

        $maxAttempts = (int) config('marketplace.auth.otp_max_attempts', 5);
        if ($challenge->attempts >= $maxAttempts) {
            $challenge->delete();

            return ['success' => false, 'message' => 'Превышено число попыток. Запросите новый код.'];
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');

            return ['success' => false, 'message' => 'Неверный код'];
        }

        $challenge->update(['phone_verified_at' => now()]);

        return ['success' => true, 'challenge' => $challenge];
    }

    /**
     * @return array{success: bool, message?: string, redirect?: string}
     */
    public function completeLogin(Request $request, LoginChallenge $challenge, ?string $password = null): array
    {
        if ($challenge->purpose !== LoginChallenge::PURPOSE_LOGIN) {
            return ['success' => false, 'message' => 'Недопустимая сессия входа'];
        }

        if (! $challenge->isPhoneVerified()) {
            return ['success' => false, 'message' => 'Сначала подтвердите код из SMS или уведомлений'];
        }

        if ($challenge->isExpired()) {
            $challenge->delete();

            return ['success' => false, 'message' => 'Сессия входа истекла. Запросите код снова.'];
        }

        $user = $challenge->user;

        if (! $user->newPassw) {
            if (! $password) {
                return ['success' => false, 'message' => 'Введите пароль', 'requires_password' => true];
            }

            if (! Hash::check($password, $user->password)) {
                return ['success' => false, 'message' => 'Неверный пароль'];
            }
        }

        $challenge->delete();
        $this->clearPhoneAuthSession($request);
        Auth::login($user);
        $request->session()->regenerate();

        $method = $challenge->channel === LoginChallenge::CHANNEL_NOTIFICATION
            ? 'phone_otp_notification'
            : 'phone_otp';

        if (! $user->newPassw) {
            $method = 'phone_otp_2fa';
        }

        app(LoginHistoryRecorder::class)->record($request, $user, $method);

        return ['success' => true, 'redirect' => route('profile')];
    }

    /**
     * Сброс пароля не меняет purpose=login, чтобы можно было вернуться к вводу пароля.
     *
     * @return array{challenge: LoginChallenge, delivery_method: string}
     */
    public function createPasswordResetChallenge(LoginChallenge $loginChallenge): array
    {
        if (! $loginChallenge->isPhoneVerified() || $loginChallenge->purpose !== LoginChallenge::PURPOSE_LOGIN) {
            throw new \InvalidArgumentException('Login challenge not ready for password reset');
        }

        $user = $loginChallenge->user;
        $code = $this->otp->forEmail();
        $ttl = (int) config('marketplace.auth.otp_ttl_minutes', 10);

        $loginChallenge->update([
            'reset_code_hash' => Hash::make($code),
            'reset_channel' => 'email',
            'reset_verified_at' => null,
            'reset_attempts' => 0,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $deliveryMethod = $this->deliverPasswordResetCode($user, $code);

        return [
            'challenge' => $loginChallenge->fresh(),
            'delivery_method' => $deliveryMethod,
        ];
    }

    public function cancelPasswordReset(LoginChallenge $challenge): void
    {
        if ($challenge->purpose !== LoginChallenge::PURPOSE_LOGIN) {
            return;
        }

        $challenge->update([
            'reset_code_hash' => null,
            'reset_channel' => null,
            'reset_verified_at' => null,
            'reset_attempts' => 0,
        ]);
    }

    public function supportEmail(): string
    {
        return (string) config('marketplace.support.email', 'support@alvora.ru');
    }

    /**
     * @return array{success: bool, message?: string, challenge?: LoginChallenge}
     */
    public function verifyPasswordResetCode(string $challengeId, string $code): array
    {
        $challenge = LoginChallenge::find($challengeId);

        if (! $challenge || $challenge->purpose !== LoginChallenge::PURPOSE_LOGIN || ! $challenge->reset_code_hash) {
            return ['success' => false, 'message' => 'Запросите код сброса пароля снова'];
        }

        if (! $challenge->isPhoneVerified()) {
            return ['success' => false, 'message' => 'Сначала подтвердите вход по телефону'];
        }

        if ($challenge->isExpired()) {
            $challenge->delete();

            return ['success' => false, 'message' => 'Код истёк'];
        }

        $maxAttempts = (int) config('marketplace.auth.otp_max_attempts', 5);
        if ($challenge->reset_attempts >= $maxAttempts) {
            $this->cancelPasswordReset($challenge);

            return ['success' => false, 'message' => 'Превышено число попыток. Запросите код снова.'];
        }

        if (! Hash::check($code, $challenge->reset_code_hash)) {
            $challenge->increment('reset_attempts');

            return ['success' => false, 'message' => 'Неверный код'];
        }

        $challenge->update(['reset_verified_at' => now()]);

        return ['success' => true, 'challenge' => $challenge];
    }

    /**
     * @return array{success: bool, message?: string, redirect?: string}
     */
    public function resetPasswordAndLogin(
        Request $request,
        LoginChallenge $challenge,
        string $password,
    ): array {
        if ($challenge->purpose !== LoginChallenge::PURPOSE_LOGIN || ! $challenge->isResetVerified()) {
            return ['success' => false, 'message' => 'Сначала подтвердите код для сброса пароля'];
        }

        if ($challenge->isExpired()) {
            $challenge->delete();

            return ['success' => false, 'message' => 'Сессия истекла'];
        }

        $user = $challenge->user;
        $user->update([
            'password' => Hash::make($password),
            'newPassw' => false,
        ]);

        $user->notify(new MarketplaceAlert(
            'Пароль изменён',
            'Пароль вашего аккаунта был успешно обновлён. Если это были не вы, немедленно свяжитесь с поддержкой.',
            null,
            NotificationCategory::Security,
        ));

        $challenge->delete();
        $this->clearPhoneAuthSession($request);
        Auth::login($user);
        $request->session()->regenerate();
        app(LoginHistoryRecorder::class)->record($request, $user, 'phone_password_reset');

        return ['success' => true, 'redirect' => route('profile')];
    }

    private function cooldownSecondsForStrike(int $strikes): int
    {
        $base = (int) config('marketplace.auth.resend_cooldown_seconds', 60);
        if ($strikes <= 1) {
            return $base;
        }
        if ($strikes <= 3) {
            return $base + 60;
        }

        return $base + 120;
    }

    private function applyCooldown(Request $request, int $seconds): void
    {
        $request->session()->put(self::SESSION_COOLDOWN_UNTIL, time() + $seconds);
    }

    private function incrementSendStrike(Request $request): int
    {
        $strikes = (int) $request->session()->get(self::SESSION_SEND_STRIKES, 0) + 1;
        $request->session()->put(self::SESSION_SEND_STRIKES, $strikes);

        return $strikes;
    }

    public function findChallenge(string $challengeId): ?LoginChallenge
    {
        return LoginChallenge::find($challengeId);
    }

    private function invalidateActiveChallenges(User $user, string $purpose): void
    {
        LoginChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('expires_at', '>', now())
            ->delete();
    }

    private function deliverCode(User $user, string $phone, string $code, string $channel): void
    {
        if ($channel === LoginChallenge::CHANNEL_NOTIFICATION) {
            $this->deliverViaNotification($user, $code);
        } else {
            $this->deliverViaSms($user, $phone, $code);
        }
    }

    private function deliverViaNotification(User $user, string $code): void
    {
        $user->notify(new MarketplaceAlert(
            'Код для входа',
            "Код подтверждения: {$code}. Проверьте уведомления на другом устройстве, где вы уже вошли в аккаунт.",
            null,
            NotificationCategory::AuthLoginOtp,
        ));
    }

    private function deliverViaSms(User $user, string $phone, string $code): void
    {
        if (app()->environment('local', 'testing')) {
            Log::info('Auth SMS OTP', ['phone' => $this->maskPhone($phone), 'code' => $code]);
        }
        // TODO: подключить SMS-провайдер

        $this->mail->sendOtp($user, $code, 'входа', NotificationCategory::AuthLoginSms);
    }

    /**
     * @return 'test'|'email'
     */
    private function deliverPasswordResetCode(User $user, string $code): string
    {
        $sent = $this->mail->sendOtp($user, $code, 'сброса пароля', NotificationCategory::AuthPasswordReset);

        return $sent ? 'email' : 'test';
    }
}
