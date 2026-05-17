<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Http\Controllers\Concerns\RedirectsArticleSearch;
use App\Services\CatalogFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SellerController extends Controller
{
    use RedirectsArticleSearch;

    public function __construct(
        private CatalogFilterService $catalogFilters,
    ) {}

    public function index($id, Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        if ($search !== '' && ($redirect = $this->redirectIfArticleSearch($search))) {
            return $redirect;
        }

        $sellerId = (int) $id;

        $baseFactory = fn () => Product::forCatalogPresentation()
            ->where('products.seller_id', $sellerId)
            ->visibleInCatalog();

        $result = $this->catalogFilters->process($request, $baseFactory, [
            'allow_category_facet' => true,
            'search_fields' => ['title', 'short_description'],
            'limit' => $request->filled('search')
                ? (int) config('marketplace.catalog_search_limit', 10)
                : null,
        ]);

        $products = $result['products'];
        $this->catalogFilters->markFavorites($products);

        $sellerUser = User::query()->with('sellerProfile')->find($sellerId);

        $shopFavoritesCount = DB::table('favorites')
            ->join('products', 'favorites.product_id', '=', 'products.id')
            ->where('products.seller_id', $sellerId)
            ->count();

        return Inertia::render('SellerProfile/Seller', [
            'products' => $products,
            'seller' => [
                'id' => $sellerId,
                'name' => $sellerUser?->sellerProfile?->shop_name ?: ($sellerUser?->name ?? 'Продавец'),
                'img' => $sellerUser?->avatar,
                'rating' => $sellerUser?->sellerProfile?->rating ?? '5.0',
                'review' => 0,
                'orders' => $sellerUser?->sellerProfile?->total_sales ?? 0,
                'likes' => (int) $shopFavoritesCount,
            ],
            'filters' => $result['filters'],
            'facets' => $result['facets'],
            'total' => $result['total'],
        ]);
    }
}
