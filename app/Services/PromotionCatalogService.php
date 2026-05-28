<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Support\Collection;

class PromotionCatalogService
{
    /**
     * @return array<int, list<array{label: string, title: string}>>
     */
    public function badgesForProducts(Collection $products): array
    {
        $ids = $products->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return [];
        }

        $promotions = Promotion::query()
            ->active()
            ->with(['products' => fn ($q) => $q->whereIn('products.id', $ids)])
            ->get();

        $map = [];
        foreach ($promotions as $promotion) {
            foreach ($promotion->products as $product) {
                $map[$product->id][] = [
                    'label' => $promotion->badge_label,
                    'title' => $promotion->badge_label,
                ];
            }
        }

        return $map;
    }

    public function attachBadgesToListing(Collection $products): Collection
    {
        $badges = $this->badgesForProducts($products);

        return $products->map(function ($product) use ($badges) {
            $product->promotion_badges = $badges[$product->id] ?? [];

            return $product;
        });
    }
}
