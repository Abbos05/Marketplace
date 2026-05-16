/**
 * Build query params for catalog filter Inertia requests.
 */
export function buildCatalogParams(state) {
    const params = {};

    if (state.search) params.search = state.search;
    if (state.sort && state.sort !== 'new') params.sort = state.sort;
    if (state.priceFrom !== '' && state.priceFrom != null) params.price_from = state.priceFrom;
    if (state.priceTo !== '' && state.priceTo != null) params.price_to = state.priceTo;
    if (state.categoryId) params.category_id = state.categoryId;

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

    return {
        search: filters.search || '',
        sort: filters.sort || 'new',
        priceFrom: filters.price_from ?? '',
        priceTo: filters.price_to ?? '',
        categoryId: filters.category_id ?? null,
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
        return ['mysqlNftsData', 'search', 'sort', 'filters', 'facets', 'total'];
    }
    return ['products', 'filters', 'facets', 'total'];
}

export const SORT_OPTIONS = [
    { value: 'new', label: 'Новинки' },
    { value: 'cheap', label: 'Сначала дешевле' },
    { value: 'expensive', label: 'Сначала дороже' },
    { value: 'popular', label: 'Популярные' },
];
