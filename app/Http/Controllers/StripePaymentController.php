<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderLedgerService;
use App\Services\StripeRefundService;
use Illuminate\Support\Facades\DB;

class StripePaymentController extends Controller
{
    // Оплата товара со страницы продукта
    public function createCheckoutSession(Request $request)
    {
        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));
            
            // Логируем входящий запрос
            \Log::info('Stripe checkout request:', $request->all());
            
            $productId = $request->product_id;
            $variantId = $request->variant_id;
            $title = $request->title;
            $price = $request->price;
            
            // Если price не пришёл, получаем из базы
            if (!$price && $variantId) {
                $variant = ProductVariant::find($variantId);
                $price = $variant->price ?? 0;
            }
            
            if (!$price && $productId) {
                $product = Product::find($productId);
                $price = $product->min_price ?? 0;
            }
            
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'rub',
                        'product_data' => [
                            'name' => $title ?? 'Товар',
                        ],
                        'unit_amount' => (int)($price * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('stripe.success') . '?session_id={CHECKOUT_SESSION_ID}&type=product&product_id=' . $productId,
                'cancel_url' => route('product.show', $productId),
                'metadata' => [
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'type' => 'product'
                ],
            ]);
            
            return response()->json(['url' => $session->url]);
            
        } catch (\Exception $e) {
            \Log::error('Stripe error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Оплата заказа
    public function createOrderCheckoutSession(Request $request)
    {
        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));
            
            \Log::info('Stripe order checkout request:', $request->all());
            
            $orderId = $request->order_id;
            $order = Order::with('items.variant.product')->findOrFail($orderId);
            
            $lineItems = [];
            foreach ($order->items as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'rub',
                        'product_data' => [
                            'name' => $item->variant->product->title,
                        ],
                        'unit_amount' => (int)($item->price_at_purchase * 100),
                    ],
                    'quantity' => $item->quantity,
                ];
            }
            
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => route('stripe.success') . '?session_id={CHECKOUT_SESSION_ID}&type=order&order_id=' . $order->id,
                'cancel_url' => route('profile.orders'),
                'metadata' => [
                    'order_id' => $order->id,
                    'type' => 'order'
                ],
            ]);
            
            return response()->json(['url' => $session->url]);
            
        } catch (\Exception $e) {
            \Log::error('Stripe order error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Успешная оплата
    public function success(Request $request)
    {
        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));
            
            $sessionId = $request->session_id;
            $session = Session::retrieve($sessionId);
            
            \Log::info('Stripe success callback:', [
                'session_id' => $sessionId,
                'payment_status' => $session->payment_status,
                'metadata' => $session->metadata
            ]);
            
            if ($session->payment_status === 'paid') {
                $type = $session->metadata->type ?? $request->type;
                
                if ($type === 'product') {
                    $productId = $session->metadata->product_id ?? $request->product_id;
                    return redirect()->route('profile.orders')->with('success', 'Товар успешно оплачен!');
                }
                
                if ($type === 'order') {
                    $orderId = $session->metadata->order_id ?? $request->order_id;
                    $order = Order::find($orderId);
                    if ($order) {
                        $order->update([
                            'payment_status' => 'paid',
                        ]);
                        app(StripeRefundService::class)->recordSuccessfulCheckout($order, $session);
                        app(OrderLedgerService::class)->recordPayment($order);
                    }
                    return redirect()->route('profile.orders')->with('success', 'Заказ успешно оплачен!');
                }
            }
            
            return redirect()->route('profile.orders')->with('error', 'Ошибка оплаты');
            
        } catch (\Exception $e) {
            \Log::error('Stripe success error: ' . $e->getMessage());
            return redirect()->route('profile.orders')->with('error', 'Ошибка при обработке оплаты');
        }
    }
}