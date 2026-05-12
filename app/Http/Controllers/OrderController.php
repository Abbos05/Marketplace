<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('items.variant.product')
            ->where('buyer_id', auth()->id())
            ->latest()
            ->get();

        return Inertia::render('Profile/Orders', [
            'orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'number' => $order->number,
                    'order_code' => $order->order_code,
                    'status' => $order->status,
                    'total' => (float) $order->total,
                    'delivery_address' => $order->delivery_address,
                    'delivery_method' => $order->delivery_method,
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at->format('d.m.Y H:i'),
                    'updated_at' => $order->updated_at->format('d.m.Y'),
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'quantity' => $item->quantity,
                            'price_at_purchase' => (float) $item->price_at_purchase,
                            'variant' => [
                                'id' => $item->variant->id ?? null,
                                'price' => $item->variant->price ?? 0,
                                'product' => [
                                    'id' => $item->variant->product->id ?? null,
                                    'title' => $item->variant->product->title ?? 'Товар',
                                    'image' => $item->variant->product->image ?? '/img/products/default.png',
                                ]
                            ]
                        ];
                    })
                ];
            })
        ]);
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $items = $request->items;

        if (!$items || count($items) === 0) {
            return back()->with('error', 'Нет товаров');
        }

        DB::beginTransaction();

        // Генерация номера заказа
        $number = 'ORD-' . time() . '-' . rand(1000, 9999);
        $orderCode = strtoupper(substr(md5(uniqid()), 0, 10));

        $order = Order::create([
            'number' => $number,
            'order_code' => $orderCode,
            'buyer_id' => $user->id,
            'status' => 'new',
            'total' => 0,
            'payment_status' => 'pending',
            'delivery_method' => 'pvz',
        ]);

        $total = 0;

        foreach ($items as $item) {
            // Если есть cart_id - берем из корзины
            if (isset($item['cart_id'])) {
                $cartItem = Cart::with('variant.product')
                    ->where('id', $item['cart_id'])
                    ->where('user_id', $user->id)
                    ->first();

                $price = $cartItem->variant->price;
                $variantId = $cartItem->variant_id;
                $sellerId = $cartItem->variant->product->seller_id;
                $quantity = $item['quantity'];

                $cartItem->delete();
            }
            // Если нет cart_id, но есть variant_id - прямой заказ
            else {
                $variant = ProductVariant::with('product')->find($item['variant_id']);

                $price = $variant->price;
                $variantId = $variant->id;
                $sellerId = $variant->product->seller_id;
                $quantity = $item['quantity'] ?? 1;
            }

            $total += $price * $quantity;

            OrderItem::create([
                'order_id' => $order->id,
                'variant_id' => $variantId,
                'seller_id' => $sellerId,
                'quantity' => $quantity,
                'price_at_purchase' => $price,
                'commission_percent' => 0,
            ]);
        }

        $order->update(['total' => $total]);

        DB::commit();

        return redirect()->route('profile.orders')->with('success', 'Заказ оформлен!');
    }
    public function show(Order $order)
{
    if ($order->buyer_id !== auth()->id()) {
        abort(403);
    }

    $order->load([
        'items.variant.product',
        'items.review' // 👈 ВОТ ЭТО ДОБАВИЛ
    ]);

    return Inertia::render('Profile/OrderShow', [
        'order' => $order
    ]);
}

    public function cancel(Order $order)
    {
        if ($order->buyer_id !== auth()->id()) {
            abort(403);
        }

        if ($order->status === 'new') {
            $order->update(['status' => 'canceled', 'payment_status' => 'failed']);
        }

        return back()->with('success', 'Заказ отменён');
    }
}