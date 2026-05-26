<?php

namespace App\Services;

use App\Models\Product;
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
                    'title' => $promotion->title,
                ];
            }
        }

        return $map;
    }

    /**
     * @return Collection<int, Product>
     */
    public function featuredProducts(int $limit = 12): Collection
    {
        $productIds = Promotion::query()
            ->active()
            ->featured()
            ->with('products:id')
            ->get()
            ->flatMap(fn (Promotion $p) => $p->products->pluck('id'))
            ->unique()
            ->take($limit * 2)
            ->values()
            ->all();

        if ($productIds === []) {
            $productIds = Promotion::query()
                ->active()
                ->with('products:id')
                ->latest('id')
                ->limit(3)
                ->get()
                ->flatMap(fn (Promotion $p) => $p->products->pluck('id'))
                ->unique()
                ->values()
                ->all();
        }

        if ($productIds === []) {
            return collect();
        }

        return Product::forCatalogPresentation()
            ->visibleInCatalog()
            ->whereIn('products.id', $productIds)
            ->limit($limit)
            ->get();
    }

    public function attachBadgesToListing(Collection $products): Collection
    {
        $badges = $this->badgesForProducts($products);

        return $products->map(function (Product $product) use ($badges) {
            $product->promotion_badges = $badges[$product->id] ?? [];

            return $product;
        });
    }
}
