<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
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

        $sellerUser = User::query()
            ->with('sellerProfile')
            ->whereKey($sellerId)
            ->first();

        if (! $sellerUser?->sellerProfile) {
            abort(404);
        }

        $baseFactory = fn () => Product::forCatalogPresentation()
            ->where('products.seller_id', $sellerId)
            ->visibleInCatalog();

        $result = $this->catalogFilters->process($request, $baseFactory, [
            'fixed_seller_id' => $sellerId,
            'allow_category_facet' => true,
            'search_fields' => ['title', 'short_description'],
        ]);

        $products = $result['products'];
        $this->catalogFilters->markFavorites($products);

        $reviewStats = Review::query()
            ->where('is_moderated', true)
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->selectRaw('COUNT(*) as reviews_count, AVG(rating) as avg_rating')
            ->first();

        $reviewsCount = (int) ($reviewStats->reviews_count ?? 0);
        $avgRating = $reviewStats->avg_rating !== null
            ? round((float) $reviewStats->avg_rating, 1)
            : null;

        $shopFavoritesCount = DB::table('favorites')
            ->join('products', 'favorites.product_id', '=', 'products.id')
            ->where('products.seller_id', $sellerId)
            ->count();

        $profile = $sellerUser->sellerProfile;

        return Inertia::render('SellerProfile/Seller', [
            'sellerId' => $sellerId,
            'products' => $products,
            'seller' => [
                'id' => $sellerId,
                'name' => $profile->shop_name ?: ($sellerUser->name ?? 'Продавец'),
                'img' => $sellerUser->avatar,
                'rating' => $avgRating,
                'reviews_count' => $reviewsCount,
                'orders' => (int) ($profile->total_sales ?? 0),
                'likes' => (int) $shopFavoritesCount,
            ],
            'filters' => $result['filters'],
            'facets' => $result['facets'],
            'total' => $result['total'],
            'pagination' => $result['pagination'],
        ]);
    }
}
