<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginChallenge;
use App\Models\User;
use App\Services\AuthChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

        if ($isNew) {
            $user = User::create([
                'phone'    => $phone,
                'password' => Hash::make(uniqid('phone_', true)),
                'newPassw' => true,
            ]);
        }

        $forceResend = $request->boolean('force_resend');
        $result = $this->challenges->sendLoginCode($request, $user, $phone, $forceResend);

        if (! empty($result['error'])) {
            return response()->json([
                'success'          => false,
                'message'          => $result['message'],
                'cooldown_seconds' => $result['cooldown_seconds'],
            ], 429);
        }

        $challenge = $result['challenge'];

        return response()->json([
            'success'            => true,
            'is_new'             => $isNew,
            'challenge_id'       => $challenge->id,
            'delivery_channel'   => $challenge->channel,
            'masked_phone'       => $this->challenges->maskPhone($phone),
            'requires_password'  => ! $user->newPassw,
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

        $result = $this->challenges->completeLogin($request, $challenge, $request->password);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
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

        return response()->json([
            'success'          => true,
            'delivery_channel' => LoginChallenge::CHANNEL_SMS,
            'masked_phone'     => $this->challenges->maskPhone($challenge->phone),
            'cooldown_seconds' => $result['cooldown_seconds'],
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
            ? $this->maskEmail($user->email)
            : null;

        return response()->json([
            'success'         => true,
            'reset_channel'   => 'email',
            'masked_target'   => $masked,
            'delivery_method' => $result['delivery_method'],
            'test_mode'       => $result['delivery_method'] === 'test',
            'message'         => 'Код отправлен на почту. Тестовый код: 000000',
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

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $masked = strlen($local) > 2
            ? substr($local, 0, 2) . '***'
            : '***';

        return $masked . '@' . $parts[1];
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
