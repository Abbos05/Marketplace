<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Http\Request;
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
                'badge_label' => $p->badge_label,
                'status' => $p->status,
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
            'badge_label' => 'required|string|max:64',
            'ends_at' => 'nullable|date',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $promotion = Promotion::create([
            'badge_label' => $data['badge_label'],
            'starts_at' => now(),
            'ends_at' => $data['ends_at'] ?? null,
            'status' => Promotion::STATUS_ACTIVE,
            'created_by' => Promotion::CREATED_BY_ADMIN,
            'seller_id' => null,
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
}
