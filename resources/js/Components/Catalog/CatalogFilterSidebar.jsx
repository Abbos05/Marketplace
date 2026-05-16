import React from 'react';
import FilterSection from './FilterSection';
import FilterCheckboxList from './FilterCheckboxList';
import { SORT_OPTIONS } from '@/lib/catalogFilters';

function formatPrice(n) {
    if (n == null) return null;
    return new Intl.NumberFormat('ru-RU').format(Math.round(n));
}

export default function CatalogFilterSidebar({
    facets = {},
    filterState,
    onSortChange,
    onPriceChange,
    onCategoryToggle,
    onAttributeValuesChange,
    onAttributeRangeChange,
    onReset,
    showCategoryFacet = false,
}) {
    const priceHint =
        facets.price?.min != null && facets.price?.max != null
            ? `${formatPrice(facets.price.min)} – ${formatPrice(facets.price.max)} ₽`
            : null;

    return (
        <aside className="shop-sidebar">
            <div className="shop-sidebar__content shop-sidebar__content--scroll">
                <div className="shop-sidebar__header">
                    <h2 className="shop-sidebar__title">Фильтры</h2>
                    <button type="button" className="shop-sidebar__reset" onClick={onReset}>
                        Сбросить
                    </button>
                </div>

                <FilterSection title="Сортировка" defaultOpen>
                    <select
                        value={filterState.sort}
                        onChange={(e) => onSortChange(e.target.value)}
                        className="shop-sidebar__select"
                    >
                        {SORT_OPTIONS.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                </FilterSection>

                <FilterSection title="Цена" defaultOpen>
                    {priceHint && <p className="shop-sidebar__hint">{priceHint}</p>}
                    <div className="shop-sidebar__price-range">
                        <input
                            type="number"
                            placeholder="От"
                            value={filterState.priceFrom}
                            onChange={(e) => onPriceChange('priceFrom', e.target.value)}
                            min="0"
                            className="shop-sidebar__price-input"
                        />
                        <input
                            type="number"
                            placeholder="До"
                            value={filterState.priceTo}
                            onChange={(e) => onPriceChange('priceTo', e.target.value)}
                            min="0"
                            className="shop-sidebar__price-input"
                        />
                    </div>
                </FilterSection>

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
