<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Order;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request)
    {

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'order_id' => 'required|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $order = Order::findOrFail($request->order_id);


        // защита
        $issuedAt = $order->updated_at;
        $now = now();

        if ($issuedAt->diffInDays($now) > 90) {
            return back()->with('error', 'Срок для отзыва истёк');
        }
        if ($order->buyer_id !== auth()->id()) {
            abort(403);
        }

        // можно оставить отзыв ТОЛЬКО если заказ получен
        if ($order->status !== 'issued') {
            return back()->with('error', 'Можно оставить отзыв только после получения заказа');
        }

        // проверка: уже есть отзыв?
        $exists = Review::where('user_id', auth()->id())
            ->where('order_id', $order->id)
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Вы уже оставили отзыв');
        }


        Review::create([
            'product_id' => $request->product_id,
            'variant_id' => $request->variant_id,
            'user_id' => auth()->id(),
            'order_id' => $order->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_moderated' => false, // 👈 важно
        ]);

        return back()->with('success', 'Отзыв отправлен на модерацию');
    }
    // app/Http/Controllers/ReviewController.php

public function update(Request $request, Review $review)
{
    // Проверка прав
    if ($review->user_id !== auth()->id()) {
        abort(403);
    }
    
    // Проверка срока
    $order = $review->order;
    $issuedAt = $order->updated_at;
    $now = now();
    
    if ($issuedAt->diffInDays($now) > 90) {
        return back()->with('error', 'Срок для редактирования отзыва истёк');
    }   
    
    // Валидация
    $request->validate([
        'rating' => 'required|integer|min:1|max:5',
        'comment' => 'nullable|string|max:1000',
    ]);
    
    // Обновляем отзыв
    $review->update([
        'rating' => $request->rating,
        'comment' => $request->comment,
        'is_moderated' => false, // После редактирования снова на модерацию
    ]);
    
    return back()->with('success', 'Отзыв обновлён и отправлен на модерацию');
}
    public function destroy(Review $review)
    {
        if ($review->user_id !== auth()->id()) {
            abort(403);
        }

        $review->delete();

        return back()->with('success', 'Отзыв удалён');
    }
}