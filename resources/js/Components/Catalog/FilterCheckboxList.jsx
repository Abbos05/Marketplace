import React, { useMemo, useState } from 'react';

const VISIBLE_LIMIT = 6;

export default function FilterCheckboxList({
    options = [],
    selected = [],
    onChange,
    searchable = false,
}) {
    const [query, setQuery] = useState('');
    const [expanded, setExpanded] = useState(false);

    const filtered = useMemo(() => {
        if (!query.trim()) return options;
        const q = query.trim().toLowerCase();
        return options.filter((o) => o.value.toLowerCase().includes(q));
    }, [options, query]);

    const visible = expanded ? filtered : filtered.slice(0, VISIBLE_LIMIT);
    const hasMore = filtered.length > VISIBLE_LIMIT;

    const toggle = (value) => {
        const set = new Set(selected);
        if (set.has(value)) set.delete(value);
        else set.add(value);
        onChange(Array.from(set));
    };

    return (
        <div className="filter-checkbox-list">
            {searchable && options.length > VISIBLE_LIMIT && (
                <input
                    type="search"
                    className="filter-option__search"
                    placeholder="Поиск..."
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
            )}
            <ul className="filter-checkbox-list__items">
                {visible.map((opt) => (
                    <li key={opt.value} className="filter-option">
                        <label className="filter-option__label">
                            <input
                                type="checkbox"
                                checked={selected.includes(opt.value)}
                                onChange={() => toggle(opt.value)}
                            />
                            <span className="filter-option__text">{opt.label ?? opt.value}</span>
                            <span className="filter-option__count">{opt.count}</span>
                        </label>
                    </li>
                ))}
            </ul>
            {hasMore && (
                <button
                    type="button"
                    className="filter-checkbox-list__toggle"
                    onClick={() => setExpanded((v) => !v)}
                >
                    {expanded ? 'Свернуть' : `Показать ещё (${filtered.length - VISIBLE_LIMIT})`}
                </button>
            )}
        </div>
    );
}

