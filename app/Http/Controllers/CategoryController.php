<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogFilterService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct(
        private CatalogFilterService $catalogFilters,
    ) {}

    public function show($id, Request $request)
    {
        $category = Category::query()
            ->where('is_active', true)
            ->with(['parent' => fn ($q) => $q->select('id', 'name', 'parent_id')])
            ->findOrFail((int) $id);

        $listed = fn ($productQuery) => $productQuery->where('is_on_action', 1);

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
            'icon' => Product::normalizeListingUrl($c->icon) ?: '/img/products/default.png',
        ])->values()->all();

        if ($showSubcategories) {
            $products = collect();
            $filters = $this->catalogFilters->parseFilters($request, ['fixed_category_id' => null]);
            $facets = ['price' => ['min' => null, 'max' => null], 'categories' => [], 'attributes' => []];
            $total = 0;
            $LikeProducts = Product::forCatalogPresentation()
                ->where('is_on_action', 1)
                ->whereNotIn('category_id', $activeChildren->pluck('id')->merge([$category->id]))
                ->limit(20)
                ->get();
        } else {
            $baseFactory = fn () => Product::forCatalogPresentation()
                ->where('products.category_id', $category->id)
                ->where('products.is_on_action', 1);

            $result = $this->catalogFilters->process($request, $baseFactory, [
                'fixed_category_id' => (int) $category->id,
                'allow_category_facet' => false,
                'search_fields' => ['title', 'short_description'],
            ]);

            $products = $result['products'];
            $filters = $result['filters'];
            $facets = $result['facets'];
            $total = $result['total'];

            $LikeProducts = Product::forCatalogPresentation()
                ->where('is_on_action', 1)
                ->where('category_id', '!=', $category->id)
                ->limit(20)
                ->get();
        }

        Product::enrichForCatalog($LikeProducts);

        $this->catalogFilters->markFavorites($products);
        $this->catalogFilters->markFavorites($LikeProducts);

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
        ]);
    }
}
