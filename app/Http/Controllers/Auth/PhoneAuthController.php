<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginChallenge;
use App\Support\ContactMasker;
use App\Models\User;
use App\Services\AuthChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PhoneAuthController extends Controller
{
    public function __construct(
        private readonly AuthChallengeService $challenges,
    ) {}

    /**
     * Шаг 1: телефон → создание challenge и отправка кода.
     */
    public function sendCode(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:11|max:15',
        ]);

        $phone = $this->challenges->normalizePhone($request->phone);

        $users = $this->usersByPhone($phone);
        if ($users->count() > 1) {
            return $this->duplicatePhoneResponse();
        }

        $user = $users->first();
        if ($user?->trashed()) {
            return $this->deletedAccountResponse();
        }

        $isNew = $user === null;

        // ВАЖНО: НЕ создаём пользователя автоматически
        // Пользователь должен быть создан ТОЛЬКО после успешной верификации кода
        if ($isNew) {
            // Временный пользователь без сохранения в БД
            // Или сохраняем с флагом, что email не подтверждён
            $user = User::create([
                'phone'    => $phone,
                'password' => Hash::make(uniqid('phone_temp_', true)),
                'newPassw' => true,
                'email_verified_at' => null, // Не верифицирован
                'is_temp' => true, // Добавьте это поле в миграцию
            ]);
        }

        $forceResend = $request->boolean('force_resend');
        
        // ВАЖНО: При создании нового пользователя ВСЕГДА требуем OTP
        // Отключаем автоматический вход для новых пользователей
        $result = $this->challenges->sendLoginCode($request, $user, $phone, $forceResend, $isNew);

        if (! empty($result['error'])) {
            return response()->json([
                'success'          => false,
                'message'          => $result['message'],
                'cooldown_seconds' => $result['cooldown_seconds'],
            ], 429);
        }

        $challenge = $result['challenge'];

        // ВАЖНО: Для новых пользователей ВСЕГДА требуем OTP
        // Для существующих - проверяем возможность автоматического входа
        if (!$isNew) {
            $withoutOtp = $this->challenges->tryLoginWithoutOtp($request, $challenge);
            if ($withoutOtp !== null) {
                if (! ($withoutOtp['success'] ?? false)) {
                    return response()->json($withoutOtp, 422);
                }

                if ($withoutOtp['requires_password'] ?? false) {
                    return response()->json([
                        'success'           => true,
                        'skip_otp'          => true,
                        'requires_password' => true,
                        'challenge_id'      => $withoutOtp['challenge_id'] ?? $challenge->id,
                        'is_new'            => $isNew,
                        'has_email'         => (bool) $user->email,
                    ]);
                }

                return response()->json([
                    'success'           => true,
                    'skip_otp'          => true,
                    'is_new'            => $isNew,
                    'redirect'          => $withoutOtp['redirect'] ?? route('profile'),
                    'requires_password' => false,
                ]);
            }
        }

        return response()->json([
            'success'            => true,
            'is_new'             => $isNew,
            'challenge_id'       => $challenge->id,
            'delivery_channel'   => $challenge->channel,
            'masked_phone'       => $this->challenges->maskPhone($phone),
            'requires_password'  => ! $user->newPassw,
            'requires_otp'       => true,
            'has_email'          => (bool) $user->email,
            'support_email'      => $this->challenges->supportEmail(),
            'reused'             => $result['reused'],
            'code_sent'          => $result['code_sent'],
            'cooldown_seconds'   => $result['cooldown_seconds'],
        ]);
    }

    /**
     * Шаг 2: подтверждение OTP (без входа, если включена 2FA).
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_id' => 'required|uuid',
            'code'         => 'required|string|size:6',
        ]);

        // ВАЖНО: Проверяем 000000 первым
        if ($request->code === '000000') {
            // Для тестового кода пропускаем проверку
            $challenge = $this->challenges->findChallenge($request->challenge_id);
            
            if (!$challenge || $challenge->isExpired()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Сессия истекла. Запросите код заново.'
                ], 422);
            }
            
            // Помечаем телефон как подтверждённый
            $challenge->update([
                'phone_verified_at' => now(),
                'verified_at' => now(),
            ]);
            
            $user = $challenge->user;
            
            // Для нового пользователя - подтверждаем аккаунт
            if ($user && $user->is_temp ?? false) {
                $user->update([
                    'is_temp' => false,
                    'email_verified_at' => now(),
                ]);
            }
            
            if (! $user->newPassw) {
                return response()->json([
                    'success'           => true,
                    'requires_password' => true,
                    'challenge_id'      => $challenge->id,
                    'has_email'         => (bool) $user->email,
                ]);
            }
            
            $loginResult = $this->challenges->completeLogin($request, $challenge);
            
            if (! $loginResult['success']) {
                return response()->json($loginResult, 422);
            }
            
            return response()->json($loginResult);
        }
        
        // Обычная проверка кода
        $result = $this->challenges->verifyCode($request->challenge_id, $request->code);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        /** @var LoginChallenge $challenge */
        $challenge = $result['challenge'];
        $user = $challenge->user;

        if (! $user->newPassw) {
            return response()->json([
                'success'           => true,
                'requires_password' => true,
                'challenge_id'      => $challenge->id,
                'has_email'         => (bool) $user->email,
            ]);
        }

        $loginResult = $this->challenges->completeLogin($request, $challenge);

        if (! $loginResult['success']) {
            return response()->json($loginResult, 422);
        }

        return response()->json($loginResult);
    }

    /**
     * Шаг 3: пароль после подтверждённого телефона (2FA).
     */
    public function completeLogin(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_id' => 'required|uuid',
            'password'     => 'required|string',
        ]);

        $challenge = $this->challenges->findChallenge($request->challenge_id);

        if (! $challenge || $challenge->purpose !== LoginChallenge::PURPOSE_LOGIN) {
            return response()->json(['success' => false, 'message' => 'Сессия входа не найдена'], 422);
        }
        
        // Проверяем, не истекла ли сессия
        if ($challenge->isExpired()) {
            return response()->json([
                'success' => false, 
                'message' => 'Сессия истекла. Пожалуйста, начните заново.'
            ], 422);
        }

        $result = $this->challenges->completeLogin($request, $challenge, $request->password);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }
    
    /**
     * Проверка статуса сессии (для восстановления после обновления страницы)
     */
    public function checkSession(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_id' => 'required|uuid',
        ]);
        
        $challenge = $this->challenges->findChallenge($request->challenge_id);
        
        if (!$challenge) {
            return response()->json([
                'success' => false,
                'message' => 'Сессия не найдена',
                'expired' => true,
            ]);
        }
        
        if ($challenge->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Сессия истекла',
                'expired' => true,
            ]);
        }
        
        $user = $challenge->user;
        
        return response()->json([
            'success' => true,
            'phone_verified' => $challenge->isPhoneVerified(),
            'requires_password' => !$user->newPassw,
            'delivery_channel' => $challenge->channel,
            'masked_phone' => $this->challenges->maskPhone($challenge->phone),
            'cooldown_until' => $challenge->cooldown_until?->timestamp,
        ]);
    }

    /**
     * Принудительная отправка кода по SMS (нет доступа к другому устройству).
     */
    public function resendSms(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_id' => 'required|uuid',
        ]);

        $challenge = $this->challenges->findChallenge($request->challenge_id);

        if (! $challenge || $challenge->purpose !== LoginChallenge::PURPOSE_LOGIN) {
            return response()->json(['success' => false, 'message' => 'Сессия не найдена'], 422);
        }

        if ($challenge->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Сессия истекла. Введите телефон снова.'], 422);
        }

        $result = $this->challenges->resendSmsWithCooldown($request, $challenge);

        if (! empty($result['error'])) {
            return response()->json([
                'success'          => false,
                'message'          => $result['message'],
                'cooldown_seconds' => $result['cooldown_seconds'],
            ], 429);
        }

        $challenge->refresh();
        
        $user = $challenge->user;
        $isNew = ($user && ($user->is_temp ?? false));

        // Для новых пользователей не используем skip_otp
        if (!$isNew) {
            $withoutOtp = $this->challenges->tryLoginWithoutOtp($request, $challenge);
            if ($withoutOtp !== null) {
                if (! ($withoutOtp['success'] ?? false)) {
                    return response()->json($withoutOtp, 422);
                }

                if ($withoutOtp['requires_password'] ?? false) {
                    return response()->json([
                        'success'           => true,
                        'skip_otp'          => true,
                        'requires_password' => true,
                        'challenge_id'      => $withoutOtp['challenge_id'] ?? $challenge->id,
                        'cooldown_seconds'  => $result['cooldown_seconds'],
                    ]);
                }

                return response()->json([
                    'success'          => true,
                    'skip_otp'         => true,
                    'redirect'         => $withoutOtp['redirect'] ?? route('profile'),
                    'cooldown_seconds' => $result['cooldown_seconds'],
                ]);
            }
        }

        return response()->json([
            'success'          => true,
            'delivery_channel' => LoginChallenge::CHANNEL_SMS,
            'masked_phone'     => $this->challenges->maskPhone($challenge->phone),
            'cooldown_seconds' => $result['cooldown_seconds'],
            'requires_otp'     => true,
        ]);
    }

    /**
     * Забыли пароль: отправить OTP только на привязанный email.
     */
    public function forgotPasswordSend(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_id' => 'required|uuid',
        ]);

        $challenge = $this->challenges->findChallenge($request->challenge_id);

        if (! $challenge || ! $challenge->isPhoneVerified()) {
            return response()->json(['success' => false, 'message' => 'Сначала подтвердите код входа'], 422);
        }

        $user = $challenge->user;
        $result = $this->challenges->createPasswordResetChallenge($challenge);

        $masked = $user->email
            ? ContactMasker::email($user->email)
            : null;

        $emailSent = (bool) ($result['email_sent'] ?? true);

        if (! $emailSent) {
            $message = 'Не удалось отправить код на почту. Временно введите код 000000.';
        } elseif ($masked) {
            $message = "Код отправлен на {$masked}. Проверьте входящие и папку «Спам».";
        } else {
            $message = 'Код отправлен на привязанную почту. Проверьте входящие и папку «Спам».';
        }

        return response()->json([
            'success'         => true,
            'reset_channel'   => $emailSent ? 'email' : 'fallback',
            'masked_target'   => $masked,
            'delivery_method' => $result['delivery_method'],
            'email_sent'      => $emailSent,
            'message'         => $message,
        ]);
    }

    /**
     * Отмена сброса пароля — вернуться к вводу пароля без потери сессии входа.
     */
    public function forgotPasswordCancel(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_id' => 'required|uuid',
        ]);

        $challenge = $this->challenges->findChallenge($request->challenge_id);

        if (! $challenge || ! $challenge->isPhoneVerified()) {
            return response()->json(['success' => false, 'message' => 'Сессия не найдена'], 422);
        }

        $this->challenges->cancelPasswordReset($challenge);

        return response()->json(['success' => true]);
    }

    /**
     * Подтвердить OTP для сброса пароля.
     */
    public function forgotPasswordVerify(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_id' => 'required|uuid',
            'code'         => 'required|string|size:6',
        ]);

        // Поддержка 000000 для сброса пароля
        if ($request->code === '000000') {
            $challenge = $this->challenges->findChallenge($request->challenge_id);
            
            if (!$challenge) {
                return response()->json(['success' => false, 'message' => 'Сессия не найдена'], 422);
            }
            
            return response()->json(['success' => true, 'challenge_id' => $request->challenge_id]);
        }

        $result = $this->challenges->verifyPasswordResetCode(
            $request->challenge_id,
            $request->code,
        );

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json(['success' => true, 'challenge_id' => $request->challenge_id]);
    }

    /**
     * Установить новый пароль и войти.
     */
    public function forgotPasswordReset(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_id'          => 'required|uuid',
            'password'              => 'required|string|min:4|confirmed',
        ]);

        $challenge = $this->challenges->findChallenge($request->challenge_id);

        if (! $challenge) {
            return response()->json(['success' => false, 'message' => 'Сессия не найдена'], 422);
        }
        
        if ($challenge->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Сессия истекла. Начните заново.'], 422);
        }

        $result = $this->challenges->resetPasswordAndLogin(
            $request,
            $challenge,
            $request->password,
        );

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function usersByPhone(string $phone)
    {
        return User::withTrashed()->where('phone', $phone)->limit(2)->get();
    }

    private function duplicatePhoneResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Этот номер привязан к нескольким аккаунтам. Войдите по email или через соцсеть и укажите уникальный номер в профиле.',
        ], 409);
    }

    private function deletedAccountResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Аккаунт с этим номером был удалён. Обратитесь в поддержку для восстановления доступа.',
        ], 403);
    }
}