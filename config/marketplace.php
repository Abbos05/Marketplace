<?php

return [

    /*
    | Помечать оплаты из сидов/имитации как provider=demo (без Stripe Checkout).
    | Возвраты при оплате через Stripe всегда идут через Stripe Refund API (в т.ч. sk_test_).
    */
    'demo_payments' => filter_var(
        env('APP_DEMO_PAYMENTS', false),
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

    'catalog_per_page' => (int) env('MARKETPLACE_CATALOG_PER_PAGE', 24),

    /** @deprecated Use catalog_per_page pagination instead */
    'catalog_search_limit' => (int) env('MARKETPLACE_CATALOG_SEARCH_LIMIT', 10),

    /*
    | Лента на главной (без поиска): новинки, популярные, остальное — случайно.
    */
    'home_feed' => [
        'limit' => (int) env('MARKETPLACE_HOME_FEED_LIMIT', 10000),
        'new_count' => (int) env('MARKETPLACE_HOME_FEED_NEW_COUNT', 6),
        'popular_count' => (int) env('MARKETPLACE_HOME_FEED_POPULAR_COUNT', 8),
        'recommendations_limit' => (int) env('MARKETPLACE_HOME_FEED_RECOMMENDATIONS_LIMIT', 12),
        'recommendations_popular_count' => (int) env('MARKETPLACE_HOME_FEED_RECOMMENDATIONS_POPULAR_COUNT', 6),
    ],

    /*
    | Вознаграждение оператора ПВЗ за выданный заказ: процент от суммы, с потолком.
    */
    'pvz_fee' => [
        'percent_of_order' => (float) env('PVZ_FEE_PERCENT', 3),
        'max_per_order' => (float) env('PVZ_FEE_MAX', 50),
    ],

    /*
    | Телефонная авторизация: OTP, кросс-девайс, 2FA.
    */
    'auth' => [
        'otp_ttl_minutes' => (int) env('AUTH_OTP_TTL_MINUTES', 10),
        'otp_max_attempts' => (int) env('AUTH_OTP_MAX_ATTEMPTS', 5),
        'active_session_threshold_minutes' => (int) env('AUTH_ACTIVE_SESSION_THRESHOLD_MINUTES', 30),
        'dev_otp' => env('AUTH_DEV_OTP', '000000'),
        'resend_cooldown_seconds' => (int) env('AUTH_RESEND_COOLDOWN_SECONDS', 60),
        // Имя провайдера (sms.ru, twilio, …). Пока пусто — SMS не уходит, вход по телефону без кода (кроме 2FA и уведомлений).
        'sms_provider' => env('AUTH_SMS_PROVIDER'),
        // Принудительно требовать OTP при входе по SMS даже без провайдера (для отладки).
        'sms_login_otp_required' => filter_var(env('AUTH_SMS_LOGIN_OTP_REQUIRED', false), FILTER_VALIDATE_BOOL),
    ],

    'support' => [
        'email' => env('MARKETPLACE_SUPPORT_EMAIL', 'support@alvoraplace.ru'),
    ],

    /*
    | Дублирование уведомлений (колокольчик) на email.
    | Поставьте NOTIFICATION_EMAIL_ENABLED=false, чтобы отключить все письма.
    | Каждую категорию можно выключить отдельно (NOTIFY_EMAIL_ORDER_DELIVERED=false и т.д.).
    | NOTIFICATION_EMAIL_OVERRIDE — все письма на один ящик (тест до настройки Postbox).
    */
    'notifications' => [
        'email_enabled' => filter_var(env('NOTIFICATION_EMAIL_ENABLED', true), FILTER_VALIDATE_BOOL),
        'email_override' => env('NOTIFICATION_EMAIL_OVERRIDE'),
        'log_on_failure' => filter_var(env('NOTIFICATION_EMAIL_LOG_ON_FAILURE', true), FILTER_VALIDATE_BOOL),
        'categories' => [
            'general' => filter_var(env('NOTIFY_EMAIL_GENERAL', true), FILTER_VALIDATE_BOOL),
            'auth_login_otp' => filter_var(env('NOTIFY_EMAIL_AUTH_LOGIN_OTP', true), FILTER_VALIDATE_BOOL),
            'auth_login_sms' => filter_var(env('NOTIFY_EMAIL_AUTH_LOGIN_SMS', true), FILTER_VALIDATE_BOOL),
            'auth_password_reset' => filter_var(env('NOTIFY_EMAIL_AUTH_PASSWORD_RESET', true), FILTER_VALIDATE_BOOL),
            'auth_profile_phone' => filter_var(env('NOTIFY_EMAIL_AUTH_PROFILE_PHONE', true), FILTER_VALIDATE_BOOL),
            'security' => filter_var(env('NOTIFY_EMAIL_SECURITY', true), FILTER_VALIDATE_BOOL),
            'account' => filter_var(env('NOTIFY_EMAIL_ACCOUNT', true), FILTER_VALIDATE_BOOL),
            'order_created' => filter_var(env('NOTIFY_EMAIL_ORDER_CREATED', true), FILTER_VALIDATE_BOOL),
            'order_paid' => filter_var(env('NOTIFY_EMAIL_ORDER_PAID', true), FILTER_VALIDATE_BOOL),
            'order_in_transit' => filter_var(env('NOTIFY_EMAIL_ORDER_IN_TRANSIT', true), FILTER_VALIDATE_BOOL),
            'order_delivered' => filter_var(env('NOTIFY_EMAIL_ORDER_DELIVERED', true), FILTER_VALIDATE_BOOL),
            'order_issued' => filter_var(env('NOTIFY_EMAIL_ORDER_ISSUED', true), FILTER_VALIDATE_BOOL),
            'order_canceled' => filter_var(env('NOTIFY_EMAIL_ORDER_CANCELED', true), FILTER_VALIDATE_BOOL),
            'order_refused' => filter_var(env('NOTIFY_EMAIL_ORDER_REFUSED', true), FILTER_VALIDATE_BOOL),
            'order_refunded' => filter_var(env('NOTIFY_EMAIL_ORDER_REFUNDED', true), FILTER_VALIDATE_BOOL),
            'order_refusal_code' => filter_var(env('NOTIFY_EMAIL_ORDER_REFUSAL_CODE', true), FILTER_VALIDATE_BOOL),
            'pvz_admin' => filter_var(env('NOTIFY_EMAIL_PVZ_ADMIN', true), FILTER_VALIDATE_BOOL),
            'pvz_operator' => filter_var(env('NOTIFY_EMAIL_PVZ_OPERATOR', true), FILTER_VALIDATE_BOOL),
            'seller_moderation' => filter_var(env('NOTIFY_EMAIL_SELLER_MODERATION', true), FILTER_VALIDATE_BOOL),
        ],
    ],

    'review_photos_max' => (int) env('MARKETPLACE_REVIEW_PHOTOS_MAX', 5),

    'similar_products' => [
        'limit' => (int) env('MARKETPLACE_SIMILAR_PRODUCTS_LIMIT', 60),
        'initial' => (int) env('MARKETPLACE_SIMILAR_PRODUCTS_INITIAL', 20),
        'step' => (int) env('MARKETPLACE_SIMILAR_PRODUCTS_STEP', 20),
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
