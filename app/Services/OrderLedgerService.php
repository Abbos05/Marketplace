<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

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
            if (! $productId || ! $item->seller_id) {
                continue;
            }

            $snapshot = $this->ensureCommissionSnapshot($item);

            Transaction::query()->create([
                'order_id' => $order->id,
                'buyer_id' => $order->buyer_id,
                'seller_id' => $item->seller_id,
                'product_id' => $productId,
                'amount' => $snapshot['gross'],
                'gross_amount' => $snapshot['gross'],
                'commission_amount' => $snapshot['commission'],
                'seller_payout_amount' => $snapshot['seller_payout'],
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
            if (! $productId || ! $item->seller_id) {
                continue;
            }

            $snapshot = $this->ensureCommissionSnapshot($item);

            Transaction::query()->create([
                'order_id' => $order->id,
                'buyer_id' => $order->buyer_id,
                'seller_id' => $item->seller_id,
                'product_id' => $productId,
                'amount' => $snapshot['gross'],
                'gross_amount' => $snapshot['gross'],
                'commission_amount' => $snapshot['commission'],
                'seller_payout_amount' => $snapshot['seller_payout'],
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
            if (! $productId || ! $item->seller_id) {
                continue;
            }

            $snapshot = $this->ensureCommissionSnapshot($item);

            Transaction::query()->create([
                'order_id' => $order->id,
                'buyer_id' => $order->buyer_id,
                'seller_id' => $item->seller_id,
                'product_id' => $productId,
                'amount' => $snapshot['gross'],
                'gross_amount' => $snapshot['gross'],
                'commission_amount' => $snapshot['commission'],
                'seller_payout_amount' => $snapshot['seller_payout'],
                'type' => self::TYPE_CANCEL,
                'status' => $order->payment_status === 'paid' ? 'refunded' : 'failed',
                'description' => 'Отмена заказа №'.($order->number ?? $order->id),
            ]);
        }
    }

    public function finalizeCommission(Order $order): void
    {
        if ($order->status !== Order::STATUS_ISSUED || $order->payment_status !== 'paid') {
            return;
        }

        DB::transaction(function () use ($order): void {
            $order->loadMissing('items.variant.product');
            $this->recordPayment($order);

            foreach ($order->items as $item) {
                $this->ensureCommissionSnapshot($item);

                if ($item->commission_status === 'finalized') {
                    continue;
                }

                $item->forceFill([
                    'commission_status' => 'finalized',
                    'commission_finalized_at' => now(),
                ])->save();
            }
        });
    }

    public function reverseCommission(Order $order): void
    {
        if (! in_array($order->status, [Order::STATUS_CANCELED, Order::STATUS_REFUSED], true)) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->commission_status === 'reversed') {
                continue;
            }

            $item->forceFill([
                'commission_status' => 'reversed',
                'commission_finalized_at' => null,
            ])->save();
        }
    }

    /**
     * @return array{gross: float, commission: float, seller_payout: float, percent: float, fixed_amount: float}
     */
    private function ensureCommissionSnapshot(OrderItem $item): array
    {
        return app(CommissionService::class)->resolveSnapshot($item, persist: true);
    }
}
