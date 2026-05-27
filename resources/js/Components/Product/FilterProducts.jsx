import React, { useState, useEffect, useRef, useMemo, useCallback } from 'react';
import { router } from '@inertiajs/react';
import ProductCard from '@/Components/Product/ProductCard';
import ScrollReveal, { staggerDelay } from '@/Components/ScrollReveal';
import CatalogFilterSidebar from '@/Components/Catalog/CatalogFilterSidebar';
import ActiveFilterChips, { buildFilterChips } from '@/Components/Catalog/ActiveFilterChips';
import { expandCatalogProductRows } from '@/lib/catalogListing';
import {
    buildCatalogParams,
    defaultSortForContext,
    filtersToState,
    getCatalogOnlyKeys,
    getCatalogRoute,
    getSortOptions,
} from '@/lib/catalogFilters';
import '../../../css/product/ShopPage.css';

function filtersSignature(filters = {}) {
    const { page, ...rest } = filters;
    return JSON.stringify(rest);
}

export default function ProductsCatalog({
    dataProduct,
    category,
    seller = null,
    sellerId: sellerIdProp = null,
    filters = {},
    facets = {},
    total = null,
    pagination = null,
    isHomePage = false,
    isSellerProfile = false,
}) {
    const categoryName = category?.name || 'категории';
    const categoryId = category?.id;
    const sellerId = sellerIdProp ?? seller?.id ?? null;

    const filterContext = isHomePage ? 'home' : isSellerProfile ? 'seller' : 'category';

    const [filterState, setFilterState] = useState(() => filtersToState(filters));
    const [accumulatedProducts, setAccumulatedProducts] = useState(() => dataProduct || []);
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);

    const timeoutRef = useRef(null);
    const skipDebounceRef = useRef(false);
    const signatureRef = useRef(filtersSignature(filters));

    const sortOptions = useMemo(() => getSortOptions(filterState), [filterState.search]);

    useEffect(() => {
        const nextState = filtersToState(filters);
        setFilterState(nextState);

        const sig = filtersSignature(filters);
        const page = filters.page ?? 1;

        if (sig !== signatureRef.current || page <= 1) {
            signatureRef.current = sig;
            setAccumulatedProducts(dataProduct || []);
        } else {
            setAccumulatedProducts((prev) => {
                const ids = new Set(prev.map((p) => p.id));
                const appended = (dataProduct || []).filter((p) => !ids.has(p.id));
                return appended.length ? [...prev, ...appended] : prev;
            });
        }

        setLoadingMore(false);
    }, [filters, dataProduct, sellerId]);

    const listingRows = useMemo(
        () => expandCatalogProductRows(accumulatedProducts, { oneVariantPerProduct: false }),
        [accumulatedProducts],
    );

    const applyFilters = useCallback(
        (nextState, immediate = false) => {
            if (filterContext === 'seller' && !sellerId) {
                return;
            }

            const params = buildCatalogParams(nextState);
            const url = getCatalogRoute(filterContext, { categoryId, sellerId });

            const run = () => {
                router.get(url, params, {
                    preserveState: filterContext !== 'seller',
                    replace: true,
                    preserveScroll: nextState.page > 1,
                    only: getCatalogOnlyKeys(filterContext),
                });
            };

            if (immediate) {
                if (timeoutRef.current) clearTimeout(timeoutRef.current);
                run();
                return;
            }

            if (timeoutRef.current) clearTimeout(timeoutRef.current);
            timeoutRef.current = setTimeout(run, 400);
        },
        [filterContext, categoryId, sellerId],
    );

    const updateState = useCallback(
        (patch, immediate = false) => {
            const resetsPage = !Object.prototype.hasOwnProperty.call(patch, 'page');
            setFilterState((prev) => {
                const next = {
                    ...prev,
                    ...patch,
                    ...(resetsPage ? { page: 1 } : {}),
                };
                if (!skipDebounceRef.current) {
                    applyFilters(next, immediate);
                }
                return next;
            });
        },
        [applyFilters],
    );

    const chips = useMemo(() => buildFilterChips(filterState, facets), [filterState, facets]);

    const handleChipRemove = (chip) => {
        if (chip.type === 'price_from') updateState({ priceFrom: '' }, true);
        else if (chip.type === 'price_to') updateState({ priceTo: '' }, true);
        else if (chip.type === 'category_id') updateState({ categoryId: null, attributes: {} }, true);
        else if (chip.type === 'sort') {
            updateState({ sort: defaultSortForContext(filterState) }, true);
        } else if (chip.type === 'rating_min') updateState({ ratingMin: null }, true);
        else if (chip.type === 'on_promotion') updateState({ onPromotion: false }, true);
        else if (chip.type === 'attribute_value') {
            const attrs = { ...filterState.attributes };
            attrs[chip.attrId] = (attrs[chip.attrId] || []).filter((v) => v !== chip.value);
            if (attrs[chip.attrId]?.length === 0) delete attrs[chip.attrId];
            updateState({ attributes: attrs }, true);
        } else if (chip.type === 'attribute_range') {
            const attrs = { ...filterState.attributes };
            delete attrs[chip.attrId];
            updateState({ attributes: attrs }, true);
        }
    };

    const clearAllFilters = () => {
        const empty = {
            search: filterState.search,
            sort: defaultSortForContext(filterState),
            priceFrom: '',
            priceTo: '',
            categoryId: null,
            onPromotion: false,
            ratingMin: null,
            page: 1,
            attributes: {},
        };
        skipDebounceRef.current = true;
        setFilterState(empty);
        skipDebounceRef.current = false;
        applyFilters(empty, true);
    };

    const clearSearch = () => {
        updateState({ search: '', sort: 'new' }, true);
    };

    const onAttributeValuesChange = (attrId, vals) => {
        const attrs = { ...filterState.attributes, [attrId]: vals };
        if (vals.length === 0) delete attrs[attrId];
        updateState({ attributes: attrs }, true);
    };

    const onAttributeRangeChange = (attrId, bound, value) => {
        const current = filterState.attributes[attrId] || { min: '', max: '' };
        const attrs = {
            ...filterState.attributes,
            [attrId]: { ...current, [bound]: value },
        };
        updateState({ attributes: attrs });
    };

    const onCategoryToggle = (id) => {
        const changed = filterState.categoryId !== id;
        updateState({
            categoryId: id,
            attributes: changed ? {} : filterState.attributes,
        }, true);
    };

    const onPriceApply = ({ priceFrom, priceTo }) => {
        if (timeoutRef.current) clearTimeout(timeoutRef.current);
        updateState({ priceFrom, priceTo, page: 1 }, true);
    };

    const loadMore = () => {
        if (!pagination?.has_more || loadingMore) return;
        setLoadingMore(true);
        updateState({ page: (filterState.page || 1) + 1 }, true);
    };

    const hasMore = pagination?.has_more ?? false;
    const resultTotal = total ?? listingRows.length;

    const sidebar = (
        <CatalogFilterSidebar
            facets={facets}
            filterState={filterState}
            onSortChange={(sort) => updateState({ sort }, true)}
            onPriceApply={onPriceApply}
            onRatingChange={(ratingMin) => updateState({ ratingMin }, true)}
            onCategoryToggle={onCategoryToggle}
            onAttributeValuesChange={onAttributeValuesChange}
            onAttributeRangeChange={onAttributeRangeChange}
            onReset={clearAllFilters}
            onPromotionChange={(checked) => updateState({ onPromotion: checked }, true)}
            showCategoryFacet={filterContext === 'seller' || filterContext === 'home'}
            showSortInSidebar={false}
        />
    );

    return (
        <div className="category-page">
            <div className="shop-layout">
                <div className="shop-layout__inner">
                    <div className="shop-sidebar-desktop">{sidebar}</div>

                    <main className="shop-products">
                        <div className="shop-products__toolbar">
                            <button
                                type="button"
                                className="shop-filters-mobile-btn"
                                onClick={() => setMobileFiltersOpen(true)}
                            >
                                Фильтры
                                {chips.length > 0 && (
                                    <span className="shop-filters-mobile-btn__badge">{chips.length}</span>
                                )}
                            </button>

                            <label className="shop-products__sort">
                                <span className="shop-products__sort-label">Сортировка</span>
                                <select
                                    value={filterState.sort}
                                    onChange={(e) => updateState({ sort: e.target.value }, true)}
                                    className="shop-products__sort-select"
                                >
                                    {sortOptions.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <p className="shop-products__count">
                                Найдено: <strong>{resultTotal}</strong>
                            </p>
                        </div>

                        <ActiveFilterChips
                            chips={chips}
                            onRemove={handleChipRemove}
                            onClearAll={clearAllFilters}
                        />

                        {filterState.search && (
                            <div className="shop-products__search-info">
                                <span>
                                    Результат поиска: <strong>"{filterState.search}"</strong>
                                    {listingRows.length > 0 &&
                                        ` — показано ${listingRows.length} из ${resultTotal}`}
                                </span>
                                <button
                                    type="button"
                                    onClick={clearSearch}
                                    className="shop-products__search-clear"
                                >
                                    Очистить поиск
                                </button>
                            </div>
                        )}

                        {listingRows.length > 0 ? (
                            <>
                                <section className="Category_product">
                                    <div className="products__grid">
                                        {listingRows.map((product, index) => (
                                            <ScrollReveal
                                                key={product.listing_key}
                                                delay={staggerDelay(index)}
                                            >
                                                <ProductCard product={product} />
                                            </ScrollReveal>
                                        ))}
                                    </div>
                                </section>
                                {hasMore && (
                                    <button
                                        type="button"
                                        className="showMore__btn showMore__btn--active shop-products__load-more"
                                        onClick={loadMore}
                                        disabled={loadingMore}
                                    >
                                        {loadingMore ? 'Загрузка…' : 'Показать ещё'}
                                    </button>
                                )}
                            </>
                        ) : (
                            <div className="shop-products__empty">
                                <p>
                                    {filterState.search
                                        ? `Товаров по запросу "${filterState.search}" не найдено`
                                        : `Пока нет товаров в категории ${categoryName}`}
                                </p>
                                {(chips.length > 0 || filterState.search) && (
                                    <button
                                        type="button"
                                        onClick={clearAllFilters}
                                        className="shop-products__reset-btn"
                                    >
                                        Сбросить фильтры
                                    </button>
                                )}
                            </div>
                        )}
                    </main>
                </div>
            </div>

            {mobileFiltersOpen && (
                <div className="shop-filters-drawer" role="dialog" aria-modal="true">
                    <div
                        className="shop-filters-drawer__backdrop"
                        onClick={() => setMobileFiltersOpen(false)}
                    />
                    <div className="shop-filters-drawer__panel">
                        <div className="shop-filters-drawer__head">
                            <h3>Фильтры</h3>
                            <button
                                type="button"
                                className="shop-filters-drawer__close"
                                onClick={() => setMobileFiltersOpen(false)}
                            >
                                ×
                            </button>
                        </div>
                        <div className="shop-filters-drawer__body">{sidebar}</div>
                        <div className="shop-filters-drawer__foot">
                            <button
                                type="button"
                                className="shop-filters-drawer__apply"
                                onClick={() => setMobileFiltersOpen(false)}
                            >
                                Показать {resultTotal} товаров
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
