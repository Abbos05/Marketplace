import React, { useCallback, useRef } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';
import '../../../../css/seller/orders.css';

const STATUS_TABS = [
    { key: 'all', label: 'Все' },
    { key: 'NEW', label: 'Новые' },
    { key: 'INTRANSIT', label: 'В пути' },
    { key: 'DELIVERED', label: 'В ПВЗ' },
    { key: 'ISSUED', label: 'Выданы' },
    { key: 'CANCELED', label: 'Отменены' },
    { key: 'REFUSED', label: 'Отказ' },
];

const SORT_OPTIONS = [
    { value: 'newest',     label: 'Сначала новые' },
    { value: 'oldest',     label: 'Сначала старые' },
    { value: 'total_desc', label: 'Сумма: по убыв.' },
    { value: 'total_asc',  label: 'Сумма: по возр.' },
];

export default function Index({ orders, stats, statusCounts, statusLabels, filters = {} }) {
    const { props } = usePage();

    const currentStatus = filters.status ?? 'all';
    const currentSort   = filters.sort   ?? 'newest';

    const searchRef  = useRef(null);
    const dateFromRef = useRef(null);
    const dateToRef   = useRef(null);

    const applyFilters = useCallback((overrides = {}) => {
        const resetPage = !Object.prototype.hasOwnProperty.call(overrides, 'page');
        router.get(
            route('seller.orders'),
            {
                status:    currentStatus,
                sort:      currentSort,
                search:    searchRef.current?.value ?? filters.search ?? '',
                date_from: dateFromRef.current?.value ?? filters.date_from ?? '',
                date_to:   dateToRef.current?.value   ?? filters.date_to   ?? '',
                ...(resetPage ? { page: 1 } : {}),
                ...overrides,
            },
            { preserveState: true, replace: true },
        );
    }, [currentStatus, currentSort, filters]);

    const handleSearch = useCallback((e) => {
        if (e.key === 'Enter') applyFilters({ search: e.target.value });
    }, [applyFilters]);

    const handleSearchBlur = useCallback((e) => {
        applyFilters({ search: e.target.value });
    }, [applyFilters]);

    const totalCount = Object.values(statusCounts).reduce((a, b) => a + Number(b), 0);

    const exportUrl = () => {
        const params = new URLSearchParams({
            status:    currentStatus !== 'all' ? currentStatus : '',
            search:    searchRef.current?.value ?? filters.search ?? '',
            date_from: dateFromRef.current?.value ?? filters.date_from ?? '',
            date_to:   dateToRef.current?.value   ?? filters.date_to   ?? '',
        });
        return route('seller.orders.export') + '?' + params.toString();
    };

    return (
        <SellerLayout title="Заказы">
            <Head title="Заказы" />


            {/* Stat cards */}
            <div className="ord-stats">
                <div className="ord-stat-card ord-stat-card--blue">
                    <span className="ord-stat-card__label">Всего заказов</span>
                    <span className="ord-stat-card__value">{stats.total_orders}</span>
                    <span className="ord-stat-card__sub">за всё время</span>
                </div>
                <div className="ord-stat-card ord-stat-card--amber">
                    <span className="ord-stat-card__label">Ожидают</span>
                    <span className="ord-stat-card__value">{stats.pending_orders}</span>
                    <span className="ord-stat-card__sub">new + paid + в обработке</span>
                </div>
                <div className="ord-stat-card ord-stat-card--green">
                    <span className="ord-stat-card__label">Чистый доход (выдано, месяц)</span>
                    <span className="ord-stat-card__value">
                        {Number(stats.month_revenue).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽
                    </span>
                    <span className="ord-stat-card__sub">
                        оборот {Number(stats.month_gross_revenue || 0).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽ · комиссия {Number(stats.month_commission || 0).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽
                        {(stats.month_pending_revenue > 0) && (
                            <> · ожидает {Number(stats.month_pending_revenue).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽</>
                        )}
                    </span>
                </div>
                <div className="ord-stat-card ord-stat-card--rose">
                    <span className="ord-stat-card__label">Сегодня</span>
                    <span className="ord-stat-card__value">{stats.today_orders}</span>
                    <span className="ord-stat-card__sub">новых заказов</span>
                </div>
            </div>

            {/* Toolbar */}
            <div className="ord-toolbar">
                <input
                    ref={searchRef}
                    className="ord-search"
                    type="text"
                    placeholder="Поиск по номеру или имени покупателя..."
                    defaultValue={filters.search ?? ''}
                    onKeyDown={handleSearch}
                    onBlur={handleSearchBlur}
                />
                <input
                    ref={dateFromRef}
                    type="date"
                    className="ord-date"
                    defaultValue={filters.date_from ?? ''}
                    title="Дата от"
                    onChange={() => applyFilters({ date_from: dateFromRef.current.value })}
                />
                <input
                    ref={dateToRef}
                    type="date"
                    className="ord-date"
                    defaultValue={filters.date_to ?? ''}
                    title="Дата до"
                    onChange={() => applyFilters({ date_to: dateToRef.current.value })}
                />
                <select
                    className="ord-sort-select"
                    value={currentSort}
                    onChange={(e) => applyFilters({ sort: e.target.value })}
                >
                    {SORT_OPTIONS.map((o) => (
                        <option key={o.value} value={o.value}>{o.label}</option>
                    ))}
                </select>
                <a className="ord-export-btn" href={exportUrl()}>
                    ↓ CSV
                </a>
            </div>

            {/* Status tabs */}
            <div className="ord-tabs">
                {STATUS_TABS.map(({ key, label }) => {
                    const count = key === 'all' ? totalCount : (statusCounts[key] ?? 0);
                    return (
                        <button
                            key={key}
                            type="button"
                            className={`ord-tab ${currentStatus === key ? 'ord-tab--active' : ''}`}
                            onClick={() => applyFilters({ status: key })}
                        >
                            {label}
                            {count > 0 && <span className="ord-tab-count">{count}</span>}
                        </button>
                    );
                })}
            </div>

            {/* Table or empty */}
            {orders.data.length === 0 ? (
                <div className="ord-empty">
                    <div className="ord-empty__icon">🛒</div>
                    <div className="ord-empty__title">Заказов нет</div>
                    <div className="ord-empty__sub">
                        {currentStatus === 'all'
                            ? 'Покупатели ещё не оформляли заказы на ваши товары'
                            : `Нет заказов со статусом «${statusLabels[currentStatus] ?? currentStatus}»`}
                    </div>
                </div>
            ) : (
                <div className="ord-table-wrap">
                    <table className="ord-table">
                        <thead>
                            <tr>
                                <th>Заказ</th>
                                <th>Покупатель</th>
                                <th>Дата</th>
                                <th>Позиций</th>
                                <th>Сумма</th>
                                <th>К выплате</th>
                                <th>Статус</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.map((order) => (
                                <tr key={order.id}>
                                    <td>
                                        <div className="ord-order-number">{order.number}</div>
                                    </td>
                                    <td>
                                        <div className="ord-buyer-name">{order.buyer.name}</div>
                                        <div className="ord-buyer-email">{order.buyer.email}</div>
                                    </td>
                                    <td className="ord-date-cell">{order.created_at}</td>
                                    <td>
                                        <span className="ord-items-count">{order.items_count} шт.</span>
                                    </td>
                                    <td className="ord-total">
                                        {Number(order.total).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽
                                    </td>
                                    <td className="ord-total">
                                        {Number(order.seller_payout).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽
                                    </td>
                                    <td>
                                        <span className={`ord-badge ord-badge--${order.status}`}>
                                            {order.status_label}
                                        </span>
                                    </td>
                                    <td>
                                        <div className="ord-actions">
                                            <Link
                                                href={route('seller.orders.show', order.id)}
                                                className="ord-btn ord-btn--primary"
                                            >
                                                Детали
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    {orders.last_page > 1 && (
                        <div className="ord-pagination">
                            <span className="ord-pagination__info">
                                Показано {orders.from}–{orders.to} из {orders.total}
                            </span>
                            <div className="ord-pagination__links">
                                {orders.links.map((link, i) => {
                                    if (link.url === null) {
                                        return (
                                            <span
                                                key={i}
                                                className="ord-page-btn ord-page-btn--disabled"
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        );
                                    }
                                    return (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            className={`ord-page-btn ${link.active ? 'ord-page-btn--active' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </SellerLayout>
    );
}
