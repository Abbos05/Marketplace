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

    /*
    | Упрощённая разбивка комиссии платформы (для отчётов и PDF).
    | Считается по каждой позиции заказа, затем суммируется.
    | payment_fee + vat + platform_net = commission_total (с округлением).
    */
    'commission_split' => [
        'payment_fee_percent_of_commission' => (float) env('COMMISSION_PAYMENT_FEE_PERCENT_OF_COMMISSION', 0),
        'vat_percent_of_commission' => (float) env('COMMISSION_VAT_PERCENT', 20),
    ],

    /*
    | Внутренний артикул варианта: prefix + product_variants.id (только цифры).
    | Примеры при prefix=000: 0001, 00042, 0001234.
    */
    'article' => [
        'prefix' => env('MARKETPLACE_ARTICLE_PREFIX', '000'),
    ],

  'catalog_search_limit' => (int) env('MARKETPLACE_CATALOG_SEARCH_LIMIT', 10),

    /*
    | Лента на главной (без поиска): новинки, популярные, остальное — случайно.
    */
    'home_feed' => [
        'limit' => (int) env('MARKETPLACE_HOME_FEED_LIMIT', 50),
        'new_count' => (int) env('MARKETPLACE_HOME_FEED_NEW_COUNT', 6),
        'popular_count' => (int) env('MARKETPLACE_HOME_FEED_POPULAR_COUNT', 8),
        'recommendations_limit' => (int) env('MARKETPLACE_HOME_FEED_RECOMMENDATIONS_LIMIT', 12),
        'recommendations_popular_count' => (int) env('MARKETPLACE_HOME_FEED_RECOMMENDATIONS_POPULAR_COUNT', 6),
    ],

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
