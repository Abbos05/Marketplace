<?php

namespace App\Services;

use App\Mail\AuthOtpMail;
use App\Models\User;
use App\Support\NotificationCategory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionalMailService
{
    public function isEmailEnabled(): bool
    {
        return (bool) config('marketplace.notifications.email_enabled', true);
    }

    public function shouldEmailCategory(string $category): bool
    {
        if (! $this->isEmailEnabled()) {
            return false;
        }

        $categories = config('marketplace.notifications.categories', []);

        if (array_key_exists($category, $categories)) {
            return (bool) $categories[$category];
        }

        return (bool) ($categories[NotificationCategory::General] ?? true);
    }

    public function recipientFor(User $user): ?string
    {
        $override = config('marketplace.notifications.email_override');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        $email = $user->email;

        return is_string($email) && $email !== '' ? $email : null;
    }

    public function canSendTo(User $user, string $category): bool
    {
        return $this->shouldEmailCategory($category) && $this->recipientFor($user) !== null;
    }

    /**
     * OTP / коды подтверждения (вход, сброс пароля, привязка телефона).
     */
    public function sendOtp(User $user, string $code, string $purposeLabel, string $category): bool
    {
        if (! $this->canSendTo($user, $category)) {
            $this->logFallback($category, $user, [
                'purpose' => $purposeLabel,
                'code' => $code,
                'reason' => 'disabled_or_no_recipient',
            ]);

            return false;
        }

        return $this->send(
            $this->recipientFor($user),
            new AuthOtpMail($code, $purposeLabel),
            $category,
            $user,
            ['purpose' => $purposeLabel, 'code' => $code],
        );
    }

    public function sendOtpToAddress(string $email, string $code, string $purposeLabel, string $category, User $user): bool
    {
        if (! $this->shouldEmailCategory($category)) {
            $this->logFallback($category, $user, [
                'purpose' => $purposeLabel,
                'code' => $code,
                'to' => $email,
                'reason' => 'disabled',
            ]);

            return false;
        }

        $to = $this->recipientForAddress($email);

        return $this->send(
            $to,
            new AuthOtpMail($code, $purposeLabel),
            $category,
            $user,
            ['purpose' => $purposeLabel, 'code' => $code, 'to' => $email],
        );
    }

    private function recipientForAddress(string $email): string
    {
        $override = config('marketplace.notifications.email_override');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        return $email;
    }

    public function sendAlert(User $user, string $title, string $body, ?string $actionUrl, string $category): bool
    {
        if (! $this->canSendTo($user, $category)) {
            $this->logFallback($category, $user, [
                'title' => $title,
                'body' => $body,
                'reason' => 'disabled_or_no_recipient',
            ]);

            return false;
        }

        return $this->send(
            $this->recipientFor($user),
            new \App\Mail\MarketplaceAlertMail($title, $body, $actionUrl),
            $category,
            $user,
            ['title' => $title],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function send(string $to, object $mailable, string $category, User $user, array $context): bool
    {
        try {
            Mail::to($to)->send($mailable);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Transactional email failed', [
                'category' => $category,
                'user_id' => $user->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            $this->logFallback($category, $user, $context + ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logFallback(string $category, User $user, array $context): void
    {
        if (! config('marketplace.notifications.log_on_failure', true)) {
            return;
        }

        Log::info('Transactional email fallback (not sent or failed)', [
            'category' => $category,
            'user_id' => $user->id,
            'email' => $user->email,
            'override' => config('marketplace.notifications.email_override'),
            ...$context,
        ]);
    }
}
