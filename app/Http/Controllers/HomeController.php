<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\Product;
use App\Services\CatalogFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct(
        private CatalogFilterService $catalogFilters,
    ) {}

    public function index(Request $request, $returnDataOnly = false, $id = null)
    {
        $search = request('search');
        $category = Category::rootsForCatalogNav();

        if ($search) {
            $baseFactory = fn () => Product::forCatalogPresentation()
                ->where('products.is_on_action', 1);

            $result = $this->catalogFilters->process($request, $baseFactory, [
                'allow_category_facet' => true,
                'search_fields' => ['title', 'description', 'short_description'],
                'limit' => 50,
            ]);

            $products = $result['products'];
            $filters = $result['filters'];
            $facets = $result['facets'];
            $total = $result['total'];
            $sort = $filters['sort'];
            $this->catalogFilters->markFavorites($products);
        } else {
            $products = Product::forCatalogPresentation()
                ->where('is_on_action', 1)
                ->orderByDesc('created_at')
                ->take(50)
                ->get();
            Product::enrichForCatalog($products);
            $filters = $this->catalogFilters->parseFilters($request);
            $facets = ['price' => ['min' => null, 'max' => null], 'categories' => [], 'attributes' => []];
            $total = $products->count();
            $sort = $filters['sort'];
            $this->catalogFilters->markFavorites($products);
        }

        $homeSlides = HomeSlide::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (HomeSlide $s) => $s->toFrontendArray())
            ->values()
            ->all();

        // Рекомендуемые товары
        $LikeProducts = Product::forCatalogPresentation()
            ->where('status', 'approved')
            ->take(12)
            ->get();
        Product::enrichForCatalog($LikeProducts);

        $this->catalogFilters->markFavorites($LikeProducts);

        $data = [
            'LikeProducts' => $LikeProducts,
            'category' => $category,
            'auth' => auth()->user() ? ['user' => auth()->user()] : ['user' => null],
            'canResetPassword' => true,
            'mysqlNftsData' => $products,
            'categoryData' => $category,
            'search' => $search,
            'sort' => $sort,
            'filters' => $filters,
            'facets' => $facets,
            'total' => $total,
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
            ->where('is_on_action', 1);

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

        $bestNfts = Product::forCatalogPresentation()
            ->where('is_on_action', 1)
            ->orderBy('created_at', 'desc')
            ->take(9)
            ->get();
        Product::enrichForCatalog($bestNfts);

        $category = Category::rootsForCatalogNav();

        $homeSlides = HomeSlide::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (HomeSlide $s) => $s->toFrontendArray())
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

    public function favorites(Product $product)
    {
        $user = Auth::user();
        if ($user) {
            $user->favorites()->toggle($product->id);
        }

        // Важно: возвращаемся с обновлёнными данными
        return back();
    }
}
