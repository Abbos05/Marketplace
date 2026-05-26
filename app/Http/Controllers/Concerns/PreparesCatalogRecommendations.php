<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Services\CatalogFilterService;
use App\Services\HomeCatalogFeedService;
use Illuminate\Support\Collection;

trait PreparesCatalogRecommendations
{
    /**
     * @param  array{
     *     limit?: int,
     *     popular_count?: int,
     *     exclude_product_ids?: array<int>,
     *     exclude_category_ids?: array<int>,
     * }  $options
     * @return Collection<int, Product>
     */
    protected function catalogRecommendations(array $options = []): Collection
    {
        $feed = app(HomeCatalogFeedService::class);
        $catalogFilters = app(CatalogFilterService::class);

        $products = $feed->buildRecommendations($options);
        Product::enrichForCatalog($products);
        $catalogFilters->markFavorites($products);

        return $products;
    }
}
