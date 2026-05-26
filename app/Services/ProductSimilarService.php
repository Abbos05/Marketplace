<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductSimilarService
{
    public function __construct(
        protected HomeCatalogFeedService $feed
    ) {}

    /**
     * @return Collection<int, Product>
     */
    public function forProduct(Product $product, ?int $limit = null): Collection
    {
        $limit = $limit ?? (int) config('marketplace.similar_products.limit', 60);
        $limit = max(1, $limit);
        $excludeId = (int) $product->id;

        $similar = Product::forCatalogPresentation()
            ->visibleInCatalog()
            ->where('products.category_id', $product->category_id)
            ->where('products.id', '!=', $excludeId)
            ->orderByCatalogPopularity()
            ->limit($limit)
            ->get();

        if ($similar->count() >= min(20, $limit)) {
            return $similar->take($limit)->values();
        }

        $pickedIds = array_merge([$excludeId], $similar->pluck('id')->all());
        $remaining = $limit - $similar->count();

        $more = $this->feed->buildRecommendations([
            'limit' => $remaining,
            'exclude_product_ids' => $pickedIds,
        ]);

        return $similar->concat($more)->take($limit)->values();
    }
}
