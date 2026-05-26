import React, { useState, useMemo, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';
import { formatInt, formatRub } from '@/lib/formatMoney';
import '../../../../css/seller/statistics.css';

const PERIODS = [
    { value: '30d', label: '30 дней' },
    { value: '90d', label: '3 мес' },
    { value: '180d', label: '6 мес' },
    { value: '365d', label: '12 мес' },
    { value: 'all', label: 'Всё время' },
];

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

    const linePath = pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ');
    const areaPath = `${linePath} L${pts[pts.length - 1][0].toFixed(1)},${H} L${pts[0][0].toFixed(1)},${H} Z`;

    const labelIndices = values.map((_, i) => i).filter((i) => i % 5 === 0 || i === values.length - 1);

    return (
        <div>
            <div className="st-sparkline-wrap">
                <svg viewBox={`0 0 ${W} ${H}`} className="st-sparkline" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="sparkGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="var(--backgroundBlack)" stopOpacity=".4" />
                            <stop offset="100%" stopColor="var(--backgroundBlack)" stopOpacity="0" />
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
                    ),
                )}
            </div>
        </div>
    );
}

function BarChart({ values, labels, colorClass = '', suffix = ' ₽' }) {
    const [hovered, setHovered] = useState(null);
    const max = Math.max(...values, 1);

    const fmt = (v) => (suffix === ' ₽' ? formatRub(v) : String(v));

    const total = values.reduce((a, b) => a + b, 0);
    const avg = values.length ? total / values.length : 0;
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
                            {hovered === i && <div className="st-bar-tooltip">{fmt(v)}</div>}
                            <div className={`st-bar ${colorClass}`} style={{ height: `${height}px` }} />
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

function Delta({ value }) {
    if (value === null || value === undefined) return null;
    const cls = value > 0 ? 'up' : value < 0 ? 'down' : 'neu';
    const icon = value > 0 ? '↑' : value < 0 ? '↓' : '→';
    return (
        <span className={`st-kpi-card__delta st-kpi-card__delta--${cls}`}>
            {icon} {Math.abs(value)}% vs пред. период
        </span>
    );
}

function CommissionModal({ open, onClose, breakdown, loading, period, fmtRub }) {
    if (!open) return null;

    const t = breakdown?.totals;
    const labels = breakdown?.split_labels ?? {};

    return (
        <div className="st-modal-backdrop" onClick={onClose} role="presentation">
            <div className="st-modal" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                <div className="st-modal__header">
                    <h2 className="st-modal__title">Структура комиссии</h2>
                    <button type="button" className="st-modal__close" onClick={onClose} aria-label="Закрыть">×</button>
                </div>

                {loading && <p className="st-modal__loading">Загрузка…</p>}

                {!loading && t && (
                    <>
                        <p className="st-modal__hint">
                            Учитываются только выданные заказы (статус «Выдан»), по которым комиссия уже финализирована.
                            Эквайринг и НДС выделяются из суммы комиссии, не из всего оборота.
                        </p>
                        <table className="st-modal__table">
                            <tbody>
                                <tr>
                                    <td>Валовые продажи</td>
                                    <td>{fmtRub(t.gross)}</td>
                                </tr>
                                <tr className="st-modal__row--accent">
                                    <td>Комиссия платформы (всего)</td>
                                    <td>{fmtRub(t.commission_total)}</td>
                                </tr>
                                <tr>
                                    <td>{labels.payment_fee ?? 'Эквайринг'}</td>
                                    <td>{fmtRub(t.payment_processing_fee)}</td>
                                </tr>
                                <tr>
                                    <td>{labels.vat ?? 'НДС'}</td>
                                    <td>{fmtRub(t.vat_amount)}</td>
                                </tr>
                                <tr>
                                    <td>{labels.platform_net ?? 'Доля маркетплейса'}</td>
                                    <td>{fmtRub(t.platform_net)}</td>
                                </tr>
                                <tr>
                                    <td>Процентная часть комиссии</td>
                                    <td>{fmtRub(t.commission_percent_amount)}</td>
                                </tr>
                                <tr>
                                    <td>Фиксированная часть</td>
                                    <td>{fmtRub(t.commission_fixed_amount)}</td>
                                </tr>
                                <tr className="st-modal__row--grand">
                                    <td>К выплате вам (чистый доход)</td>
                                    <td>{fmtRub(t.seller_payout)}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div className="st-modal__actions">
                            <a
                                href={`${route('seller.statistics.commission-report')}?period=${encodeURIComponent(period)}`}
                                className="st-modal__btn st-modal__btn--primary"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Скачать PDF за период
                            </a>
                            <button type="button" className="st-modal__btn" onClick={onClose}>
                                Закрыть
                            </button>
                        </div>
                    </>
                )}

                {!loading && !t && (
                    <p className="st-modal__empty">Нет данных по комиссии за выбранный период.</p>
                )}
            </div>
        </div>
    );
}

export default function Index({ kpi, monthly, byStatus, topProducts, daily, period }) {
    const [commissionOpen, setCommissionOpen] = useState(false);
    const [commissionLoading, setCommissionLoading] = useState(false);
    const [commissionBreakdown, setCommissionBreakdown] = useState(null);

    const switchPeriod = (p) => {
        router.get(route('seller.statistics'), { period: p }, { preserveState: false });
    };

    const openCommissionModal = useCallback(async () => {
        setCommissionOpen(true);
        setCommissionLoading(true);
        setCommissionBreakdown(null);
        try {
            const res = await fetch(
                `${route('seller.statistics.commission-breakdown')}?period=${encodeURIComponent(period)}`,
                { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
            );
            if (res.ok) {
                setCommissionBreakdown(await res.json());
            }
        } finally {
            setCommissionLoading(false);
        }
    }, [period]);

    const totalStatusOrders = useMemo(
        () => byStatus.reduce((s, r) => s + r.count, 0),
        [byStatus],
    );

    return (
        <SellerLayout title="Статистика">
            <Head title="Статистика" />

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

            <p className="st-info-banner">
                Показатели «выдано» учитывают только оплаченные заказы со статусом «Выдан».
                «Ожидает выдачи» — оплаченные заказы в пути или в пункте выдачи.
                Публикация товара на эти суммы не влияет.
            </p>

            <div className="st-kpi-grid">
                <div className="st-kpi-card">
                    <div className="st-kpi-card__label">Чистый доход (выдано)</div>
                    <div className="st-kpi-card__value">{formatRub(kpi.revenue)}</div>
                    <span className="st-kpi-card__hint">После выдачи заказа покупателю</span>
                    <Delta value={kpi.revenue_delta} />
                </div>
                <div className="st-kpi-card st-kpi-card--pending">
                    <div className="st-kpi-card__label">Ожидает выдачи</div>
                    <div className="st-kpi-card__value">{formatRub(kpi.pending_revenue ?? 0)}</div>
                    <span className="st-kpi-card__hint">
                        {kpi.pending_orders_count ?? 0} зак. · оборот {formatRub(kpi.pending_gross_revenue ?? 0)}
                        · оплаченные, ещё не выданные
                    </span>
                </div>
                <div className="st-kpi-card">
                    <div className="st-kpi-card__label">Валовые продажи (выдано)</div>
                    <div className="st-kpi-card__value">{formatRub(kpi.gross_revenue)}</div>
                    <span className="st-kpi-card__hint">Сумма цен товаров до комиссии</span>
                </div>
                <button
                    type="button"
                    className="st-kpi-card st-kpi-card--clickable"
                    onClick={openCommissionModal}
                    title="Показать структуру комиссии"
                >
                    <div className="st-kpi-card__label">Комиссия (выдано)</div>
                    <div className="st-kpi-card__value">{formatRub(kpi.commission_total)}</div>
                    <span className="st-kpi-card__hint">Только по выданным заказам · нажмите для детализации →</span>
                </button>
                <div className="st-kpi-card">
                    <div className="st-kpi-card__label">Заказов выдано</div>
                    <div className="st-kpi-card__value">{formatInt(kpi.orders_count)}</div>
                    <Delta value={kpi.orders_delta} />
                </div>
                <div className="st-kpi-card">
                    <div className="st-kpi-card__label">Средний чек</div>
                    <div className="st-kpi-card__value">{formatRub(kpi.avg_order_value)}</div>
                    <span className="st-kpi-card__hint">Чистый доход на заказ (выдано)</span>
                </div>
                <div className="st-kpi-card">
                    <div className="st-kpi-card__label">Товаров продано</div>
                    <div className="st-kpi-card__value">{formatInt(kpi.items_sold)}</div>
                </div>
            </div>

            <div className="st-two-col">
                <div className="st-chart-card">
                    <div className="st-chart-card__title">Чистый доход по месяцам (выдано, за 12 мес)</div>
                    <BarChart values={monthly.revenue} labels={monthly.months} suffix=" ₽" />
                </div>
                <div className="st-chart-card">
                    <div className="st-chart-card__title">Заказов выдано по месяцам (за 12 мес)</div>
                    <BarChart
                        values={monthly.orders}
                        labels={monthly.months}
                        colorClass="st-bar--orders"
                        suffix=""
                    />
                </div>
            </div>

            <div className="st-two-col">
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

                <div className="st-chart-card">
                    <div className="st-chart-card__title">Топ товаров по чистому доходу (выдано)</div>
                    {topProducts.length === 0 ? (
                        <div className="st-empty">Нет данных за выбранный период</div>
                    ) : (
                        <div className="st-top-table-wrap">
                            <table className="st-top-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Товар</th>
                                        <th className="st-col-money">Чистый доход</th>
                                        <th className="st-col-money">Комиссия</th>
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
                                                <Link
                                                    href={route('seller.products.manage', p.id)} 
                                                    className="st-top-product st-top-product--link"
                                                >
                                                    {p.image ? (
                                                        <img src={p.image} alt={p.title} className="st-top-product__img" />
                                                    ) : (
                                                        <div className="st-top-product__placeholder">📦</div>
                                                    )}
                                                    <span className="st-top-product__name">{p.title}</span>
                                                </Link>
                                            </td>
                                            <td className="st-col-money" style={{ fontWeight: 700 }}>
                                                {formatRub(p.revenue)}
                                            </td>
                                            <td className="st-col-money">{formatRub(p.commission_total)}</td>
                                            <td>{formatInt(p.orders_count)}</td>
                                            <td>{formatInt(p.items_sold)} шт.</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            <div className="st-chart-card">
                <div className="st-chart-card__title">Чистый доход за 30 дней (выдано)</div>
                {daily.revenue.every((v) => v === 0) ? (
                    <div className="st-empty">Нет продаж за последние 30 дней</div>
                ) : (
                    <Sparkline values={daily.revenue} days={daily.days} />
                )}
            </div>

            <CommissionModal
                open={commissionOpen}
                onClose={() => setCommissionOpen(false)}
                breakdown={commissionBreakdown}
                loading={commissionLoading}
                period={period}
                fmtRub={formatRub}
            />
        </SellerLayout>
    );
}
