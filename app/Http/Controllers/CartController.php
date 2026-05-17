<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\PickupPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index()
    {
        $user = Auth::user();
        
        if (!$user) {
            return Inertia::render('Profile/Cart', [
                'cartItems' => [],
                'total' => 0,
                'pickupPoints' => [],
            ]);
        }
        
        // Получаем товары из корзины с правильными связями
        $cartItems = Cart::with(['variant.product.images'])
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn (Cart $cartItem) => $cartItem->variant?->product?->isPubliclyVisible())
            ->values()
            ->map(function ($cartItem) {
                return [
                    'id' => $cartItem->id,
                    'quantity' => $cartItem->quantity,
                    'product' => [
                        'id' => $cartItem->variant->product->id,
                        'title' => $cartItem->variant->product->title,
                        'image' => optional($cartItem->variant->product->images->first())->url ?? '/img/default.jpg',
                    ],
                    'variant' => [
                        'id' => $cartItem->variant->id,
                        'price' => $cartItem->variant->price,
                        'old_price' => $cartItem->variant->old_price,
                        'stock' => $cartItem->variant->stock,
                        'options' => is_array($cartItem->variant->options)
                            ? json_encode($cartItem->variant->options)
                            : ($cartItem->variant->options ?? null),
                    ],
                ];
            });
        
        $pickupPoints = PickupPoint::query()
            ->active()
            ->with('region')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (PickupPoint $p) => [
                'id' => $p->id,
                'label' => $p->title.($p->region ? ' — '.$p->region->name : ''),
            ]);

        return Inertia::render('Profile/Cart', [
            'cartItems' => $cartItems,
            'pickupPoints' => $pickupPoints,
        ]);
    }
    
    
    
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        $cartItem = Cart::with('variant')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $cartItem) {
            return back();
        }

        $cartItem->loadMissing('variant.product');
        if (! $cartItem->variant?->product?->isPubliclyVisible()) {
            $cartItem->delete();

            return back()->with('error', 'Товар больше недоступен и удалён из корзины.');
        }

        $qty = (int) $request->quantity;
        if ($qty < 1) {
            return back();
        }

        return DB::transaction(function () use ($cartItem, $qty) {
            $variant = ProductVariant::query()
                ->whereKey($cartItem->variant_id)
                ->lockForUpdate()
                ->first();

            if (! $variant || $qty > $variant->stock) {
                return back()->with('error', 'Недостаточно товара на складе.');
            }

            $cartItem->update(['quantity' => $qty]);

            return back();
        });
    }
    
    public function remove($id)
    {
        $user = Auth::user();
        
        Cart::where('id', $id)
            ->where('user_id', $user->id)
            ->delete();
            
        return back();
    }
    
    /**
     * Store a newly created resource in storage.
     */
  public function store(Request $request)
{
    $request->validate([
        'variant_id' => 'required|exists:product_variants,id'
    ]);
    
    $userId = auth()->id();
    $variantId = $request->variant_id;
    $quantity = (int) ($request->quantity ?? 1);
    if ($quantity < 1) {
        $quantity = 1;
    }

    return DB::transaction(function () use ($userId, $variantId, $quantity) {
        $variant = ProductVariant::query()
            ->whereKey($variantId)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $variant->is_active) {
            return back()->with('error', 'Этот вариант сейчас недоступен.');
        }

        $variant->loadMissing('product');
        if (! $variant->product?->isPurchasable()) {
            return back()->with([
                'error' => $variant->product?->storefrontBlockMessage() ?? 'Товар недоступен для заказа.',
                'in_cart' => false,
            ]);
        }

        $existing = Cart::query()
            ->where('user_id', $userId)
            ->where('variant_id', $variantId)
            ->lockForUpdate()
            ->first();

        $currentQty = $existing ? (int) $existing->quantity : 0;
        if ($currentQty + $quantity > $variant->stock) {
            return back()->with('error', 'На складе только '.$variant->stock.' шт.');
        }

        if ($existing) {
            $existing->increment('quantity', $quantity);

            return back()->with([
                'success' => 'Количество обновлено',
                'in_cart' => true,
            ]);
        }

        Cart::create([
            'user_id' => $userId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ]);

        return back()->with([
            'success' => 'Добавлено в корзину',
            'in_cart' => true,
        ]);
    });
}

public function destroy($variantId)
{
    $userId = auth()->id();
    
    Cart::where('user_id', $userId)
        ->where('variant_id', $variantId)
        ->delete();
    
    return back()->with([
        'success' => 'Удалено из корзины',
        'in_cart' => false
    ]);
}
}
