<?php

namespace App\Support;

class MailBrandLogo
{
    public static function path(): string
    {
        $relative = (string) config('marketplace.mail.logo_path', 'icons/icon-192.png');

        return public_path($relative);
    }

    public static function exists(): bool
    {
        return is_file(self::path());
    }
}
