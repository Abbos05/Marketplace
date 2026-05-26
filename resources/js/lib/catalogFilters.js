/**
 * Build query params for catalog filter Inertia requests.
 */
export function buildCatalogParams(state) {
    const params = {};

    if (state.search) params.search = state.search;
    if (state.sort && state.sort !== defaultSortForContext(state)) {
        params.sort = state.sort;
    }
    if (state.priceFrom !== '' && state.priceFrom != null) params.price_from = state.priceFrom;
    if (state.priceTo !== '' && state.priceTo != null) params.price_to = state.priceTo;
    if (state.categoryId) params.category_id = state.categoryId;
    if (state.onPromotion) params.on_promotion = 1;
    if (state.ratingMin) params.rating_min = state.ratingMin;
    if (state.page && state.page > 1) params.page = state.page;

    if (state.attributes && Object.keys(state.attributes).length > 0) {
        params.attr = {};
        Object.entries(state.attributes).forEach(([attrId, val]) => {
            if (Array.isArray(val) && val.length > 0) {
                params.attr[attrId] = val;
            } else if (val && typeof val === 'object' && (val.min || val.max)) {
                params.attr[attrId] = {
                    min: val.min || undefined,
                    max: val.max || undefined,
                };
            }
        });
        if (Object.keys(params.attr).length === 0) delete params.attr;
    }

    return params;
}

export function defaultSortForContext(state) {
    return state.search ? 'relevance' : 'new';
}

export function filtersToState(filters = {}) {
    const attrs = {};
    if (filters.attributes) {
        Object.entries(filters.attributes).forEach(([id, val]) => {
            if (Array.isArray(val)) {
                attrs[id] = val;
            } else if (val && typeof val === 'object') {
                attrs[id] = { min: val.min ?? '', max: val.max ?? '' };
            }
        });
    }

    const search = filters.search || '';

    return {
        search,
        sort: filters.sort || defaultSortForContext({ search }),
        priceFrom: filters.price_from ?? '',
        priceTo: filters.price_to ?? '',
        categoryId: filters.category_id ?? null,
        onPromotion: Boolean(filters.on_promotion),
        ratingMin: filters.rating_min ?? null,
        page: filters.page ?? 1,
        attributes: attrs,
    };
}

export function getCatalogRoute(context, { categoryId, sellerId }) {
    if (context === 'category' && categoryId) {
        return route('category.show', categoryId);
    }
    if (context === 'seller' && sellerId) {
        return route('seller.index', sellerId);
    }
    return '/';
}

export function getCatalogOnlyKeys(context) {
    if (context === 'home') {
        return ['mysqlProductsData', 'search', 'sort', 'filters', 'facets', 'total', 'pagination'];
    }
    if (context === 'seller') {
        return ['products', 'filters', 'facets', 'total', 'pagination', 'seller', 'sellerId'];
    }
    return ['products', 'filters', 'facets', 'total', 'pagination'];
}

/** @param {{ search?: string }} state */
export function getSortOptions(state = {}) {
    const options = [
        { value: 'new', label: 'Новинки' },
        { value: 'old', label: 'Старые' },
        { value: 'cheap', label: 'Сначала дешевле' },
        { value: 'expensive', label: 'Сначала дороже' },
        { value: 'popular', label: 'Популярные' },
        { value: 'rating', label: 'По рейтингу' },
    ];

    if (state.search) {
        return [{ value: 'relevance', label: 'По релевантности' }, ...options];
    }

    return options;
}

export const SORT_OPTIONS = getSortOptions();

/**
 * Preserve active catalog filters when submitting a new search from the header.
 */
export function mergeCatalogSearchParams(searchText, filters = {}) {
    const state = filtersToState(filters);
    state.search = searchText;
    state.page = 1;
    if (!searchText) {
        state.sort = 'new';
    } else if (!filters.sort || filters.sort === 'new') {
        state.sort = 'relevance';
    }
    return buildCatalogParams(state);
}

export function clampPriceValue(value, facetMin, facetMax) {
    if (value === '' || value == null) return '';
    const num = Number(value);
    if (Number.isNaN(num)) return '';
    if (facetMin != null && num < facetMin) return String(Math.round(facetMin));
    if (facetMax != null && num > facetMax) return String(Math.round(facetMax));
    return String(Math.round(num));
}
