import React, { useState, useEffect, useRef, useMemo, useCallback } from 'react';
import { router } from '@inertiajs/react';
import ProductCard from '@/Components/Product/ProductCard';
import CatalogFilterSidebar from '@/Components/Catalog/CatalogFilterSidebar';
import ActiveFilterChips, { buildFilterChips } from '@/Components/Catalog/ActiveFilterChips';
import { expandCatalogProductRows } from '@/lib/catalogListing';
import {
    buildCatalogParams,
    filtersToState,
    getCatalogRoute,
    getCatalogOnlyKeys,
} from '@/lib/catalogFilters';
import '../../../css/product/ShopPage.css';

export default function ProductsCatalog({
    dataProduct,
    category,
    seller = null,
    filters = {},
    facets = {},
    total = null,
    isHomePage = false,
    isCategoryPage = false,
    isSellerProfile = false,
}) {
    const initialProducts = dataProduct || [];
    const listingRows = useMemo(() => expandCatalogProductRows(initialProducts), [initialProducts]);
    const categoryName = category?.name || 'категории';
    const categoryId = category?.id;
    const sellerId = seller?.id;

    const filterContext = isHomePage ? 'home' : isSellerProfile ? 'seller' : 'category';

    const [filterState, setFilterState] = useState(() => filtersToState(filters));
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);
    const [displayCount, setDisplayCount] = useState(24);

    const timeoutRef = useRef(null);
    const skipDebounceRef = useRef(false);

    useEffect(() => {
        setFilterState(filtersToState(filters));
        setDisplayCount(24);
    }, [filters]);

    const applyFilters = useCallback(
        (nextState, immediate = false) => {
            const params = buildCatalogParams(nextState);
            const url = getCatalogRoute(filterContext, { categoryId, sellerId });

            const run = () => {
                router.get(url, params, {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
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
        [filterContext, categoryId, sellerId]
    );

    const updateState = useCallback(
        (patch, immediate = false) => {
            setFilterState((prev) => {
                const next = { ...prev, ...patch };
                if (!skipDebounceRef.current) {
                    applyFilters(next, immediate);
                }
                return next;
            });
        },
        [applyFilters]
    );

    const chips = useMemo(() => buildFilterChips(filterState, facets), [filterState, facets]);

    const handleChipRemove = (chip) => {
        if (chip.type === 'price_from') updateState({ priceFrom: '' }, true);
        else if (chip.type === 'price_to') updateState({ priceTo: '' }, true);
        else if (chip.type === 'category_id') updateState({ categoryId: null, attributes: {} }, true);
        else if (chip.type === 'sort') updateState({ sort: 'new' }, true);
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
            sort: 'new',
            priceFrom: '',
            priceTo: '',
            categoryId: null,
            attributes: {},
        };
        skipDebounceRef.current = true;
        setFilterState(empty);
        skipDebounceRef.current = false;
        applyFilters(empty, true);
    };

    const clearSearch = () => {
        updateState({ search: '' }, true);
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

    const visibleRows = listingRows.slice(0, displayCount);
    const hasMore = listingRows.length > displayCount;
    const resultTotal = total ?? listingRows.length;

    const sidebar = (
        <CatalogFilterSidebar
            facets={facets}
            filterState={filterState}
            onSortChange={(sort) => updateState({ sort }, true)}
            onPriceChange={(field, value) =>
                updateState({ [field]: value }, false)
            }
            onCategoryToggle={onCategoryToggle}
            onAttributeValuesChange={onAttributeValuesChange}
            onAttributeRangeChange={onAttributeRangeChange}
            onReset={clearAllFilters}
            showCategoryFacet={filterContext === 'seller' || filterContext === 'home'}
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
                                        ` — показано ${visibleRows.length} из ${resultTotal}`}
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

                        {visibleRows.length > 0 ? (
                            <>
                                <section className="Category_product">
                                    <div className="products__grid">
                                        {visibleRows.map((product) => (
                                            <ProductCard
                                                key={product.listing_key}
                                                product={product}
                                            />
                                        ))}
                                    </div>
                                </section>
                                {hasMore && (
                                    <button
                                        type="button"
                                        className="showMore__btn showMore__btn--active shop-products__load-more"
                                        onClick={() => setDisplayCount((c) => c + 24)}
                                    >
                                        Показать ещё
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
