<?php

namespace App\Support;

class ContactMasker
{
    /**
     * Скрывает только середину локальной части; домен показывается полностью.
     * admin@gmail.com → a***n@gmail.com, ivan.petrov@gmail.com → iv***ov@gmail.com
     */
    public static function email(string $email): string
    {
        $email = trim($email);
        $parts = explode('@', $email, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return '***@***';
        }

        [$local, $domain] = $parts;
        $len = strlen($local);

        if ($len === 1) {
            $maskedLocal = '*';
        } elseif ($len === 2) {
            $maskedLocal = $local[0] . '*';
        } elseif ($len <= 4) {
            $maskedLocal = substr($local, 0, 1) . '***' . substr($local, -1);
        } else {
            $maskedLocal = substr($local, 0, 2) . '***' . substr($local, -2);
        }

        return $maskedLocal . '@' . $domain;
    }
}
