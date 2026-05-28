<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Http\Controllers\Concerns\PreparesCatalogRecommendations;
use App\Http\Controllers\Concerns\RedirectsArticleSearch;
use App\Services\CatalogFilterService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    use PreparesCatalogRecommendations;
    use RedirectsArticleSearch;

    public function __construct(
        private CatalogFilterService $catalogFilters,
    ) {}

    public function show($id, Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        if ($search !== '' && ($redirect = $this->redirectIfArticleSearch($search))) {
            return $redirect;
        }

        $category = Category::query()
            ->where('is_active', true)
            ->with(['parent' => fn ($q) => $q->select('id', 'name', 'parent_id')])
            ->findOrFail((int) $id);

        $listed = fn ($productQuery) => $productQuery->visibleInCatalog();

        $activeChildren = $category->children()
            ->where('is_active', true)
            ->whereHas('products', $listed)
            ->orderBy('name')
            ->get();

        $showSubcategories = $activeChildren->isNotEmpty();

        $breadcrumbs = [
            ['label' => 'Каталог', 'href' => url('/category')],
        ];
        if ($category->parent_id && $category->parent) {
            $breadcrumbs[] = [
                'label' => $category->parent->name,
                'href' => route('category.show', $category->parent->id),
            ];
        }
        $breadcrumbs[] = ['label' => $category->name, 'href' => null];

        $subcategories = $activeChildren->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
        ])->values()->all();

        if ($showSubcategories) {
            $products = collect();
            $filters = $this->catalogFilters->parseFilters($request, ['fixed_category_id' => null]);
            $facets = ['price' => ['min' => null, 'max' => null], 'categories' => [], 'attributes' => []];
            $total = 0;
            $pagination = null;
            $excludeCategoryIds = $activeChildren->pluck('id')->push($category->id)->all();
        } else {
            $baseFactory = fn () => Product::forCatalogPresentation()
                ->where('products.category_id', $category->id)
                ->visibleInCatalog();

            $result = $this->catalogFilters->process($request, $baseFactory, [
                'fixed_category_id' => (int) $category->id,
                'allow_category_facet' => false,
                'search_fields' => ['title', 'short_description'],
            ]);

            $products = $result['products'];
            $filters = $result['filters'];
            $facets = $result['facets'];
            $total = $result['total'];
            $pagination = $result['pagination'];

            $excludeCategoryIds = [(int) $category->id];
        }

        $LikeProducts = $this->catalogRecommendations([
            'exclude_category_ids' => $excludeCategoryIds,
        ]);

        $this->catalogFilters->markFavorites($products);

        return Inertia::render('CategoryPage', [
            'category' => $category->only(['id', 'name', 'slug', 'parent_id']),
            'showSubcategories' => $showSubcategories,
            'subcategories' => $subcategories,
            'breadcrumbs' => $breadcrumbs,
            'products' => $products,
            'LikeProducts' => $LikeProducts,
            'filters' => $filters,
            'facets' => $facets,
            'total' => $total,
            'pagination' => $pagination ?? null,
        ]);
    }
}
