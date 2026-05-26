<?php

namespace App\Services;

use App\Models\Order;

class PvzFeeCalculator
{
    public static function percent(): float
    {
        return (float) config('marketplace.pvz_fee.percent_of_order', 3);
    }

    public static function maxPerOrder(): float
    {
        return (float) config('marketplace.pvz_fee.max_per_order', 50);
    }

    public static function forOrderTotal(float $orderTotal): float
    {
        $percent = self::percent();
        $max = self::maxPerOrder();
        $fee = round($orderTotal * $percent / 100, 2);

        return min($fee, $max);
    }

    public static function forOrder(Order $order): float
    {
        return self::forOrderTotal((float) $order->total);
    }

    /**
     * @return array{percent: float, max: float, label: string}
     */
    public static function feeDescription(): array
    {
        $percent = self::percent();
        $max = self::maxPerOrder();

        return [
            'percent' => $percent,
            'max' => $max,
            'label' => sprintf('%s%% от суммы заказа, не более %s ₽', rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.'), number_format($max, 0, '', ' ')),
        ];
    }
}
