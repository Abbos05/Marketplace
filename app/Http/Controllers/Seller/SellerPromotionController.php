<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SellerPromotionController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $promotions = Promotion::query()
            ->where('seller_id', $user->id)
            ->withCount('products')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Promotion $p) => $this->format($p));

        $products = Product::query()
            ->where('seller_id', $user->id)
            ->where('status', 'approved')
            ->where('is_on_action', true)
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Seller/Promotions/Index', [
            'promotions' => $promotions,
            'products' => $products,
            'flash' => session()->only(['success', 'error']),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'title' => 'required|string|max:120',
            'badge_label' => 'required|string|max:64',
            'description' => 'nullable|string|max:2000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
            'is_featured' => 'boolean',
        ]);

        $productIds = Product::query()
            ->where('seller_id', $user->id)
            ->whereIn('id', $data['product_ids'])
            ->pluck('id');

        if ($productIds->isEmpty()) {
            return back()->withErrors(['product_ids' => 'Выберите хотя бы один ваш товар.']);
        }

        $slug = $this->uniqueSlug($data['title']);

        $promotion = Promotion::create([
            'title' => $data['title'],
            'slug' => $slug,
            'badge_label' => $data['badge_label'],
            'description' => $data['description'] ?? null,
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? null,
            'status' => Promotion::STATUS_ACTIVE,
            'created_by' => Promotion::CREATED_BY_SELLER,
            'seller_id' => $user->id,
            'is_featured' => $request->boolean('is_featured'),
        ]);

        $promotion->products()->sync($productIds);

        return redirect()->route('seller.promotions')->with('success', 'Акция создана.');
    }

    public function toggle(Promotion $promotion)
    {
        $this->authorizePromotion($promotion);

        $newStatus = $promotion->status === Promotion::STATUS_ACTIVE
            ? Promotion::STATUS_DRAFT
            : Promotion::STATUS_ACTIVE;

        $promotion->update(['status' => $newStatus]);

        return back()->with('success', $newStatus === Promotion::STATUS_ACTIVE ? 'Акция активирована.' : 'Акция приостановлена.');
    }

    public function destroy(Promotion $promotion)
    {
        $this->authorizePromotion($promotion);
        $promotion->delete();

        return back()->with('success', 'Акция удалена.');
    }

    private function authorizePromotion(Promotion $promotion): void
    {
        if ((int) $promotion->seller_id !== (int) Auth::id()) {
            abort(403);
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'promo';
        }
        $slug = $base;
        $i = 1;
        while (Promotion::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function format(Promotion $p): array
    {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'badge_label' => $p->badge_label,
            'description' => $p->description,
            'starts_at' => $p->starts_at?->format('d.m.Y H:i'),
            'ends_at' => $p->ends_at?->format('d.m.Y H:i'),
            'status' => $p->status,
            'is_featured' => (bool) $p->is_featured,
            'products_count' => (int) $p->products_count,
            'is_active_now' => $p->isCurrentlyActive(),
        ];
    }
}
