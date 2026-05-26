<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;

class TestModeAccess
{
    public static function sessionKey(): string
    {
        return (string) config('test_mode.session_key', 'test_mode_access_granted_at');
    }

    public static function isEnabled(): bool
    {
        return (string) config('test_mode.password', '') !== '';
    }

    public static function ttlSeconds(): int
    {
        return max(1, (int) config('test_mode.ttl_minutes', 60)) * 60;
    }

    public static function isGranted(Session $session): bool
    {
        if (! self::isEnabled()) {
            return true;
        }

        $grantedAt = self::grantedAt($session);

        if ($grantedAt === null) {
            return false;
        }

        return (time() - $grantedAt) < self::ttlSeconds();
    }

    public static function grant(Session $session): void
    {
        $session->put(self::sessionKey(), time());
    }

    public static function grantedAt(Session $session): ?int
    {
        $value = $session->get(self::sessionKey());

        if ($value === true) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
