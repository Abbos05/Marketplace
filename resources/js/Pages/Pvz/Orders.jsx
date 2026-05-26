import React, { useState, useCallback, useEffect } from 'react';
import { router } from '@inertiajs/react';
import PvzLayout from '@/Layouts/PvzLayout';
import BarcodeScannerModal from '@/Components/BarcodeScannerModal';
import PvzOrderCard from '@/Components/Pvz/PvzOrderCard';
import '../../../css/pvz/dashboard.css';
import '../../../css/admin/dashboard.css';

const PICKUP_FILTERS = [
    { key: 'mine', label: 'На моём ПВЗ' },
    { key: 'other', label: 'Другие ПВЗ' },
    { key: 'all', label: 'Все' },
];

const STATUS_FILTERS = [
    { key: 'active', label: 'Актуальные', hint: 'Новые, в пути, в пункте' },
    { key: 'ready', label: 'К выдаче', hint: 'В пункте выдачи' },
    { key: 'done', label: 'Завершённые', hint: 'Выдан, отказ, отмена' },
    // { key: 'all', label: 'Все статусы', hint: '' },
];

const MAX_SEARCH_LEN = 80;

const SEARCH_TYPE_LABELS = {
    daily_code: 'Суточный код',
    order_code: 'Код заказа',
    order_number: 'Номер заказа',
    email: 'Email',
    phone: 'Телефон',
};

export default function Orders({
    pickupPoint,
    orderSearch = '',
    searchType = '',
    pickupFilter = 'mine',
    statusFilter = 'active',
    pickupCounts = { all: 0, mine: 0, other: 0 },
    statusCounts = { active: 0, ready: 0, done: 0, all: 0, filtered: 0 },
    pagination = { current_page: 1, per_page: 25, total: 0, last_page: 1 },
    orderResults = [],
    recentSearches = [],
}) {
    const [orderQuery, setOrderQuery] = useState(orderSearch);
    const [scannerOpen, setScannerOpen] = useState(false);

    useEffect(() => {
        setOrderQuery(orderSearch);
    }, [orderSearch]);

    const clampQuery = (value) => String(value ?? '').trim().slice(0, MAX_SEARCH_LEN);

    const runOrderSearch = useCallback((query) => {
        const trimmed = clampQuery(query);
        if (!trimmed) return;
        setOrderQuery(trimmed);
        router.post('/pvz/orders/search', { order_search: trimmed }, {
            preserveScroll: true,
        });
    }, []);

    const applyListFilters = (overrides = {}) => {
        if (!orderSearch) return;
        router.get('/pvz/orders', {
            pickup_filter: overrides.pickup_filter ?? pickupFilter,
            status_filter: overrides.status_filter ?? statusFilter,
            page: overrides.page ?? 1,
        }, { preserveScroll: true });
    };

    const setPickupFilter = (filter) => applyListFilters({ pickup_filter: filter, page: 1 });
    const setStatusFilter = (filter) => applyListFilters({ status_filter: filter, page: 1 });
    const goToPage = (page) => applyListFilters({ page });

    const submitOrderSearch = (e) => {
        e?.preventDefault();
        runOrderSearch(orderQuery);
    };

    const handleBarcodeScan = useCallback((decoded) => {
        setScannerOpen(false);
        runOrderSearch(decoded);
    }, [runOrderSearch]);

    const showPickupFilters = orderSearch && pickupCounts.all > 0;
    const showStatusFilters = orderSearch && statusCounts.all > 0;
    const showBulkHint = orderSearch && statusCounts.all > 25;
    const recentHistory = recentSearches.slice(0, 4);
    const showEmptyHistory = !orderSearch && recentHistory.length > 0;

    const clearSearch = () => {
        setOrderQuery('');
        router.get('/pvz/orders', { clear: 1 }, { preserveScroll: true });
    };

    return (
        <PvzLayout title="Поиск заказов" pickupPoint={pickupPoint}>
            <h1 className="pvz-page-title">Поиск и сканирование</h1>

            <div className="pvz-search-help">
                <div className="pvz-search-help-card">
                    <span className="pvz-search-help-card__tag">Выдача</span>
                    <strong>Суточный код</strong>
                    <p>8 цифр из «Код получения» — все заказы покупателя.</p>
                </div>
                <div className="pvz-search-help-card">
                    <span className="pvz-search-help-card__tag">Выдача</span>
                    <strong>Код заказа</strong>
                    <p>10 символов с QR на странице заказа — один заказ.</p>
                </div>
                <div className="pvz-search-help-card pvz-search-help-card--muted">
                    <span className="pvz-search-help-card__tag">Просмотр</span>
                    <strong>Номер, email, телефон</strong>
                    <p>Только информация, без выдачи и отказа.</p>
                </div>
            </div>

            <form onSubmit={submitOrderSearch} className="adm-search-bar adm-mb">
                <input
                    type="text"
                    className="admin-search-input"
                    placeholder="Суточный код, код заказа (10), номер, email..."
                    value={orderQuery}
                    maxLength={MAX_SEARCH_LEN}
                    onChange={(e) => setOrderQuery(e.target.value.slice(0, MAX_SEARCH_LEN))}
                />
                <button
                    type="button"
                    className="adm-action-btn adm-btn-scan"
                    onClick={() => setScannerOpen(true)}
                >
                    Сканировать
                </button>
                <button type="submit" className="adm-action-btn adm-btn-view">Найти</button>
                {orderSearch && (
                    <button type="button" className="adm-action-btn" onClick={clearSearch}>
                        Очистить
                    </button>
                )}
            </form>

            {showEmptyHistory && (
                <section className="pvz-search-history">
                    <h2 className="pvz-search-history__title">История поиска</h2>
                    <p className="pvz-search-history__lead">Последние 4 запроса — нажмите, чтобы повторить</p>
                    <ul className="pvz-search-history__list">
                        {recentHistory.map((row) => (
                            <li key={`${row.q}-${row.at}`}>
                                <button
                                    type="button"
                                    className="pvz-search-history__item"
                                    onClick={() => runOrderSearch(row.q)}
                                >
                                    <span className="pvz-search-history__query">{row.q}</span>
                                    <span className="pvz-search-history__meta">
                                        {SEARCH_TYPE_LABELS[row.type] || row.type}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {showPickupFilters && (
                <div className="pvz-pickup-filters">
                    <span className="pvz-filter-group-label">Пункт выдачи</span>
                    {PICKUP_FILTERS.map((f) => (
                        <button
                            key={f.key}
                            type="button"
                            className={`pvz-pickup-filter-btn ${pickupFilter === f.key ? 'active' : ''}`}
                            onClick={() => setPickupFilter(f.key)}
                        >
                            {f.label}
                            <span className="pvz-pickup-filter-count">
                                {pickupCounts[f.key] ?? 0}
                            </span>
                        </button>
                    ))}
                </div>
            )}

            {showStatusFilters && (
                <div className="pvz-status-filters">
                    <span className="pvz-filter-group-label">Актуальность</span>
                    {STATUS_FILTERS.map((f) => (
                        <button
                            key={f.key}
                            type="button"
                            className={`pvz-pickup-filter-btn pvz-status-filter-btn ${statusFilter === f.key ? 'active' : ''}`}
                            onClick={() => setStatusFilter(f.key)}
                            title={f.hint}
                        >
                            {f.label}
                            <span className="pvz-pickup-filter-count">
                                {statusCounts[f.key] ?? 0}
                            </span>
                        </button>
                    ))}
                </div>
            )}

            {showBulkHint && (
                <p className="pvz-search-bulk-hint">
                    Найдено заказов: {statusCounts.all}. По умолчанию показаны актуальные — без выданных и отказов.
                    Используйте фильтры и страницы ниже.
                </p>
            )}

            {!orderSearch && orderResults.length === 0 && !showEmptyHistory && (
                <div className="adm-empty">Введите суточный код или данные для поиска</div>
            )}

            {orderSearch && pickupCounts.all === 0 && (
                <div className="adm-empty">Заказы по запросу «{orderSearch}» не найдены</div>
            )}

            {orderSearch && pickupCounts.all > 0 && statusCounts.filtered === 0 && (
                <div className="adm-empty">
                    В выбранных фильтрах нет заказов. <span> </span> 
                    {statusFilter !== 'active' && (
                        <button type="button" className="pvz-link-btn" onClick={() => setStatusFilter('active')}>
                            Показать актуальные
                        </button>
                    )}
                    {statusFilter === 'active' && pickupFilter !== 'all' && (
                        <button type="button" className="pvz-link-btn" onClick={() => setPickupFilter('all')}>
                            Показать на всех ПВЗ
                        </button>
                    )}
                </div>
            )}

            {orderSearch && pickupCounts.all > 0 && orderResults.length === 0 && statusCounts.filtered > 0 && (
                <div className="adm-empty">
                    На этой странице нет заказов. Перейдите на другую страницу.
                </div>
            )}

            {orderResults.length > 0 && (
                <div className="adm-orders-list" style={{ padding: 0 }}>
                    <div className="adm-result-count">
                        Показано: {orderResults.length} из {statusCounts.filtered}
                        {pickupCounts.all !== statusCounts.filtered && (
                            <span> (всего по запросу: {pickupCounts.all})</span>
                        )}
                        {pagination.last_page > 1 && (
                            <span> · стр. {pagination.current_page} / {pagination.last_page}</span>
                        )}
                    </div>
                    {orderResults.map((o) => (
                        <PvzOrderCard
                            key={o.id}
                            order={o}
                            pickupPointId={pickupPoint.id}
                            defaultExpanded={orderResults.length <= 3}
                            mode="issue"
                        />
                    ))}

                    {pagination.last_page > 1 && (
                        <div className="pvz-pagination">
                            <button
                                type="button"
                                className="adm-action-btn"
                                disabled={pagination.current_page <= 1}
                                onClick={() => goToPage(pagination.current_page - 1)}
                            >
                                ← Назад
                            </button>
                            <span className="pvz-pagination__info">
                                {pagination.current_page} / {pagination.last_page}
                            </span>
                            <button
                                type="button"
                                className="adm-action-btn adm-btn-view"
                                disabled={pagination.current_page >= pagination.last_page}
                                onClick={() => goToPage(pagination.current_page + 1)}
                            >
                                Вперёд →
                            </button>
                        </div>
                    )}
                </div>
            )}

            <BarcodeScannerModal
                open={scannerOpen}
                onClose={() => setScannerOpen(false)}
                onScan={handleBarcodeScan}
                title="Сканирование кода покупателя"
            />
        </PvzLayout>
    );
}
