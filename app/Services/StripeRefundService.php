<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Refund;
use Stripe\Stripe;

class StripeRefundService
{
    /**
     * Можно ли оформить возврат через Stripe Refund API (включая тестовые ключи sk_test_).
     */
    public function usesStripeRefundApi(Order $order): bool
    {
        return $this->resolveStripePaymentIntentId($order) !== null;
    }

    /**
     * Только когда нет Stripe payment_intent (сид, ручная демо-оплата).
     */
    public function shouldUseDemoRefund(Order $order): bool
    {
        return ! $this->usesStripeRefundApi($order);
    }

    public function isStripeTestMode(): bool
    {
        $secret = $this->stripeSecret();

        return $secret !== null && str_starts_with($secret, 'sk_test_');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function applyDemoRefund(Order $order, ?string $reason = null): array
    {
        $order->refresh();

        if ($order->payment_status === 'refunded') {
            return [
                'ok' => true,
                'message' => 'Возврат уже оформлен.',
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
            'message' => 'Возврат оформлен. Средства вернутся на карту (обычно 3–10 рабочих дней).',
        ];
    }

    public function recordSuccessfulCheckout(Order $order, object $session): void
    {
        $paymentIntentId = $this->extractPaymentIntentId($session->payment_intent ?? null);

        $payload = [
            'amount' => $order->total,
            'status' => 'succeeded',
            'provider_response' => [
                'checkout_session_id' => $session->id ?? null,
                'payment_status' => $session->payment_status ?? null,
            ],
        ];

        if ($paymentIntentId) {
            $payload['payment_id'] = $paymentIntentId;
        } else {
            Log::warning('Stripe checkout without payment_intent', [
                'order_id' => $order->id,
                'session' => $session->id ?? null,
            ]);
            $payload['payment_id'] = 'pending_'.$session->id;
        }

        Payment::query()->updateOrCreate(
            [
                'order_id' => $order->id,
                'provider' => 'stripe',
            ],
            $payload
        );
    }

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
     * @return array{ok: bool, message: string}
     */
    public function refundOrder(Order $order, ?string $reason = null): array
    {
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

        if ($this->shouldUseDemoRefund($order)) {
            return $this->applyDemoRefund($order, $reason);
        }

        $paymentIntentId = $this->resolveStripePaymentIntentId($order);

        if (! $paymentIntentId) {
            return [
                'ok' => false,
                'message' => 'Не найден идентификатор оплаты Stripe для возврата. Оплатите заказ через Stripe Checkout или обратитесь в поддержку.',
            ];
        }

        try {
            Stripe::setApiKey($this->stripeSecret());

            $payment = $this->findRefundablePayment($order);
            $amountCents = (int) round((float) ($payment?->amount ?? $order->total) * 100);

            $params = [
                'payment_intent' => $paymentIntentId,
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

            if ($payment) {
                $payment->update([
                    'payment_id' => $paymentIntentId,
                    'provider' => 'stripe',
                    'status' => 'refunded',
                    'provider_response' => array_merge(
                        is_array($payment->provider_response) ? $payment->provider_response : [],
                        [
                            'refund_id' => $refund->id,
                            'refund_status' => $refund->status ?? null,
                            'refunded_at' => now()->toIso8601String(),
                            'stripe_test_mode' => $this->isStripeTestMode(),
                        ]
                    ),
                ]);
            }

            $order->update(['payment_status' => 'refunded']);
            app(OrderLedgerService::class)->recordRefund($order);

            return [
                'ok' => true,
                'message' => 'Возврат оформлен. Средства вернутся на карту (обычно 3–10 рабочих дней).',
            ];
        } catch (\Throwable $e) {
            Log::error('Stripe refund failed', [
                'order_id' => $order->id,
                'payment_intent' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Не удалось выполнить возврат. Попробуйте позже или обратитесь в поддержку.',
            ];
        }
    }

    /**
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

    /**
     * Восстанавливает payment_intent из БД или из сохранённой Checkout Session.
     */
    public function resolveStripePaymentIntentId(Order $order): ?string
    {
        if (! Schema::hasTable('payments')) {
            return null;
        }

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('provider', 'stripe')
            ->latest('id')
            ->first();

        if ($payment && $this->isPaymentIntentId((string) $payment->payment_id)) {
            return $payment->payment_id;
        }

        $sessionId = is_array($payment?->provider_response)
            ? ($payment->provider_response['checkout_session_id'] ?? null)
            : null;

        if (! $sessionId || ! $this->stripeConfigured()) {
            return null;
        }

        try {
            Stripe::setApiKey($this->stripeSecret());
            $session = StripeCheckoutSession::retrieve($sessionId, ['expand' => ['payment_intent']]);
            $paymentIntentId = $this->extractPaymentIntentId($session->payment_intent ?? null);

            if ($paymentIntentId && $payment) {
                $payment->update([
                    'payment_id' => $paymentIntentId,
                    'provider_response' => array_merge(
                        $payment->provider_response ?? [],
                        ['payment_intent_recovered_at' => now()->toIso8601String()]
                    ),
                ]);
            }

            return $paymentIntentId;
        } catch (\Throwable $e) {
            Log::warning('Could not recover payment_intent from checkout session', [
                'order_id' => $order->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function findRefundablePayment(Order $order): ?Payment
    {
        if (! Schema::hasTable('payments')) {
            return null;
        }

        return Payment::query()
            ->where('order_id', $order->id)
            ->where('status', 'succeeded')
            ->latest('id')
            ->first();
    }

    private function isPaymentIntentId(string $id): bool
    {
        return str_starts_with($id, 'pi_');
    }

    private function extractPaymentIntentId(mixed $paymentIntent): ?string
    {
        if (is_object($paymentIntent)) {
            return $paymentIntent->id ?? null;
        }

        return is_string($paymentIntent) && $this->isPaymentIntentId($paymentIntent)
            ? $paymentIntent
            : null;
    }

    private function stripeConfigured(): bool
    {
        return $this->stripeSecret() !== null;
    }

    private function stripeSecret(): ?string
    {
        $secret = config('services.stripe.secret') ?? config('stripe.secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}
