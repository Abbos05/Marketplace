import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { clampPriceValue } from '@/lib/catalogFilters';

function parseNum(value) {
    if (value === '' || value == null) return null;
    const n = Number(value);
    return Number.isNaN(n) ? null : n;
}

export default function PriceRangeFilter({
    facetMin = null,
    facetMax = null,
    priceFrom = '',
    priceTo = '',
    onApply,
}) {
    const boundsReady = facetMin != null && facetMax != null && facetMax >= facetMin;
    const minBound = boundsReady ? Math.round(facetMin) : 0;
    const maxBound = boundsReady ? Math.round(facetMax) : 100000;

    const [draftFrom, setDraftFrom] = useState(priceFrom ?? '');
    const [draftTo, setDraftTo] = useState(priceTo ?? '');
    const [dragging, setDragging] = useState(null);
    const trackRef = useRef(null);

    useEffect(() => {
        setDraftFrom(priceFrom ?? '');
        setDraftTo(priceTo ?? '');
    }, [priceFrom, priceTo]);

    const fromNum = parseNum(draftFrom) ?? minBound;
    const toNum = parseNum(draftTo) ?? maxBound;
    const rangeSpan = Math.max(maxBound - minBound, 1);

    const percentFrom = ((Math.min(fromNum, toNum) - minBound) / rangeSpan) * 100;
    const percentTo = ((Math.max(fromNum, toNum) - minBound) / rangeSpan) * 100;

    const commitDraft = useCallback(
        (fromVal, toVal) => {
            let nextFrom = fromVal ?? draftFrom;
            let nextTo = toVal ?? draftTo;

            if (boundsReady) {
                if (nextFrom !== '') {
                    nextFrom = clampPriceValue(nextFrom, minBound, maxBound);
                }
                if (nextTo !== '') {
                    nextTo = clampPriceValue(nextTo, minBound, maxBound);
                }
                const fromN = parseNum(nextFrom);
                const toN = parseNum(nextTo);
                if (fromN != null && toN != null && fromN > toN) {
                    nextTo = nextFrom;
                }
            }

            setDraftFrom(nextFrom);
            setDraftTo(nextTo);
            onApply?.({ priceFrom: nextFrom, priceTo: nextTo });
        },
        [boundsReady, draftFrom, draftTo, maxBound, minBound, onApply],
    );

    const valueFromPointer = useCallback(
        (clientX) => {
            const track = trackRef.current;
            if (!track || !boundsReady) return minBound;
            const rect = track.getBoundingClientRect();
            const ratio = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));
            return Math.round(minBound + ratio * rangeSpan);
        },
        [boundsReady, minBound, rangeSpan],
    );

    useEffect(() => {
        if (!dragging) return undefined;

        const onMove = (e) => {
            const val = valueFromPointer(e.clientX);
            if (dragging === 'from') {
                const capped = Math.min(val, parseNum(draftTo) ?? maxBound);
                setDraftFrom(String(capped));
            } else {
                const capped = Math.max(val, parseNum(draftFrom) ?? minBound);
                setDraftTo(String(capped));
            }
        };

        const onUp = () => {
            setDragging(null);
            commitDraft();
        };

        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);

        return () => {
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
        };
    }, [commitDraft, dragging, draftFrom, draftTo, maxBound, minBound, valueFromPointer]);

    const hint = useMemo(() => {
        if (!boundsReady) return null;
        return `Доступно: ${new Intl.NumberFormat('ru-RU').format(minBound)} – ${new Intl.NumberFormat('ru-RU').format(maxBound)} ₽`;
    }, [boundsReady, minBound, maxBound]);

    const isDirty =
        String(draftFrom) !== String(priceFrom ?? '') || String(draftTo) !== String(priceTo ?? '');

    return (
        <div className="price-range-filter">
            {hint && <p className="shop-sidebar__hint">{hint}</p>}
            <div className="shop-sidebar__price-range">
                <input
                    type="number"
                    placeholder="От"
                    value={draftFrom}
                    onChange={(e) => setDraftFrom(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') commitDraft();
                    }}
                    min={boundsReady ? minBound : 0}
                    max={boundsReady ? maxBound : undefined}
                    className="shop-sidebar__price-input"
                    aria-label="Цена от"
                />
                <span className="price-range-filter__sep" aria-hidden>
                    —
                </span>
                <input
                    type="number"
                    placeholder="До"
                    value={draftTo}
                    onChange={(e) => setDraftTo(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') commitDraft();
                    }}
                    min={boundsReady ? minBound : 0}
                    max={boundsReady ? maxBound : undefined}
                    className="shop-sidebar__price-input"
                    aria-label="Цена до"
                />
            </div>
            {boundsReady && (
                <div className="price-range-filter__slider">
                    <div ref={trackRef} className="price-range-filter__track">
                        <div
                            className="price-range-filter__fill"
                            style={{ left: `${percentFrom}%`, right: `${100 - percentTo}%` }}
                        />
                        <button
                            type="button"
                            className="price-range-filter__thumb price-range-filter__thumb--from"
                            style={{ left: `${percentFrom}%` }}
                            aria-label="Минимальная цена"
                            onPointerDown={(e) => {
                                e.preventDefault();
                                setDragging('from');
                            }}
                        />
                        <button
                            type="button"
                            className="price-range-filter__thumb price-range-filter__thumb--to"
                            style={{ left: `${percentTo}%` }}
                            aria-label="Максимальная цена"
                            onPointerDown={(e) => {
                                e.preventDefault();
                                setDragging('to');
                            }}
                        />
                    </div>
                </div>
            )}
            <button
                type="button"
                className={`price-range-filter__apply${isDirty ? ' price-range-filter__apply--active' : ''}`}
                onClick={() => commitDraft()}
                disabled={!isDirty}
            >
                Применить цену
            </button>
        </div>
    );
}
