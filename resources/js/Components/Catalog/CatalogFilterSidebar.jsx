import React from 'react';
import FilterSection from './FilterSection';
import FilterCheckboxList from './FilterCheckboxList';
import PriceRangeFilter from './PriceRangeFilter';
import { getSortOptions } from '@/lib/catalogFilters';

export default function CatalogFilterSidebar({
    facets = {},
    filterState,
    onSortChange,
    onPriceApply,
    onRatingChange,
    onCategoryToggle,
    onAttributeValuesChange,
    onAttributeRangeChange,
    onReset,
    showCategoryFacet = false,
    onPromotionChange,
    showSortInSidebar = false,
}) {
    const sortOptions = getSortOptions(filterState);

    const ratingOptions = [
        { value: '', label: 'Любой рейтинг' },
        ...(facets.rating || []).map((item) => ({
            value: String(item.value),
            label: `${item.label} (${item.count})`,
        })),
    ];

    return (
        <aside className="shop-sidebar">
            <div className="shop-sidebar__content shop-sidebar__content--scroll">
                <div className="shop-sidebar__header">
                    <h2 className="shop-sidebar__title">Фильтры</h2>
                    <button type="button" className="shop-sidebar__reset" onClick={onReset}>
                        Сбросить
                    </button>
                </div>

                {showSortInSidebar && (
                    <FilterSection title="Сортировка" defaultOpen>
                        <select
                            value={filterState.sort}
                            onChange={(e) => onSortChange(e.target.value)}
                            className="shop-sidebar__select"
                        >
                            {sortOptions.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </select>
                    </FilterSection>
                )}

                <FilterSection title="Акции" defaultOpen>
                    <label className="shop-sidebar__toggle">
                        <input
                            type="checkbox"
                            className="shop-sidebar__toggle-input"
                            checked={!!filterState.onPromotion}
                            onChange={(e) => onPromotionChange?.(e.target.checked)}
                        />
                        <span className="shop-sidebar__toggle-box" aria-hidden />
                        <span className="shop-sidebar__toggle-text">Только по акции</span>
                    </label>
                </FilterSection>

                <FilterSection title="Цена" defaultOpen>
                    <PriceRangeFilter
                        facetMin={facets.price?.min}
                        facetMax={facets.price?.max}
                        priceFrom={filterState.priceFrom}
                        priceTo={filterState.priceTo}
                        onApply={onPriceApply}
                    />
                </FilterSection>

                {facets.rating?.length > 0 && (
                    <FilterSection title="Рейтинг" defaultOpen>
                        <select
                            className="shop-sidebar__select"
                            value={filterState.ratingMin ? String(filterState.ratingMin) : ''}
                            onChange={(e) => {
                                const val = e.target.value;
                                onRatingChange?.(val === '' ? null : parseInt(val, 10));
                            }}
                        >
                            {ratingOptions.map((o) => (
                                <option key={o.value || 'any'} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </select>
                    </FilterSection>
                )}

                {showCategoryFacet && facets.categories?.length > 0 && (
                    <FilterSection title="Категория" defaultOpen>
                        <FilterCheckboxList
                            options={facets.categories.map((c) => ({
                                value: String(c.id),
                                count: c.count,
                                label: c.name,
                            }))}
                            selected={
                                filterState.categoryId ? [String(filterState.categoryId)] : []
                            }
                            onChange={(vals) => {
                                const id = vals.length ? parseInt(vals[vals.length - 1], 10) : null;
                                onCategoryToggle(id);
                            }}
                            searchable={facets.categories.length > 6}
                        />
                    </FilterSection>
                )}

                {facets.attributes?.map((attr) => {
                    if (attr.type === 'number') {
                        const val = filterState.attributes[attr.id] || { min: '', max: '' };
                        const formatPrice = (n) =>
                            n != null ? new Intl.NumberFormat('ru-RU').format(Math.round(n)) : null;
                        return (
                            <FilterSection key={attr.id} title={attr.name} defaultOpen>
                                {attr.min != null && attr.max != null && (
                                    <p className="shop-sidebar__hint">
                                        {formatPrice(attr.min)} – {formatPrice(attr.max)}
                                    </p>
                                )}
                                <div className="shop-sidebar__price-range">
                                    <input
                                        type="number"
                                        placeholder="От"
                                        value={val.min ?? ''}
                                        onChange={(e) =>
                                            onAttributeRangeChange(attr.id, 'min', e.target.value)
                                        }
                                        className="shop-sidebar__price-input"
                                    />
                                    <input
                                        type="number"
                                        placeholder="До"
                                        value={val.max ?? ''}
                                        onChange={(e) =>
                                            onAttributeRangeChange(attr.id, 'max', e.target.value)
                                        }
                                        className="shop-sidebar__price-input"
                                    />
                                </div>
                            </FilterSection>
                        );
                    }

                    const selected = filterState.attributes[attr.id] || [];
                    const options = (attr.options || []).map((o) => ({
                        value: o.value,
                        count: o.count,
                    }));

                    return (
                        <FilterSection key={attr.id} title={attr.name} defaultOpen={false}>
                            <FilterCheckboxList
                                options={options}
                                selected={selected}
                                onChange={(vals) => onAttributeValuesChange(attr.id, vals)}
                                searchable={options.length > 6}
                            />
                        </FilterSection>
                    );
                })}
            </div>
        </aside>
    );
}
