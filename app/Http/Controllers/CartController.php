<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ]);
        }
        
        // Получаем товары из корзины с правильными связями
        $cartItems = Cart::with(['variant.product'])
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($cartItem) {
                return [
                    'id' => $cartItem->id,
                    'quantity' => $cartItem->quantity,
                    'product' => [
                        'id' => $cartItem->variant->product->id,
                        'title' => $cartItem->variant->product->title,
                        'image' => $cartItem->variant->product->images->first()->url ?? '/img/default.jpg',
                    ],
                    'variant' => [
                        'id' => $cartItem->variant->id,
                        'price' => $cartItem->variant->price,
                        'old_price' => $cartItem->variant->old_price,
                        'stock' => $cartItem->variant->stock,
                    ]
                ];
            });
        
        return Inertia::render('Profile/Cart', [
            'cartItems' => $cartItems,
        ]);
    }
    
    
    
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        
        $cartItem = Cart::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
            
        if ($cartItem) {
            $cartItem->update([
                'quantity' => $request->quantity
            ]);
        }
        
        return back();
    }
    
    public function remove($id)
    {
        $user = Auth::user();
        
        Cart::where('id', $id)
            ->where('user_id', $user->id)
            ->delete();
            
        return back();
    }
    
    public function create(Request $request)
    {
        $user = Auth::user();
        $selectedIds = $request->items;
        
        // Логика создания заказа из выбранных товаров
        
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
    $quantity = $request->quantity ?? 1;
    
    // Проверка существования
    $exists = Cart::where('user_id', $userId)
        ->where('variant_id', $variantId)
        ->exists();
    
    if ($exists) {
        Cart::where('user_id', $userId)
            ->where('variant_id', $variantId)
            ->increment('quantity', $quantity);
        
        return back()->with([
            'success' => 'Количество обновлено',
            'in_cart' => true
        ]);
    }
    
    Cart::create([
        'user_id' => $userId,
        'variant_id' => $variantId,
        'quantity' => $quantity,
    ]);
    
    return back()->with([
        'success' => 'Добавлено в корзину',
        'in_cart' => true
    ]);
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
