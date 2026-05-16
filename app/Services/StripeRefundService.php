<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderLedgerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Refund;
use Stripe\Stripe;

class StripeRefundService
{
    public function isDemoMode(): bool
    {
        return (bool) config('marketplace.demo_payments', false);
    }

    /**
     * Возврат без вызова Stripe API (сид, локальное демо, нет записи платежа).
     */
    public function shouldUseDemoRefund(Order $order): bool
    {
        if ($this->isDemoMode()) {
            return true;
        }

        $payment = $this->findRefundablePayment($order);

        if (! $payment) {
            return true;
        }

        if ($payment->provider === 'demo') {
            return true;
        }

        if (str_starts_with((string) $payment->payment_id, 'demo_')) {
            return true;
        }

        return false;
    }

    /**
     * Демо-возврат: помечаем заказ и платёж как refunded (без Stripe).
     *
     * @return array{ok: bool, message: string}
     */
    public function applyDemoRefund(Order $order, ?string $reason = null): array
    {
        $order->refresh();

        if ($order->payment_status === 'refunded') {
            return [
                'ok' => true,
                'message' => 'Возврат уже оформлен (демо).',
            ];
        }

        if ($order->payment_status !== 'paid') {
            return [
                'ok' => false,
                'message' => 'Возврат доступен только для оплаченных заказов.',
            ];
        }

        $existing = Payment::query()
            ->where('order_id', $order->id)
            ->latest('id')
            ->first();

        $refundId = 'demo_ref_'.Str::lower(Str::random(16));

        if ($existing) {
            $existing->update([
                'status' => 'refunded',
                'provider_response' => array_merge(
                    is_array($existing->provider_response) ? $existing->provider_response : [],
                    [
                        'demo' => true,
                        'refund_id' => $refundId,
                        'refund_status' => 'succeeded',
                        'refunded_at' => now()->toIso8601String(),
                        'reason' => $reason ?? 'demo_refund',
                    ]
                ),
            ]);
        } else {
            Payment::query()->create([
                'order_id' => $order->id,
                'payment_id' => 'demo_pi_'.Str::lower(Str::random(12)),
                'provider' => 'demo',
                'amount' => $order->total,
                'status' => 'refunded',
                'provider_response' => [
                    'demo' => true,
                    'refund_id' => $refundId,
                    'refund_status' => 'succeeded',
                    'refunded_at' => now()->toIso8601String(),
                    'reason' => $reason ?? 'demo_refund',
                ],
            ]);
        }

        $order->update(['payment_status' => 'refunded']);
        app(OrderLedgerService::class)->recordRefund($order);

        return [
            'ok' => true,
            'message' => 'Возврат выполнен (демо). Сумма '.number_format((float) $order->total, 0, ',', ' ').' ₽ зачислена обратно на карту в учебном режиме.',
        ];
    }

    /**
     * Сохраняет успешную оплату заказа через Stripe Checkout (для последующего возврата).
     */
    public function recordSuccessfulCheckout(Order $order, object $session): void
    {
        $paymentIntentId = $session->payment_intent ?? null;
        if (! $paymentIntentId) {
            Log::warning('Stripe checkout without payment_intent', ['order_id' => $order->id, 'session' => $session->id ?? null]);

            return;
        }

        Payment::query()->updateOrCreate(
            [
                'order_id' => $order->id,
                'provider' => 'stripe',
            ],
            [
                'payment_id' => $paymentIntentId,
                'amount' => $order->total,
                'status' => 'succeeded',
                'provider_response' => [
                    'checkout_session_id' => $session->id,
                    'payment_status' => $session->payment_status ?? null,
                ],
            ]
        );
    }

    /**
     * Помечает оплату как демо (если оплату имитировали без Stripe).
     */
    public function recordDemoPayment(Order $order): void
    {
        Payment::query()->updateOrCreate(
            [
                'order_id' => $order->id,
                'provider' => 'demo',
            ],
            [
                'payment_id' => 'demo_pi_'.Str::lower(Str::random(12)),
                'amount' => $order->total,
                'status' => 'succeeded',
                'provider_response' => [
                    'demo' => true,
                    'paid_at' => now()->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Возврат оплаты на карту через Stripe (полная сумма заказа).
     *
     * @return array{ok: bool, message: string}
     */
    public function refundOrder(Order $order, ?string $reason = null): array
    {
        if ($this->shouldUseDemoRefund($order)) {
            return $this->applyDemoRefund($order, $reason);
        }

        $order->refresh();

        if ($order->payment_status === 'refunded') {
            return [
                'ok' => true,
                'message' => 'Деньги уже возвращены на карту.',
            ];
        }

        if ($order->payment_status !== 'paid') {
            return [
                'ok' => false,
                'message' => 'Возврат доступен только для оплаченных заказов.',
            ];
        }

        $payment = $this->findRefundablePayment($order);

        if (! $payment) {
            return $this->applyDemoRefund($order, $reason);
        }

        $secret = config('services.stripe.secret');
        if (! $secret) {
            return $this->applyDemoRefund($order, $reason);
        }

        try {
            Stripe::setApiKey($secret);

            $amountCents = (int) round((float) $payment->amount * 100);
            $params = [
                'payment_intent' => $payment->payment_id,
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_number' => (string) ($order->number ?? ''),
                    'reason' => $reason ?? 'order_refund',
                ],
            ];
            if ($amountCents > 0) {
                $params['amount'] = $amountCents;
            }

            $refund = Refund::create($params);

            $payment->update([
                'status' => 'refunded',
                'provider_response' => array_merge(
                    is_array($payment->provider_response) ? $payment->provider_response : [],
                    [
                        'refund_id' => $refund->id,
                        'refund_status' => $refund->status ?? null,
                        'refunded_at' => now()->toIso8601String(),
                    ]
                ),
            ]);

            $order->update(['payment_status' => 'refunded']);
            app(OrderLedgerService::class)->recordRefund($order);

            return [
                'ok' => true,
                'message' => 'Средства отправлены на возврат. Обычно деньги приходят на карту в течение 3–10 рабочих дней (зависит от банка).',
            ];
        } catch (\Throwable $e) {
            Log::error('Stripe refund failed', [
                'order_id' => $order->id,
                'payment_intent' => $payment->payment_id,
                'error' => $e->getMessage(),
            ]);

            return $this->applyDemoRefund($order, $reason.'_stripe_fallback');
        }
    }

    /**
     * Отмена/отказ: при оплате — возврат (в демо — сразу, без отдельной страницы).
     *
     * @return array{ok: bool, message: string, refunded: bool}
     */
    public function handleOrderCanceledOrRefused(Order $order, string $context = 'cancel'): array
    {
        if ($order->payment_status === 'paid') {
            $result = $this->refundOrder($order, $context);

            return [
                'ok' => $result['ok'],
                'message' => $result['message'],
                'refunded' => $result['ok'],
            ];
        }

        return [
            'ok' => true,
            'message' => '',
            'refunded' => false,
        ];
    }

    private function findRefundablePayment(Order $order): ?Payment
    {
        return Payment::query()
            ->where('order_id', $order->id)
            ->where('status', 'succeeded')
            ->whereIn('provider', ['stripe', 'demo'])
            ->latest('id')
            ->first();
    }
}
