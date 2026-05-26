<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderLedgerService;
use App\Services\OrderNotificationService;
use App\Services\StripeRefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripePaymentController extends Controller
{
    public function createCheckoutSession(Request $request)
    {
        try {
            if (! $request->user()?->phone) {
                return response()->json([
                    'error' => 'Подтвердите номер телефона в профиле, чтобы перейти к оплате.',
                    'redirect' => route('profile'),
                ], 422);
            }

            $this->configureStripe();

            $productId = $request->product_id;
            $variantId = $request->variant_id;
            $title = $request->title;
            $price = $request->price;

            if (! $price && $variantId) {
                $price = ProductVariant::find($variantId)?->price ?? 0;
            }

            if (! $price && $productId) {
                $price = Product::find($productId)?->min_price ?? 0;
            }

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'rub',
                        'product_data' => [
                            'name' => $title ?? 'Товар',
                        ],
                        'unit_amount' => (int) round((float) $price * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('stripe.success').'?session_id={CHECKOUT_SESSION_ID}&type=product&product_id='.$productId,
                'cancel_url' => route('product.show', $productId),
                'metadata' => [
                    'product_id' => (string) $productId,
                    'variant_id' => (string) $variantId,
                    'type' => 'product',
                ],
            ]);

            return response()->json(['url' => $session->url]);
        } catch (\Throwable $e) {
            Log::error('Stripe checkout error', ['message' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createOrderCheckoutSession(Request $request)
    {
        try {
            if (! $request->user()?->phone) {
                return response()->json([
                    'error' => 'Подтвердите номер телефона в профиле, чтобы перейти к оплате.',
                    'redirect' => route('profile'),
                ], 422);
            }

            $this->configureStripe();

            $order = Order::with('items.variant.product')->findOrFail($request->order_id);

            if ($order->buyer_id !== $request->user()->id) {
                abort(403);
            }

            $lineItems = [];
            foreach ($order->items as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'rub',
                        'product_data' => [
                            'name' => $item->variant->product->title,
                        ],
                        'unit_amount' => (int) round((float) $item->price_at_purchase * 100),
                    ],
                    'quantity' => $item->quantity,
                ];
            }

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => route('stripe.success').'?session_id={CHECKOUT_SESSION_ID}&type=order&order_id='.$order->id,
                'cancel_url' => route('profile.orders'),
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'type' => 'order',
                ],
            ]);

            return response()->json(['url' => $session->url]);
        } catch (\Throwable $e) {
            Log::error('Stripe order checkout error', ['message' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function success(Request $request)
    {
        $sessionId = (string) $request->query('session_id', '');

        if ($sessionId === '') {
            return redirect()->route('profile.orders')->with('error', 'Не удалось подтвердить оплату: нет идентификатора сессии.');
        }

        try {
            $this->configureStripe();
            $session = Session::retrieve($sessionId, ['expand' => ['payment_intent']]);
        } catch (\Throwable $e) {
            Log::error('Stripe success retrieve error', ['session_id' => $sessionId, 'message' => $e->getMessage()]);

            return redirect()->route('profile.orders')->with('error', 'Не удалось проверить статус оплаты в Stripe.');
        }

        if (($session->payment_status ?? null) !== 'paid') {
            return redirect()->route('profile.orders')->with('error', 'Оплата не завершена.');
        }

        $type = (string) ($session->metadata->type ?? $request->query('type', ''));

        if ($type === 'product') {
            return redirect()->route('profile.orders')->with('success', 'Товар успешно оплачен!');
        }

        if ($type !== 'order') {
            return redirect()->route('profile.orders')->with('error', 'Неизвестный тип оплаты.');
        }

        $orderId = (int) ($session->metadata->order_id ?? $request->query('order_id', 0));
        $order = Order::query()->find($orderId);

        if (! $order) {
            return redirect()->route('profile.orders')->with('error', 'Заказ не найден после оплаты.');
        }

        $ledgerWarning = null;

        try {
            DB::transaction(function () use ($order, $session) {
                if ($order->payment_status !== 'paid') {
                    $order->update(['payment_status' => 'paid']);
                }

                app(StripeRefundService::class)->recordSuccessfulCheckout($order, $session);
                app(OrderLedgerService::class)->recordPayment($order);
            });
        } catch (\Throwable $e) {
            Log::error('Stripe success post-processing error', [
                'order_id' => $order->id,
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);

            if ($order->payment_status !== 'paid') {
                return redirect()->route('profile.orders')->with('error', 'Ошибка при обработке оплаты.');
            }

            $ledgerWarning = ' Оплата прошла, но часть служебных записей не сохранилась — сообщите в поддержку при необходимости.';
        }

        if ($order->payment_status === 'paid') {
            app(OrderNotificationService::class)->notifyPaid($order->fresh());
        }

        return redirect()->route('profile.orders')->with(
            'success',
            'Заказ успешно оплачен!'.($ledgerWarning ?? '')
        );
    }

    private function configureStripe(): void
    {
        $secret = config('services.stripe.secret') ?? config('stripe.secret');

        if (! $secret) {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        Stripe::setApiKey($secret);
    }
}
