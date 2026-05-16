import React from 'react';
import { SORT_OPTIONS } from '@/lib/catalogFilters';

export default function ActiveFilterChips({ chips = [], onRemove, onClearAll }) {
    if (chips.length === 0) return null;

    return (
        <div className="active-filters">
            <div className="active-filters__list">
                {chips.map((chip) => (
                    <button
                        key={chip.key}
                        type="button"
                        className="active-filters__chip"
                        onClick={() => onRemove(chip)}
                    >
                        <span>{chip.label}</span>
                        <span className="active-filters__chip-x" aria-hidden>×</span>
                    </button>
                ))}
            </div>
            <button type="button" className="active-filters__clear" onClick={onClearAll}>
                Сбросить всё
            </button>
        </div>
    );
}

export function buildFilterChips(state, facets = {}) {
    const chips = [];

    if (state.priceFrom) {
        chips.push({ key: 'price_from', type: 'price_from', label: `Цена от ${state.priceFrom} ₽` });
    }
    if (state.priceTo) {
        chips.push({ key: 'price_to', type: 'price_to', label: `Цена до ${state.priceTo} ₽` });
    }
    if (state.categoryId && facets.categories) {
        const cat = facets.categories.find((c) => c.id === state.categoryId);
        if (cat) chips.push({ key: 'category', type: 'category_id', label: cat.name });
    }
    if (state.sort && state.sort !== 'new') {
        const sortLabel = SORT_OPTIONS.find((s) => s.value === state.sort)?.label;
        if (sortLabel) chips.push({ key: 'sort', type: 'sort', label: sortLabel });
    }

    Object.entries(state.attributes || {}).forEach(([attrId, val]) => {
        const facet = facets.attributes?.find((a) => String(a.id) === String(attrId));
        const name = facet?.name || 'Параметр';
        if (Array.isArray(val)) {
            val.forEach((v) => {
                chips.push({
                    key: `attr_${attrId}_${v}`,
                    type: 'attribute_value',
                    attrId,
                    value: v,
                    label: `${name}: ${v}`,
                });
            });
        } else if (val && (val.min || val.max)) {
            const parts = [];
            if (val.min) parts.push(`от ${val.min}`);
            if (val.max) parts.push(`до ${val.max}`);
            chips.push({
                key: `attr_${attrId}_range`,
                type: 'attribute_range',
                attrId,
                label: `${name} ${parts.join(' ')}`,
            });
        }
    });

    return chips;
}
