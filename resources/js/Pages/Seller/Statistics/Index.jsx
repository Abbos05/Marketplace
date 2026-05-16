import React, { useState, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';
import '../../../../css/seller/statistics.css';

const PERIODS = [
    { value: '30d',  label: '30 дней' },
    { value: '90d',  label: '3 мес' },
    { value: '180d', label: '6 мес' },
    { value: '365d', label: '12 мес' },
    { value: 'all',  label: 'Всё время' },
];

// ── Sparkline SVG ──────────────────────────────────────────────────
function Sparkline({ values, days }) {
    const W = 600;
    const H = 70;
    const PAD = 4;

    const max = Math.max(...values, 1);
    const min = Math.min(...values);
    const range = max - min || 1;

    const pts = values.map((v, i) => {
        const x = PAD + (i / (values.length - 1)) * (W - PAD * 2);
        const y = H - PAD - ((v - min) / range) * (H - PAD * 2);
        return [x, y];
    });

    const linePath  = pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ');
    const areaPath  = `${linePath} L${pts[pts.length-1][0].toFixed(1)},${H} L${pts[0][0].toFixed(1)},${H} Z`;

    // Show every 5th label so they don't overlap
    const labelIndices = values.map((_, i) => i).filter(i => i % 5 === 0 || i === values.length - 1);

    return (
        <div>
            <div className="st-sparkline-wrap">
                <svg viewBox={`0 0 ${W} ${H}`} className="st-sparkline" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="sparkGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%"   stopColor="var(--backgroundBlack)" stopOpacity=".4" />
                            <stop offset="100%" stopColor="var(--backgroundBlack)" stopOpacity="0"  />
                        </linearGradient>
                    </defs>
                    <path d={areaPath} className="st-sparkline-area" />
                    <path d={linePath} className="st-sparkline-line" />
                </svg>
            </div>
            <div className="st-sparkline-days">
                {days.map((d, i) =>
                    labelIndices.includes(i) ? (
                        <span key={i} className="st-sparkline-day">{d}</span>
                    ) : (
                        <span key={i} />
                    )
                )}
            </div>
        </div>
    );
}

// ── Bar chart ──────────────────────────────────────────────────────
function BarChart({ values, labels, colorClass = '', suffix = ' ₽' }) {
    const [hovered, setHovered] = useState(null);
    const max = Math.max(...values, 1);

    const fmt = (v) =>
        suffix === ' ₽'
            ? Number(v).toLocaleString('ru-RU', { maximumFractionDigits: 0 }) + ' ₽'
            : String(v);

    const total  = values.reduce((a, b) => a + b, 0);
    const avg    = values.length ? total / values.length : 0;
    const maxVal = Math.max(...values);

    return (
        <>
            <div className="st-bars">
                {values.map((v, i) => {
                    const height = Math.max((v / max) * 140, v > 0 ? 4 : 0);
                    return (
                        <div
                            key={i}
                            className="st-bar-item"
                            onMouseEnter={() => setHovered(i)}
                            onMouseLeave={() => setHovered(null)}
                        >
                            {hovered === i && (
                                <div className="st-bar-tooltip">{fmt(v)}</div>
                            )}
                            <div
                                className={`st-bar ${colorClass}`}
                                style={{ height: `${height}px` }}
                            />
                            <span className="st-bar-label">{labels[i]}</span>
                        </div>
                    );
                })}
            </div>
            <div className="st-chart-footer">
                <div className="st-chart-stat">
                    <span className="st-chart-stat__label">Итого</span>
                    <span className="st-chart-stat__value">{fmt(total)}</span>
                </div>
                <div className="st-chart-stat">
                    <span className="st-chart-stat__label">Среднее/мес</span>
                    <span className="st-chart-stat__value">{fmt(Math.round(avg))}</span>
                </div>
                <div className="st-chart-stat">
                    <span className="st-chart-stat__label">Максимум</span>
                    <span className="st-chart-stat__value">{fmt(maxVal)}</span>
                </div>
            </div>
        </>
    );
}

// ── Delta badge ────────────────────────────────────────────────────
function Delta({ value }) {
    if (value === null || value === undefined) return null;
    const cls  = value > 0 ? 'up' : value < 0 ? 'down' : 'neu';
    const icon = value > 0 ? '↑' : value < 0 ? '↓' : '→';
    return (
        <span className={`st-kpi-card__delta st-kpi-card__delta--${cls}`}>
            {icon} {Math.abs(value)}% vs пред. период
        </span>
    );
}

// ── Main page ──────────────────────────────────────────────────────
export default function Index({ kpi, monthly, byStatus, topProducts, daily, period, statusLabels }) {
    const switchPeriod = (p) => {
        router.get(route('seller.statistics'), { period: p }, { preserveState: false });
    };

    const fmtRub = (v) =>
        Number(v).toLocaleString('ru-RU', { maximumFractionDigits: 0 }) + ' ₽';

    const totalStatusOrders = useMemo(
        () => byStatus.reduce((s, r) => s + r.count, 0),
        [byStatus]
    );

    return (
        <SellerLayout title="Статистика">
            <Head title="Статистика" />

            {/* Period switcher */}
            <div className="st-period-tabs">
                {PERIODS.map((p) => (
                    <button
                        key={p.value}
                        type="button"
                        className={`st-period-btn ${period === p.value ? 'st-period-btn--active' : ''}`}
                        onClick={() => switchPeriod(p.value)}
                    >
                        {p.label}
                    </button>
                ))}
            </div>

            {/* KPI cards */}
            <div className="st-kpi-grid">
                <div className="st-kpi-card">
                    <div className="st-kpi-card__icon">💰</div>
                    <div className="st-kpi-card__label">Выручка</div>
                    <div className="st-kpi-card__value">{fmtRub(kpi.revenue)}</div>
                    <Delta value={kpi.revenue_delta} />
                </div>
                <div className="st-kpi-card">
                    <div className="st-kpi-card__icon">🛒</div>
                    <div className="st-kpi-card__label">Заказов</div>
                    <div className="st-kpi-card__value">{kpi.orders_count}</div>
                    <Delta value={kpi.orders_delta} />
                </div>
                <div className="st-kpi-card">
                    <div className="st-kpi-card__icon">📊</div>
                    <div className="st-kpi-card__label">Средний чек</div>
                    <div className="st-kpi-card__value">{fmtRub(kpi.avg_order_value)}</div>
                </div>
                <div className="st-kpi-card">
                    <div className="st-kpi-card__icon">📦</div>
                    <div className="st-kpi-card__label">Товаров продано</div>
                    <div className="st-kpi-card__value">{kpi.items_sold}</div>
                </div>
            </div>

            {/* Revenue + Orders charts side by side */}
            <div className="st-two-col">
                <div className="st-chart-card">
                    <div className="st-chart-card__title">Выручка по месяцам</div>
                    <BarChart values={monthly.revenue} labels={monthly.months} suffix=" ₽" />
                </div>
                <div className="st-chart-card">
                    <div className="st-chart-card__title">Заказов по месяцам</div>
                    <BarChart
                        values={monthly.orders}
                        labels={monthly.months}
                        colorClass="st-bar--orders"
                        suffix=""
                    />
                </div>
            </div>

            {/* Status distribution + Top products side by side */}
            <div className="st-two-col">
                {/* Status distribution */}
                <div className="st-chart-card">
                    <div className="st-chart-card__title">
                        Распределение по статусам
                        {totalStatusOrders > 0 && (
                            <span style={{ fontSize: 13, fontWeight: 400, color: '#94a3b8', marginLeft: 8 }}>
                                всего {totalStatusOrders}
                            </span>
                        )}
                    </div>
                    {totalStatusOrders === 0 ? (
                        <div className="st-empty">Нет данных за выбранный период</div>
                    ) : (
                        <div className="st-status-list">
                            {byStatus
                                .filter((r) => r.count > 0)
                                .map((r) => (
                                    <div key={r.status} className="st-status-row">
                                        <span className="st-status-row__label">{r.label}</span>
                                        <div className="st-status-row__bar-wrap">
                                            <div
                                                className={`st-status-row__bar st-status-row__bar--${r.status}`}
                                                style={{ width: `${r.percent}%` }}
                                            />
                                        </div>
                                        <span className="st-status-row__count">{r.count}</span>
                                        <span className="st-status-row__pct">{r.percent}%</span>
                                    </div>
                                ))}
                        </div>
                    )}
                </div>

                {/* Top products */}
                <div className="st-chart-card">
                    <div className="st-chart-card__title">Топ товаров по выручке</div>
                    {topProducts.length === 0 ? (
                        <div className="st-empty">Нет данных за выбранный период</div>
                    ) : (
                        <table className="st-top-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Товар</th>
                                    <th>Выручка</th>
                                    <th>Заказов</th>
                                    <th>Продано</th>
                                </tr>
                            </thead>
                            <tbody>
                                {topProducts.map((p, i) => (
                                    <tr key={p.id}>
                                        <td>
                                            <span className={`st-rank st-rank--${i + 1}`}>{i + 1}</span>
                                        </td>
                                        <td>
                                            <div className="st-top-product">
                                                {p.image ? (
                                                    <img src={p.image} alt={p.title} className="st-top-product__img" />
                                                ) : (
                                                    <div className="st-top-product__placeholder">📦</div>
                                                )}
                                                <span className="st-top-product__name">{p.title}</span>
                                            </div>
                                        </td>
                                        <td style={{ fontWeight: 700 }}>{fmtRub(p.revenue)}</td>
                                        <td>{p.orders_count}</td>
                                        <td>{p.items_sold} шт.</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>

            {/* Sparkline — daily activity */}
            <div className="st-chart-card">
                <div className="st-chart-card__title">Выручка за последние 30 дней</div>
                {daily.revenue.every((v) => v === 0) ? (
                    <div className="st-empty">Нет продаж за последние 30 дней</div>
                ) : (
                    <Sparkline values={daily.revenue} days={daily.days} />
                )}
            </div>
        </SellerLayout>
    );
}
