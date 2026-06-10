<?php

namespace App\Support;

class ContactMasker
{
    public static function email(string $email): string
    {
        // Разделяем email на локальную часть и домен
        $parts = explode('@', $email);

        if (count($parts) !== 2) {
            // Если email некорректный, возвращаем как есть
            return $email;
        }

        $localPart = $parts[0];
        $domain = $parts[1];

        // Если локальная часть слишком короткая (1–2 символа), маскировать нечего
        if (strlen($localPart) <= 2) {
            return $localPart . '@' . $domain;
        }

        // Сохраняем первый и последний символ, остальное заменяем на ***
        $maskedLocal = $localPart[0] . '***' . $localPart[strlen($localPart) - 1];

        return $maskedLocal . '@' . $domain;
    }
}
