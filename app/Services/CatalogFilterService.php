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
    public const SORT_NEW = 'new';

    public const SORT_CHEAP = 'cheap';

    public const SORT_EXPENSIVE = 'expensive';

    public const SORT_POPULAR = 'popular';

  private const LEGACY_SORT_MAP = [
        'price_desc' => self::SORT_EXPENSIVE,
        'price_asc' => self::SORT_CHEAP,
        'date_desc' => self::SORT_NEW,
        'date_asc' => self::SORT_NEW,
    ];

    /**
     * @param  callable(): Builder  $baseQueryFactory
     * @param  array{
     *   fixed_category_id?: int|null,
     *   allow_category_facet?: bool,
     *   search_fields?: array<string>,
     *   limit?: int|null,
     * }  $context
     * @return array{
     *   products: Collection,
     *   filters: array,
     *   facets: array,
     *   total: int
     * }
     */
    public function process(Request $request, callable $baseQueryFactory, array $context = []): array
    {
        $context = array_merge([
            'fixed_category_id' => null,
            'allow_category_facet' => false,
            'search_fields' => ['title'],
            'limit' => null,
        ], $context);

        $parsed = $this->parseFilters($request, $context);
        $attributeCategoryId = $this->resolveAttributeCategoryId($context, $parsed);

        $query = $baseQueryFactory();
        $this->applyFilters($query, $parsed, $context, except: null);

        $total = $this->countListingPositions($query);

        if ($context['limit']) {
            $query->limit((int) $context['limit']);
        }

        $products = $query->get();
        Product::enrichForCatalog($products);

        $facets = [
            'price' => $this->buildPriceFacet($baseQueryFactory, $parsed, $context),
            'categories' => $context['allow_category_facet']
                ? $this->buildCategoryFacets($baseQueryFactory, $parsed, $context)
                : [],
            'attributes' => $attributeCategoryId
                ? $this->buildAttributeFacets($baseQueryFactory, $parsed, $context, $attributeCategoryId)
                : [],
        ];

        return [
            'products' => $products,
            'filters' => $this->filtersForFrontend($parsed),
            'facets' => $facets,
            'total' => $total,
        ];
    }

    public function normalizeSort(?string $sort): string
    {
        $sort = $sort ?? self::SORT_NEW;
        if (isset(self::LEGACY_SORT_MAP[$sort])) {
            return self::LEGACY_SORT_MAP[$sort];
        }

        return in_array($sort, [self::SORT_NEW, self::SORT_CHEAP, self::SORT_EXPENSIVE, self::SORT_POPULAR], true)
            ? $sort
            : self::SORT_NEW;
    }

    /**
     * @return array{
     *   search: ?string,
     *   sort: string,
     *   price_from: ?string,
     *   price_to: ?string,
     *   category_id: ?int,
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

        return [
            'search' => $search !== '' ? $search : null,
            'sort' => $this->normalizeSort($request->query('sort')),
            'price_from' => $request->query('price_from'),
            'price_to' => $request->query('price_to'),
            'category_id' => $categoryId ? (int) $categoryId : null,
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
     * @param  array|null  $except  keys: price, category_id, attribute_id (int)
     */
    public function applyFilters(Builder $query, array $parsed, array $context, ?array $except = null): void
    {
        [$skip, $exceptAttrId] = $this->parseExcept($except);

        if ($parsed['search'] && ! in_array('search', $skip, true)) {
            $this->applySearch($query, $parsed['search'], $context['search_fields'] ?? ['title']);
        }

        if ($parsed['category_id'] && ! in_array('category_id', $skip, true)) {
            $query->where('products.category_id', $parsed['category_id']);
        }

        if (! in_array('price', $skip, true)) {
            if ($parsed['price_from'] !== null && $parsed['price_from'] !== '') {
                $query->where('products.min_price', '>=', (float) $parsed['price_from']);
            }
            if ($parsed['price_to'] !== null && $parsed['price_to'] !== '') {
                $query->where('products.min_price', '<=', (float) $parsed['price_to']);
            }
        }
        foreach ($parsed['attributes'] as $attrId => $filter) {
            if ($exceptAttrId !== null && (int) $exceptAttrId === (int) $attrId) {
                continue;
            }
            $this->applyAttributeFilter($query, (int) $attrId, $filter);
        }

        $this->applySort($query, $parsed['sort'], $parsed['search'] ?? null);
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
        if ($search && ! $this->articles->isStrictArticleQuery($search)) {
            $this->textSearch->applyRelevanceOrder($query, $search, ['title', 'short_description']);
        }

        match ($sort) {
            self::SORT_CHEAP => $query->orderBy('products.min_price'),
            self::SORT_EXPENSIVE => $query->orderByDesc('products.min_price'),
            self::SORT_POPULAR => $query->orderByDesc('products.sales_count')->orderByDesc('products.created_at'),
            default => $query->orderByDesc('products.created_at'),
        };
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
        $this->applyFilters($q, $parsed, $context, except: ['price']);

        $row = $q->selectRaw('MIN(products.min_price) as min_price, MAX(products.min_price) as max_price')->first();

        return [
            'min' => $row?->min_price !== null ? (float) $row->min_price : null,
            'max' => $row?->max_price !== null ? (float) $row->max_price : null,
        ];
    }

    /**
     * @return list<array{id: int, name: string, count: int, selected: bool}>
     */
    protected function buildCategoryFacets(callable $baseQueryFactory, array $parsed, array $context): array
    {
        $q = $baseQueryFactory();
        $this->applyFilters($q, $parsed, $context, except: ['category_id']);

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
            $this->applyFilters($q, $parsed, $context, except: ['attribute_id' => $def->id]);

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
    protected function countListingPositions(Builder $productQuery): int
    {
        $listingCount = $this->listingCountSql('products.id');

        return (int) ((clone $productQuery)->selectRaw("COALESCE(SUM({$listingCount}), 0) as listing_total")->value('listing_total') ?? 0);
    }

    /**
     * @param  string  $productIdColumn  SQL-ссылка на product_id (например products.id или pav.product_id)
     */
    protected function listingCountSql(string $productIdColumn): string
    {
        return "(SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE COUNT(*) END FROM product_variants AS pv_lc WHERE pv_lc.product_id = {$productIdColumn} AND pv_lc.is_active = 1 AND pv_lc.deleted_at IS NULL)";
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
    protected function filtersForFrontend(array $parsed): array
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
