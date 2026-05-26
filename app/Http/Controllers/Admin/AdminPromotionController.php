<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminPromotionController extends Controller
{
    public function index()
    {
        $promotions = Promotion::query()
            ->withCount('products')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Promotion $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'badge_label' => $p->badge_label,
                'status' => $p->status,
                'is_featured' => (bool) $p->is_featured,
                'products_count' => (int) $p->products_count,
                'starts_at' => $p->starts_at?->format('d.m.Y'),
                'ends_at' => $p->ends_at?->format('d.m.Y'),
                'created_by' => $p->created_by,
                'is_active_now' => $p->isCurrentlyActive(),
            ]);

        $products = Product::query()
            ->where('status', 'approved')
            ->where('is_on_action', true)
            ->orderBy('title')
            ->limit(200)
            ->get(['id', 'title']);

        return Inertia::render('Admin/Promotions', [
            'promotions' => $promotions,
            'products' => $products,
            'flash' => session()->only(['success', 'error']),
        ]);
    }

    public function store(Request $request)
    {
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

        $slug = $this->uniqueSlug($data['title']);

        $promotion = Promotion::create([
            'title' => $data['title'],
            'slug' => $slug,
            'badge_label' => $data['badge_label'],
            'description' => $data['description'] ?? null,
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? null,
            'status' => Promotion::STATUS_ACTIVE,
            'created_by' => Promotion::CREATED_BY_ADMIN,
            'seller_id' => null,
            'is_featured' => $request->boolean('is_featured'),
        ]);

        $promotion->products()->sync($data['product_ids']);

        return back()->with('success', 'Акция создана.');
    }

    public function toggle(Promotion $promotion)
    {
        $newStatus = $promotion->status === Promotion::STATUS_ACTIVE
            ? Promotion::STATUS_DRAFT
            : Promotion::STATUS_ACTIVE;
        $promotion->update(['status' => $newStatus]);

        return back()->with('success', 'Статус акции обновлён.');
    }

    public function destroy(Promotion $promotion)
    {
        $promotion->delete();

        return back()->with('success', 'Акция удалена.');
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'promo';
        $slug = $base;
        $i = 1;
        while (Promotion::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
