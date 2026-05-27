<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CatalogFilterService
{
    public function __construct(
        private readonly ArticleNumberService $articles,
        private readonly CatalogTextSearchService $textSearch,
    ) {}

    public const SORT_RELEVANCE = 'relevance';

    public const SORT_NEW = 'new';

    public const SORT_OLD = 'old';

    public const SORT_CHEAP = 'cheap';

    public const SORT_EXPENSIVE = 'expensive';

    public const SORT_POPULAR = 'popular';

    public const SORT_RATING = 'rating';

    private const LEGACY_SORT_MAP = [
        'price_desc' => self::SORT_EXPENSIVE,
        'price_asc' => self::SORT_CHEAP,
        'date_desc' => self::SORT_NEW,
        'date_asc' => self::SORT_OLD,
    ];

  private const ALLOWED_SORTS = [
        self::SORT_RELEVANCE,
        self::SORT_NEW,
        self::SORT_OLD,
        self::SORT_CHEAP,
        self::SORT_EXPENSIVE,
        self::SORT_POPULAR,
        self::SORT_RATING,
    ];

    /**
     * @param  callable(): Builder  $baseQueryFactory
     * @param  array{
     *   fixed_category_id?: int|null,
     *   fixed_seller_id?: int|null,
     *   allow_category_facet?: bool,
     *   search_fields?: array<string>,
     * }  $context
     * @return array{
     *   products: Collection,
     *   filters: array,
     *   facets: array,
     *   total: int,
     *   pagination: array{page: int, per_page: int, has_more: bool, product_total: int}
     * }
     */
    public function process(Request $request, callable $baseQueryFactory, array $context = []): array
    {
        $context = array_merge([
            'fixed_category_id' => null,
            'fixed_seller_id' => null,
            'allow_category_facet' => false,
            'search_fields' => ['title'],
        ], $context);

        $parsed = $this->parseFilters($request, $context);
        $attributeCategoryId = $this->resolveAttributeCategoryId($context, $parsed);

        $query = $baseQueryFactory();
        $this->applyFilters($query, $parsed, $context, except: null);

        $total = $this->countListingPositions($query, $parsed);
        $productTotal = (clone $query)->count('products.id');

        $perPage = max(1, (int) config('marketplace.catalog_per_page', 24));
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $products = (clone $query)
            ->offset($offset)
            ->limit($perPage)
            ->get();

        Product::enrichForCatalog($products, [
            'price_from' => $parsed['price_from'],
            'price_to' => $parsed['price_to'],
        ]);

        $facets = [
            'price' => $this->buildPriceFacet($baseQueryFactory, $parsed, $context),
            'rating' => $this->buildRatingFacets($baseQueryFactory, $parsed, $context),
            'categories' => $context['allow_category_facet']
                ? $this->buildCategoryFacets($baseQueryFactory, $parsed, $context)
                : [],
            'attributes' => $attributeCategoryId
                ? $this->buildAttributeFacets($baseQueryFactory, $parsed, $context, $attributeCategoryId)
                : [],
        ];

        return [
            'products' => $products,
            'filters' => $this->filtersForFrontend($parsed, $page),
            'facets' => $facets,
            'total' => $total,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => ($offset + $products->count()) < $productTotal,
                'product_total' => $productTotal,
            ],
        ];
    }

    public function normalizeSort(?string $sort, bool $hasSearch = false): string
    {
        if ($sort === null || $sort === '') {
            return $hasSearch ? self::SORT_RELEVANCE : self::SORT_NEW;
        }

        if (isset(self::LEGACY_SORT_MAP[$sort])) {
            return self::LEGACY_SORT_MAP[$sort];
        }

        if (in_array($sort, self::ALLOWED_SORTS, true)) {
            if ($sort === self::SORT_RELEVANCE && ! $hasSearch) {
                return self::SORT_NEW;
            }

            return $sort;
        }

        return $hasSearch ? self::SORT_RELEVANCE : self::SORT_NEW;
    }

    /**
     * @return array{
     *   search: ?string,
     *   sort: string,
     *   price_from: ?string,
     *   price_to: ?string,
     *   category_id: ?int,
     *   on_promotion: bool,
     *   rating_min: ?int,
     *   page: int,
     *   attributes: array<int, array{values?: array<string>, min?: ?string, max?: ?string}>
     * }
     */
    public function parseFilters(Request $request, array $context = []): array
    {
        $categoryId = $context['fixed_category_id'] ?? null;
        if (! $categoryId && $request->filled('category_id')) {
            $categoryId = (int) $request->query('category_id');
        }

        $search = trim((string) $request->query('search', ''));
        $hasSearch = $search !== '';

        $ratingMin = $request->query('rating_min');
        $ratingMin = in_array((int) $ratingMin, [4, 5], true) ? (int) $ratingMin : null;

        return [
            'search' => $hasSearch ? $search : null,
            'sort' => $this->normalizeSort($request->query('sort'), $hasSearch),
            'price_from' => $request->query('price_from'),
            'price_to' => $request->query('price_to'),
            'category_id' => $categoryId ? (int) $categoryId : null,
            'on_promotion' => $request->boolean('on_promotion'),
            'rating_min' => $ratingMin,
            'page' => max(1, (int) $request->query('page', 1)),
            'attributes' => $this->parseAttributeFilters($request),
        ];
    }

    /**
     * @return array<int, array{values?: array<string>, min?: ?string, max?: ?string}>
     */
    public function parseAttributeFilters(Request $request): array
    {
        $raw = $request->input('attr', []);
        if (! is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $attrId => $payload) {
            $attrId = (int) $attrId;
            if ($attrId <= 0) {
                continue;
            }

            if (is_array($payload) && (array_key_exists('min', $payload) || array_key_exists('max', $payload))) {
                $result[$attrId] = [
                    'min' => $payload['min'] ?? null,
                    'max' => $payload['max'] ?? null,
                ];
                continue;
            }

            $values = is_array($payload) ? $payload : [$payload];
            $values = array_values(array_filter(array_map('strval', $values), fn ($v) => $v !== ''));
            if ($values !== []) {
                $result[$attrId] = ['values' => $values];
            }
        }

        return $result;
    }

    /**
     * @param  array  $parsed
     * @param  array|null  $except  keys: price, category_id, rating, on_promotion, sort, attribute_id (int)
     */
    public function applyFilters(Builder $query, array $parsed, array $context, ?array $except = null): void
    {
        [$skip, $exceptAttrId] = $this->parseExcept($except);

        if ($parsed['search'] && ! in_array('search', $skip, true)) {
            $this->applySearch($query, $parsed['search'], $context['search_fields'] ?? ['title']);
        }

        if (! empty($context['fixed_seller_id'])) {
            $query->where('products.seller_id', (int) $context['fixed_seller_id']);
        }

        if ($parsed['category_id'] && ! in_array('category_id', $skip, true)) {
            $query->where('products.category_id', $parsed['category_id']);
        }

        if (! in_array('price', $skip, true)) {
            $this->applyVariantPriceFilter($query, $parsed['price_from'], $parsed['price_to']);
        }

        if (! empty($parsed['on_promotion']) && ! in_array('on_promotion', $skip, true)) {
            $query->whereHas('promotions', fn ($q) => $q->active());
        }

        if (! empty($parsed['rating_min']) && ! in_array('rating', $skip, true)) {
            $this->applyRatingFilter($query, (int) $parsed['rating_min']);
        }

        foreach ($parsed['attributes'] as $attrId => $filter) {
            if ($exceptAttrId !== null && (int) $exceptAttrId === (int) $attrId) {
                continue;
            }
            $this->applyAttributeFilter($query, (int) $attrId, $filter);
        }

        if (! in_array('sort', $skip, true)) {
            $this->applySort($query, $parsed['sort'], $parsed['search'] ?? null);
        }
    }

    /**
     * @return array{0: list<string>, 1: ?int}
     */
    protected function parseExcept(?array $except): array
    {
        if ($except === null) {
            return [[], null];
        }

        $exceptAttrId = isset($except['attribute_id']) ? (int) $except['attribute_id'] : null;

        if (array_is_list($except)) {
            return [$except, $exceptAttrId];
        }

        $skip = [];
        foreach ($except as $key => $value) {
            if ($key === 'attribute_id') {
                continue;
            }
            $skip[] = is_int($key) ? $value : (string) $key;
        }

        return [$skip, $exceptAttrId];
    }

    public function applySort(Builder $query, string $sort, ?string $search = null): void
    {
        $canUseRelevance = $search
            && ! $this->articles->isStrictArticleQuery($search)
            && $sort === self::SORT_RELEVANCE;

        if ($canUseRelevance) {
            $this->textSearch->applyRelevanceOrder($query, $search, ['title', 'short_description']);

            return;
        }

        match ($sort) {
            self::SORT_CHEAP => $query->orderBy('products.min_price'),
            self::SORT_EXPENSIVE => $query->orderByDesc('products.min_price'),
            self::SORT_POPULAR => $query->orderByCatalogPopularity(),
            self::SORT_OLD => $query->orderBy('products.created_at'),
            self::SORT_RATING => $this->applyRatingSort($query),
            default => $query->orderByDesc('products.created_at'),
        };
    }

    protected function applyRatingSort(Builder $query): void
    {
        $query->orderByDesc(DB::raw($this->moderatedRatingAvgSql()))
            ->orderByDesc('products.created_at');
    }

    protected function moderatedRatingAvgSql(): string
    {
        return '(SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE reviews.product_id = products.id AND reviews.is_moderated = 1 AND reviews.deleted_at IS NULL)';
    }

    protected function applyRatingFilter(Builder $query, int $ratingMin): void
    {
        $query->whereHas('reviews', function (Builder $q) use ($ratingMin) {
            $q->where('is_moderated', true);
            if ($ratingMin >= 5) {
                $q->where('rating', 5);
            } else {
                $q->where('rating', '>=', 4);
            }
        });
    }

    /**
     * @param  array<string>  $fields
     */
    protected function applySearch(Builder $query, string $search, array $fields): void
    {
        if ($this->articles->isStrictArticleQuery($search)) {
            $sku = trim($search);
            $query->whereHas('variants', fn (Builder $vq) => $vq->where('sku', $sku));

            return;
        }

        $this->textSearch->apply($query, $search, $fields);
    }

    /**
     * @param  array{values?: array<string>, min?: ?string, max?: ?string}  $filter
     */
    protected function applyAttributeFilter(Builder $query, int $attributeId, array $filter): void
    {
        if (isset($filter['values'])) {
            $values = $filter['values'];
            $query->whereHas('attributeValues', function (Builder $q) use ($attributeId, $values) {
                $q->where('attribute_id', $attributeId)->whereIn('value', $values);
            });

            return;
        }

        $min = $filter['min'] ?? null;
        $max = $filter['max'] ?? null;
        if (($min === null || $min === '') && ($max === null || $max === '')) {
            return;
        }

        $query->whereHas('attributeValues', function (Builder $q) use ($attributeId, $min, $max) {
            $q->where('attribute_id', $attributeId);
            if ($min !== null && $min !== '') {
                $q->whereRaw('CAST(value AS DECIMAL(12,2)) >= ?', [(float) $min]);
            }
            if ($max !== null && $max !== '') {
                $q->whereRaw('CAST(value AS DECIMAL(12,2)) <= ?', [(float) $max]);
            }
        });
    }

    protected function resolveAttributeCategoryId(array $context, array $parsed): ?int
    {
        if ($context['fixed_category_id']) {
            return (int) $context['fixed_category_id'];
        }

        return $parsed['category_id'] ? (int) $parsed['category_id'] : null;
    }

    /**
     * @return array{min: float|null, max: float|null}
     */
    protected function buildPriceFacet(callable $baseQueryFactory, array $parsed, array $context): array
    {
        $q = $baseQueryFactory();
        $this->applyFilters($q, $parsed, $context, except: ['price', 'sort']);

        $productIds = (clone $q)->select('products.id');
        $row = DB::table('product_variants as pv')
            ->whereIn('pv.product_id', $productIds)
            ->where('pv.is_active', true)
            ->whereNull('pv.deleted_at')
            ->selectRaw('MIN(pv.price) as min_price, MAX(pv.price) as max_price')
            ->first();

        return [
            'min' => $row?->min_price !== null ? (float) $row->min_price : null,
            'max' => $row?->max_price !== null ? (float) $row->max_price : null,
        ];
    }

    /**
     * @return list<array{value: int, label: string, count: int}>
     */
    protected function buildRatingFacets(callable $baseQueryFactory, array $parsed, array $context): array
    {
        $facets = [];

        foreach ([5 => '5 звёзд', 4 => '4 звезды и выше'] as $threshold => $label) {
            $q = $baseQueryFactory();
            $this->applyFilters($q, $parsed, $context, except: ['rating', 'sort']);
            $this->applyRatingFilter($q, $threshold);

            $count = (clone $q)->count('products.id');
            if ($count === 0 && ($parsed['rating_min'] ?? null) !== $threshold) {
                continue;
            }

            $facets[] = [
                'value' => $threshold,
                'label' => $label,
                'count' => $count,
            ];
        }

        return $facets;
    }

    /**
     * @return list<array{id: int, name: string, count: int, selected: bool}>
     */
    protected function buildCategoryFacets(callable $baseQueryFactory, array $parsed, array $context): array
    {
        $q = $baseQueryFactory();
        $this->applyFilters($q, $parsed, $context, except: ['category_id', 'sort']);

        $listingCount = $this->listingCountSql('products.id');
        $rows = $q->select('products.category_id', DB::raw("SUM({$listingCount}) as cnt"))
            ->whereNotNull('products.category_id')
            ->groupBy('products.category_id')
            ->pluck('cnt', 'category_id');

        if ($rows->isEmpty()) {
            return [];
        }

        $categories = Category::query()
            ->whereIn('id', $rows->keys())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedId = $parsed['category_id'];

        return $categories->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'count' => (int) ($rows[$c->id] ?? 0),
            'selected' => $selectedId === (int) $c->id,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildAttributeFacets(
        callable $baseQueryFactory,
        array $parsed,
        array $context,
        int $categoryId
    ): array {
        $definitions = CategoryAttribute::query()
            ->where('category_id', $categoryId)
            ->where('applies_to', 'product')
            ->whereIn('type', ['select', 'boolean', 'number'])
            ->orderBy('id')
            ->get();

        if ($definitions->isEmpty()) {
            return [];
        }

        $facets = [];

        foreach ($definitions as $def) {
            $q = $baseQueryFactory();
            $this->applyFilters($q, $parsed, $context, except: ['sort', 'attribute_id' => $def->id]);

            if (in_array($def->type, ['select', 'boolean'], true)) {
                $counts = $this->attributeValueCounts($q, $def->id);
                $selected = $parsed['attributes'][$def->id]['values'] ?? [];
                $options = [];
                $definedOptions = is_array($def->options) ? $def->options : [];

                foreach ($definedOptions as $opt) {
                    $opt = (string) $opt;
                    $count = (int) ($counts[$opt] ?? 0);
                    if ($count === 0 && ! in_array($opt, $selected, true)) {
                        continue;
                    }
                    $options[] = [
                        'value' => $opt,
                        'count' => $count,
                        'selected' => in_array($opt, $selected, true),
                    ];
                }

                foreach ($counts as $value => $count) {
                    if (in_array($value, $definedOptions, true)) {
                        continue;
                    }
                    $options[] = [
                        'value' => $value,
                        'count' => (int) $count,
                        'selected' => in_array($value, $selected, true),
                    ];
                }

                if ($options === []) {
                    continue;
                }

                $facets[] = [
                    'id' => $def->id,
                    'name' => $def->name,
                    'type' => $def->type,
                    'options' => $options,
                ];
            } elseif ($def->type === 'number') {
                $bounds = $this->attributeNumberBounds($q, $def->id);
                $current = $parsed['attributes'][$def->id] ?? [];

                $facets[] = [
                    'id' => $def->id,
                    'name' => $def->name,
                    'type' => 'number',
                    'min' => $bounds['min'],
                    'max' => $bounds['max'],
                    'selected_min' => $current['min'] ?? null,
                    'selected_max' => $current['max'] ?? null,
                ];
            }
        }

        return $facets;
    }

    /**
     * @return array<string, int>
     */
    protected function attributeValueCounts(Builder $productQuery, int $attributeId): array
    {
        $productIds = (clone $productQuery)->select('products.id');
        $listingCount = $this->listingCountSql('pav.product_id');

        return DB::table('product_attribute_values as pav')
            ->where('pav.attribute_id', $attributeId)
            ->whereIn('pav.product_id', $productIds)
            ->select('pav.value', DB::raw("SUM({$listingCount}) as cnt"))
            ->groupBy('pav.value')
            ->pluck('cnt', 'value')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /**
     * Количество позиций витрины (варианты; 1 если активных вариантов нет).
     */
    protected function countListingPositions(Builder $productQuery, array $parsed): int
    {
        $listingCount = $this->listingCountSql('products.id', $parsed['price_from'] ?? null, $parsed['price_to'] ?? null);

        return (int) ((clone $productQuery)->selectRaw("COALESCE(SUM({$listingCount}), 0) as listing_total")->value('listing_total') ?? 0);
    }

    /**
     * @param  string  $productIdColumn  SQL-ссылка на product_id (например products.id или pav.product_id)
     */
    protected function listingCountSql(string $productIdColumn, mixed $priceFrom = null, mixed $priceTo = null): string
    {
        $variantWhere = "pv_lc.product_id = {$productIdColumn} AND pv_lc.is_active = 1 AND pv_lc.deleted_at IS NULL";
        if ($priceFrom !== null && $priceFrom !== '') {
            $variantWhere .= ' AND pv_lc.price >= '.(float) $priceFrom;
        }
        if ($priceTo !== null && $priceTo !== '') {
            $variantWhere .= ' AND pv_lc.price <= '.(float) $priceTo;
        }

        return "(SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE COUNT(*) END FROM product_variants AS pv_lc WHERE {$variantWhere})";
    }

    protected function applyVariantPriceFilter(Builder $query, mixed $priceFrom, mixed $priceTo): void
    {
        if (($priceFrom === null || $priceFrom === '') && ($priceTo === null || $priceTo === '')) {
            return;
        }

        $query->whereHas('variants', function (Builder $variantQuery) use ($priceFrom, $priceTo) {
            $variantQuery
                ->where('is_active', true)
                ->whereNull('deleted_at');

            if ($priceFrom !== null && $priceFrom !== '') {
                $variantQuery->where('price', '>=', (float) $priceFrom);
            }
            if ($priceTo !== null && $priceTo !== '') {
                $variantQuery->where('price', '<=', (float) $priceTo);
            }
        });
    }

    /**
     * @return array{min: float|null, max: float|null}
     */
    protected function attributeNumberBounds(Builder $productQuery, int $attributeId): array
    {
        $productIds = (clone $productQuery)->select('products.id');

        $row = DB::table('product_attribute_values as pav')
            ->where('pav.attribute_id', $attributeId)
            ->whereIn('pav.product_id', $productIds)
            ->selectRaw('MIN(CAST(pav.value AS DECIMAL(12,2))) as vmin, MAX(CAST(pav.value AS DECIMAL(12,2))) as vmax')
            ->first();

        return [
            'min' => $row?->vmin !== null ? (float) $row->vmin : null,
            'max' => $row?->vmax !== null ? (float) $row->vmax : null,
        ];
    }

    /**
     * @param  array  $parsed
     */
    protected function filtersForFrontend(array $parsed, int $page): array
    {
        $attr = [];
        foreach ($parsed['attributes'] as $id => $filter) {
            if (isset($filter['values'])) {
                $attr[$id] = $filter['values'];
            } else {
                $attr[$id] = [
                    'min' => $filter['min'] ?? null,
                    'max' => $filter['max'] ?? null,
                ];
            }
        }

        return [
            'search' => $parsed['search'],
            'sort' => $parsed['sort'],
            'price_from' => $parsed['price_from'],
            'price_to' => $parsed['price_to'],
            'category_id' => $parsed['category_id'],
            'on_promotion' => (bool) ($parsed['on_promotion'] ?? false),
            'rating_min' => $parsed['rating_min'] ?? null,
            'page' => $page,
            'attributes' => $attr,
        ];
    }

    public function markFavorites(Collection $products): void
    {
        if (! auth()->check()) {
            $products->each(function ($p) {
                $p->is_favorite = false;
                $p->favorite_variant_ids = [];
            });

            return;
        }

        $favoriteProductIds = DB::table('favorites')
            ->where('user_id', auth()->id())
            ->whereNull('variant_id')
            ->pluck('product_id')
            ->toArray();

        $favoriteVariantIdsByProduct = DB::table('favorites')
            ->where('user_id', auth()->id())
            ->whereNotNull('variant_id')
            ->select('product_id', 'variant_id')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('variant_id')->map(fn ($id) => (int) $id)->values()->all());

        $products->each(function ($product) use ($favoriteProductIds, $favoriteVariantIdsByProduct) {
            $variantIds = $favoriteVariantIdsByProduct->get($product->id, []);
            $product->favorite_variant_ids = $variantIds;
            $product->is_favorite = in_array($product->id, $favoriteProductIds) || $variantIds !== [];
        });
    }
}
