<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;

class OrderLedgerService
{
    public const TYPE_PAYMENT = 'payment';

    public const TYPE_REFUND = 'refund';

    public const TYPE_CANCEL = 'cancel';

    /**
     * Записи в transactions по позициям заказа (оплата).
     */
    public function recordPayment(Order $order): void
    {
        if (Transaction::query()->where('order_id', $order->id)->where('type', self::TYPE_PAYMENT)->exists()) {
            return;
        }

        $order->loadMissing('items.variant.product');

        foreach ($order->items as $item) {
            $productId = $item->variant?->product_id;
            if (! $productId) {
                continue;
            }

            $amount = (float) $item->price_at_purchase * (int) $item->quantity;

            Transaction::query()->create([
                'order_id' => $order->id,
                'buyer_id' => $order->buyer_id,
                'seller_id' => $item->seller_id,
                'product_id' => $productId,
                'amount' => $amount,
                'type' => self::TYPE_PAYMENT,
                'status' => 'completed',
                'description' => 'Оплата заказа №'.($order->number ?? $order->id),
            ]);
        }
    }

    public function recordRefund(Order $order): void
    {
        if (Transaction::query()->where('order_id', $order->id)->where('type', self::TYPE_REFUND)->exists()) {
            return;
        }

        $order->loadMissing('items.variant.product');

        foreach ($order->items as $item) {
            $productId = $item->variant?->product_id;
            if (! $productId) {
                continue;
            }

            $amount = (float) $item->price_at_purchase * (int) $item->quantity;

            Transaction::query()->create([
                'order_id' => $order->id,
                'buyer_id' => $order->buyer_id,
                'seller_id' => $item->seller_id,
                'product_id' => $productId,
                'amount' => $amount,
                'type' => self::TYPE_REFUND,
                'status' => 'refunded',
                'description' => 'Возврат по заказу №'.($order->number ?? $order->id),
            ]);
        }
    }

    public function recordCancel(Order $order): void
    {
        if (Transaction::query()->where('order_id', $order->id)->where('type', self::TYPE_CANCEL)->exists()) {
            return;
        }

        $order->loadMissing('items.variant.product');

        foreach ($order->items as $item) {
            $productId = $item->variant?->product_id;
            if (! $productId) {
                continue;
            }

            $amount = (float) $item->price_at_purchase * (int) $item->quantity;

            Transaction::query()->create([
                'order_id' => $order->id,
                'buyer_id' => $order->buyer_id,
                'seller_id' => $item->seller_id,
                'product_id' => $productId,
                'amount' => $amount,
                'type' => self::TYPE_CANCEL,
                'status' => $order->payment_status === 'paid' ? 'refunded' : 'failed',
                'description' => 'Отмена заказа №'.($order->number ?? $order->id),
            ]);
        }
    }
}
