<?php

return [

    /*
    | Демо-режим платежей и возвратов (диплом / локальная разработка).
    | true — возврат через страницу-подтверждение без реального Stripe Refund API.
    | false — реальный возврат через Stripe (нужен payment_intent в таблице payments).
    */
    'demo_payments' => filter_var(
        env('APP_DEMO_PAYMENTS', env('APP_ENV') === 'local'),
        FILTER_VALIDATE_BOOL
    ),

    'footer' => [
        'social' => [
            [
                'label' => 'VK',
                'url' => env('FOOTER_VK_URL', 'https://vk.com/id_a_i_09_05_i_a'),
            ],
            [
                'label' => 'Telegram-канал',
                'url' => env('FOOTER_TELEGRAM_URL', 'https://t.me/AlvoraPlace'),
            ],
            [
                'label' => 'MAX',
                'url' => env('FOOTER_MAX_URL', 'https://max.ru/join/uTTd84ZCWV6LDqeiR1KOFZnBPp-2ar4mgwWMtSsmfmQ'),
            ],
            [
                'label' => 'Instagram',
                'url' => env('FOOTER_INSTAGRAM_URL', 'https://www.instagram.com/id_a_l_00_05_l_a/'),
            ],
        ],
    ],

];
