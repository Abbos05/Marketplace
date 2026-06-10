<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HomeCatalogFeedService
{
    /**
     * Лента главной: новинки → популярные по просмотрам → остальное по статистике.
     *
     * @return Collection<int, Product>
     */
    public function build(): Collection
    {
        $newCount = max(0, (int) config('marketplace.home_feed.new_count', 6));
        $popularCount = max(0, (int) config('marketplace.home_feed.popular_count', 8));
        $limit = max(1, (int) config('marketplace.home_feed.limit', 0));

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
            $more = $this->applyPopularityOrder(
                $base()->whereNotIn('products.id', $excludeIds ?: [0])
            )
                ->limit($remaining)
                ->get();
            $picked = $picked->concat($more);
        }

        return $picked->take($limit)->values();
    }

    /**
     * Рекомендации для блока «Возможно, вам понравится»: популярные по статистике + остальное.
     *
     * @param  array{
     *     limit?: int,
     *     popular_count?: int,
     *     exclude_product_ids?: array<int>,
     *     exclude_category_ids?: array<int>,
     * }  $options
     * @return Collection<int, Product>
     */
    public function buildRecommendations(array $options = []): Collection
    {
        $popularCount = max(0, (int) ($options['popular_count'] ?? config('marketplace.home_feed.recommendations_popular_count', 6)));
        $limit = max(0, (int) ($options['limit'] ?? 0));

        $excludeProductIds = array_values(array_unique(array_map('intval', $options['exclude_product_ids'] ?? [])));
        $excludeCategoryIds = array_values(array_unique(array_map('intval', $options['exclude_category_ids'] ?? [])));

        $base = function () use ($excludeProductIds, $excludeCategoryIds): Builder {
            $query = Product::forCatalogPresentation()->visibleInCatalog();

            if ($excludeProductIds !== []) {
                $query->whereNotIn('products.id', $excludeProductIds);
            }

            if ($excludeCategoryIds !== []) {
                $query->whereNotIn('products.category_id', $excludeCategoryIds);
            }

            return $query;
        };

        $picked = collect();
        $pickedIds = $excludeProductIds;

        if ($popularCount > 0) {
            $popular = $this->applyPopularityOrder($base())
                ->limit($popularCount)
                ->get();
            $picked = $picked->concat($popular);
            $pickedIds = array_merge($pickedIds, $popular->pluck('id')->all());
        }

        $remaining = max(0, $limit - $picked->count());
        if ($remaining > 0) {
            $more = $this->applyPopularityOrder(
                $base()->whereNotIn('products.id', $pickedIds ?: [0])
            )
                ->limit($remaining)
                ->get();
            $picked = $picked->concat($more);
        }

        return $picked->take($limit)->values();
    }

    protected function applyPopularityOrder(Builder $query): Builder
    {
        return $query->orderByCatalogPopularity();
    }
}
