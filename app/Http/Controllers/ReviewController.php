<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Review;
use App\Services\ReviewImageService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewImageService $reviewImages
    ) {}

    public function store(Request $request)
    {
        $maxPhotos = $this->reviewImages->maxPhotos();

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'order_id' => 'required|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'photos' => 'nullable|array|max:'.$maxPhotos,
            'photos.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $order = Order::findOrFail($request->order_id);

        $issuedAt = $order->updated_at;
        if ($issuedAt->diffInDays(now()) > 90) {
            return back()->with('error', 'Срок для отзыва истёк');
        }

        if ($order->buyer_id !== auth()->id()) {
            abort(403);
        }

        if ($order->status !== Order::STATUS_ISSUED) {
            return back()->with('error', 'Можно оставить отзыв только после получения заказа');
        }

        $exists = Review::where('user_id', auth()->id())
            ->where('order_id', $order->id)
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Вы уже оставили отзыв');
        }

        $review = Review::create([
            'product_id' => $request->product_id,
            'variant_id' => $request->variant_id,
            'user_id' => auth()->id(),
            'order_id' => $order->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_moderated' => false,
        ]);

        $photos = $request->file('photos', []);
        if (! is_array($photos)) {
            $photos = $photos ? [$photos] : [];
        }
        $this->reviewImages->storeMany($review, $photos);

        return back()->with('success', 'Отзыв отправлен на модерацию');
    }

    public function update(Request $request, Review $review)
    {
        if ($review->user_id !== auth()->id()) {
            abort(403);
        }

        $order = $review->order;
        if (! $order) {
            abort(404);
        }

        if ($order->updated_at->diffInDays(now()) > 90) {
            return back()->with('error', 'Срок для редактирования отзыва истёк');
        }

        $maxPhotos = $this->reviewImages->maxPhotos();

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'keep_image_ids' => 'nullable|array|max:'.$maxPhotos,
            'keep_image_ids.*' => 'integer',
            'photos' => 'nullable|array|max:'.$maxPhotos,
            'photos.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_moderated' => false,
            'moderation_comment' => null,
            'moderated_at' => null,
            'moderated_by' => null,
        ]);

        $keepIds = array_map('intval', $request->input('keep_image_ids', []));
        $keepIds = array_values(array_filter($keepIds, fn ($id) => $review->images()->where('id', $id)->exists()));

        $photos = $request->file('photos', []);
        if (! is_array($photos)) {
            $photos = $photos ? [$photos] : [];
        }

        $this->reviewImages->sync($review, $photos, $keepIds);

        return back()->with('success', 'Отзыв обновлён и отправлен на модерацию');
    }

    public function destroy(Review $review)
    {
        if ($review->user_id !== auth()->id()) {
            abort(403);
        }

        $this->reviewImages->deleteForReview($review);
        $review->delete();

        return back()->with('success', 'Отзыв удалён');
    }
}
