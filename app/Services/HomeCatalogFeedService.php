<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HomeCatalogFeedService
{
    /**
     * Лента главной: новинки → популярные → остальное в случайном порядке.
     *
     * @return Collection<int, Product>
     */
    public function build(): Collection
    {
        $newCount = max(0, (int) config('marketplace.home_feed.new_count', 6));
        $popularCount = max(0, (int) config('marketplace.home_feed.popular_count', 8));
        $limit = max(1, (int) config('marketplace.home_feed.limit', 50));

        $base = fn (): Builder => Product::forCatalogPresentation()->visibleInCatalog();

        $picked = collect();
        $excludeIds = [];

        if ($newCount > 0) {
            $new = $base()
                ->orderByDesc('products.created_at')
                ->limit($newCount)
                ->get();
            $picked = $picked->concat($new);
            $excludeIds = $new->pluck('id')->all();
        }

        if ($popularCount > 0) {
            $popularQuery = $base()->whereNotIn('products.id', $excludeIds ?: [0]);
            $popular = $this->applyPopularityOrder($popularQuery)
                ->limit($popularCount)
                ->get();
            $picked = $picked->concat($popular);
            $excludeIds = array_merge($excludeIds, $popular->pluck('id')->all());
        }

        $remaining = max(0, $limit - $picked->count());
        if ($remaining > 0) {
            $random = $base()
                ->whereNotIn('products.id', $excludeIds ?: [0])
                ->inRandomOrder()
                ->limit($remaining)
                ->get();
            $picked = $picked->concat($random);
        }

        return $picked->take($limit)->values();
    }

    /**
     * Рекомендации: популярное + случайное (без дубля блока новинок).
     *
     * @return Collection<int, Product>
     */
    public function buildRecommendations(): Collection
    {
        $popularCount = max(0, (int) config('marketplace.home_feed.recommendations_popular_count', 6));
        $limit = max(1, (int) config('marketplace.home_feed.recommendations_limit', 12));

        $base = fn (): Builder => Product::forCatalogPresentation()->visibleInCatalog();

        $picked = collect();
        $excludeIds = [];

        if ($popularCount > 0) {
            $popular = $this->applyPopularityOrder($base())
                ->limit($popularCount)
                ->get();
            $picked = $picked->concat($popular);
            $excludeIds = $popular->pluck('id')->all();
        }

        $remaining = max(0, $limit - $picked->count());
        if ($remaining > 0) {
            $random = $base()
                ->whereNotIn('products.id', $excludeIds ?: [0])
                ->inRandomOrder()
                ->limit($remaining)
                ->get();
            $picked = $picked->concat($random);
        }

        return $picked->take($limit)->values();
    }

    protected function applyPopularityOrder(Builder $query): Builder
    {
        return $query
            ->orderByDesc('products.sales_count')
            ->orderByDesc('reviews_count')
            ->orderByDesc('products.views_count')
            ->orderByDesc('products.created_at');
    }
}
