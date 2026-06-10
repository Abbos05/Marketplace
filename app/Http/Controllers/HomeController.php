<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Http\Controllers\Concerns\PreparesCatalogRecommendations;
use App\Http\Controllers\Concerns\RedirectsArticleSearch;
use App\Services\CatalogFilterService;
use App\Services\HomeCatalogFeedService;
use App\Services\PromotionCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HomeController extends Controller
{
    use PreparesCatalogRecommendations;
    use RedirectsArticleSearch;

    public function __construct(
        private CatalogFilterService $catalogFilters,
        private HomeCatalogFeedService $homeFeed,
        private PromotionCatalogService $promotionCatalog,
    ) {
    }

    public function index(Request $request, $returnDataOnly = false, $id = null)
    {
        $search = request('search');
        $category = Category::rootsForCatalogNav();
        if ($search) {
            if ($redirect = $this->redirectIfArticleSearch($search)) {
                return $redirect;
            }
            $baseFactory = fn() => Product::forCatalogPresentation()
                ->visibleInCatalog();

            $result = $this->catalogFilters->process($request, $baseFactory, [
                'allow_category_facet' => true,
                'search_fields' => ['title', 'short_description'],
            ]);

            $products = $result['products'];

            $filters = $result['filters'];
            $facets = $result['facets'];
            $total = $result['total'];
            $pagination = $result['pagination'];
            $sort = $filters['sort'];
            $this->catalogFilters->markFavorites($products);
        } else {
            $products = $this->homeFeed->build();

            Product::enrichForCatalog($products);
            $filters = $this->catalogFilters->parseFilters($request);
            $facets = ['price' => ['min' => null, 'max' => null], 'categories' => [], 'attributes' => []];
            $total = $products->count();
            $pagination = null;
            $sort = $filters['sort'];
            $this->catalogFilters->markFavorites($products);
            $products = $products->shuffle();

        }

        $homeSlides = HomeSlide::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn(HomeSlide $s) => $s->toFrontendArray())
            ->values()
            ->all();



        // Получаем ID избранных товаров
        $favoriteProductIds = DB::table('favorites')
            ->where('user_id', $request?->user()?->id)
            ->pluck('product_id')
            ->unique()
            ->values()
            ->toArray();

        // Передаём их в рекомендации для исключения
        $LikeProducts = $this->catalogRecommendations([
            'exclude_product_ids' => $favoriteProductIds,
            'limit' => '80'

        ]);

        // Перемешиваем
        $LikeProducts = $LikeProducts->shuffle();
        $data = [
            'LikeProducts' => $LikeProducts,
            'category' => $category,
            'auth' => auth()->user() ? ['user' => auth()->user()] : ['user' => null],
            'canResetPassword' => true,
            'mysqlProductsData' => $products,
            'categoryData' => $category,
            'search' => $search,
            'sort' => $sort,
            'filters' => $filters,
            'facets' => $facets,
            'total' => $total,
            'pagination' => $pagination ?? null,
            'homeSlides' => $homeSlides,
        ];

        if ($returnDataOnly)
            return $data;
        return Inertia::render('Home', $data);
    }

    public function category($returnDataOnly = false)
    {
        $search = request('search');
        $sort = request('sort', 'price_desc');


        $validSorts = ['price_desc', 'price_asc', 'date_desc', 'date_asc'];
        $sort = in_array($sort, $validSorts) ? $sort : 'price_desc';


        $query = Product::forCatalogPresentation()
            ->visibleInCatalog();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        switch ($sort) {
            case 'price_asc':
                $query->orderBy('min_price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('min_price', 'desc');
                break;
            case 'date_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'date_desc':
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('min_price', 'desc');
        }

        $products = $query->take(50)->get();
        Product::enrichForCatalog($products);

        $bestProducts = Product::forCatalogPresentation()
            ->visibleInCatalog()
            ->orderBy('created_at', 'desc')
            ->take(9)
            ->get();
        Product::enrichForCatalog($bestProducts);

        $category = Category::rootsForCatalogNav();

        $homeSlides = HomeSlide::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn(HomeSlide $s) => $s->toFrontendArray())
            ->values()
            ->all();

        $data = [
            'auth' => auth()->user() ? ['user' => auth()->user()] : ['user' => null],
            'canResetPassword' => true,
            'categoryData' => $category,
            'search' => $search,
            'sort' => $sort,
            'homeSlides' => $homeSlides,
        ];

        if ($returnDataOnly)
            return $data;

        return Inertia::render('Product/CategoryMenu', $data);
    }

    public function favorites(Request $request, Product $product)
    {
        $user = Auth::user();

        if (!$product->canBeViewedBy($user)) {
            abort(404);
        }

        if ($user) {
            $data = $request->validate([
                'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            ], [
                'variant_id.integer' => 'ID варианта товара должен быть числом.',
                'variant_id.exists' => 'Выбранный вариант товара не существует.',
            ]);

            $variantId = $data['variant_id'] ?? null;
            if ($variantId !== null) {
                $variantBelongsToProduct = ProductVariant::query()
                    ->whereKey($variantId)
                    ->where('product_id', $product->id)
                    ->exists();

                abort_unless($variantBelongsToProduct, 404);
            }

            $favoriteQuery = DB::table('favorites')
                ->where('user_id', $user->id)
                ->where('product_id', $product->id);

            $variantId === null
                ? $favoriteQuery->whereNull('variant_id')
                : $favoriteQuery->where('variant_id', $variantId);

            $favoriteExists = $favoriteQuery->exists();

            if ($favoriteExists) {
                $favoriteQuery->delete();
            } else {
                DB::table('favorites')->insert([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'variant_id' => $variantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ✅ Вместо return back() используем:
        return redirect()->back()->with('success', true);
        // Или если у вас Inertia:
        // return back()->with('success', true);
    }
}
