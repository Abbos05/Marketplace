<?php 

use App\Models\OrderItem;

// routes/api.php
Route::get('/check-ordered/{variantId}', function ($variantId) {
    $user = auth()->user();
    
    $orderItem = OrderItem::where('variant_id', $variantId)
        ->whereHas('order', function ($query) use ($user) {
            $query->where('buyer_id', $user->id);
        })
        ->latest()
        ->first();
    
    if ($orderItem) {
        return response()->json([
            'ordered' => true,
            'order_id' => $orderItem->order_id
        ]);
    }
    
    return response()->json(['ordered' => false]);
})->middleware('auth');

?>