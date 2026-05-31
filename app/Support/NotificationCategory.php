<?php

namespace App\Support;

/**
 * Ключи категорий для config('marketplace.notifications.categories').
 * Отключите категорию в .env, если не нужны письма этого типа.
 */
final class NotificationCategory
{
    public const General = 'general';

    public const AuthLoginOtp = 'auth_login_otp';

    public const AuthLoginSms = 'auth_login_sms';

    public const AuthPasswordReset = 'auth_password_reset';

    public const AuthProfilePhone = 'auth_profile_phone';

    public const AuthProfileEmail = 'auth_profile_email';

    public const Security = 'security';

    public const Account = 'account';

    public const OrderCreated = 'order_created';

    public const OrderPaid = 'order_paid';

    public const OrderInTransit = 'order_in_transit';

    public const OrderDelivered = 'order_delivered';

    public const OrderIssued = 'order_issued';

    public const OrderCanceled = 'order_canceled';

    public const OrderRefused = 'order_refused';

    public const OrderRefunded = 'order_refunded';

    public const OrderRefusalCode = 'order_refusal_code';

    public const PvzAdmin = 'pvz_admin';

    public const PvzOperator = 'pvz_operator';

    public const SellerModeration = 'seller_moderation';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::General,
            self::AuthLoginOtp,
            self::AuthLoginSms,
            self::AuthPasswordReset,
            self::AuthProfilePhone,
            self::AuthProfileEmail,
            self::Security,
            self::Account,
            self::OrderCreated,
            self::OrderPaid,
            self::OrderInTransit,
            self::OrderDelivered,
            self::OrderIssued,
            self::OrderCanceled,
            self::OrderRefused,
            self::OrderRefunded,
            self::OrderRefusalCode,
            self::PvzAdmin,
            self::PvzOperator,
            self::SellerModeration,
        ];
    }
}
