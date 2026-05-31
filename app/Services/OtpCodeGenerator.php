<?php

namespace App\Services;

class OtpCodeGenerator
{
    /** SMS-заглушка для локальной разработки (000000). */
    public function forSms(): string
    {
        $devOtp = config('marketplace.auth.dev_otp');

        if ($devOtp !== null && $devOtp !== '' && app()->environment('local', 'testing')) {
            return (string) $devOtp;
        }

        return $this->random();
    }

    /** Реальный код: уведомления, профиль. */
    public function real(): string
    {
        return $this->random();
    }

    /** Код на почту — всегда случайный (не dev OTP). */
    public function forEmail(): string
    {
        return $this->random();
    }

    /** Fallback, если письмо не ушло (временно 000000). */
    public function fallback(): string
    {
        $devOtp = config('marketplace.auth.dev_otp');

        if ($devOtp !== null && $devOtp !== '') {
            return (string) $devOtp;
        }

        return '000000';
    }

    private function random(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
