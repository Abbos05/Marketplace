<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Promocode;
use Illuminate\Http\Request;

class PromocodeController extends Controller
{
    /**
     * Validate a promo code against the current user's cart items.
     * Returns JSON: { valid, discount_amount, message, code, seller_id }
     */
    public function validate(Request $request)
    {
        $request->validate([
            'code'       => 'required|string|max:50',
            'cart_ids'   => 'required|array|min:1',
            'cart_ids.*' => 'integer',
        ]);

        $user = auth()->user();
        $code = strtoupper(trim($request->input('code')));

        $promo = Promocode::where('code', $code)->first();

        if (!$promo) {
            return response()->json(['valid' => false, 'message' => 'Промокод не найден.']);
        }

        if (!$promo->isValid()) {
            if ($promo->expires_at && now()->gt($promo->expires_at)) {
                return response()->json(['valid' => false, 'message' => 'Срок действия промокода истёк.']);
            }
            return response()->json(['valid' => false, 'message' => 'Промокод недействителен.']);
        }

        // Check total usage limit
        if ($promo->usage_limit !== null && $promo->usages()->count() >= $promo->usage_limit) {
            return response()->json(['valid' => false, 'message' => 'Промокод больше не доступен (лимит исчерпан).']);
        }

        // Check per-user usage
        if ($promo->usage_per_user !== null) {
            $userUsages = $promo->usages()->where('user_id', $user->id)->count();
            if ($userUsages >= $promo->usage_per_user) {
                return response()->json(['valid' => false, 'message' => 'Вы уже использовали этот промокод.']);
            }
        }

        // Load the cart items the buyer selected
        $cartItems = Cart::with('variant.product')
            ->whereIn('id', $request->cart_ids)
            ->where('user_id', $user->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['valid' => false, 'message' => 'Товары не найдены в корзине.']);
        }

        // Filter items belonging to the promo's seller
        $sellerItems = $cartItems->filter(
            fn($item) => $item->variant?->product?->seller_id === $promo->seller_id
        );

        if ($sellerItems->isEmpty()) {
            return response()->json([
                'valid'   => false,
                'message' => 'Промокод действует только на товары конкретного продавца, которых нет в выборке.',
            ]);
        }

        // Calculate subtotal for seller's items
        $sellerSubtotal = $sellerItems->sum(fn($item) => ($item->variant->price ?? 0) * $item->quantity);

        // Check minimum order amount
        if ($promo->min_order_amount !== null && $sellerSubtotal < $promo->min_order_amount) {
            return response()->json([
                'valid'   => false,
                'message' => 'Сумма товаров продавца меньше минимальной: ' .
                             number_format($promo->min_order_amount, 0, '.', ' ') . ' ₽.',
            ]);
        }

        // Calculate discount
        $discountAmount = 0;
        if ($promo->discount_type === 'percent') {
            $discountAmount = round($sellerSubtotal * $promo->discount_value / 100, 2);
        } else {
            $discountAmount = min((float) $promo->discount_value, $sellerSubtotal);
        }

        return response()->json([
            'valid'           => true,
            'message'         => "Промокод применён! Скидка {$promo->discount_value}%.",
            'code'            => $promo->code,
            'discount_amount' => $discountAmount,
            'seller_id'       => $promo->seller_id,
        ]);
    }
}
