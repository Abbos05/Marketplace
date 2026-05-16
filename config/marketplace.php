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
                'url' => env('FOOTER_VK_URL', 'https://vk.com'),
            ],
            [
                'label' => 'Telegram',
                'url' => env('FOOTER_TELEGRAM_URL', 'https://t.me'),
            ],
        ],
    ],

];
