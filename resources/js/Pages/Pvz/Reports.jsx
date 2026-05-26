import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import PvzLayout from '@/Layouts/PvzLayout';
import '../../../css/pvz/layout.css';
import '../../../css/pvz/dashboard.css';

function formatPeriodLabel(period) {
    const [y, m] = period.split('-');
    const months = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    return `${months[parseInt(m, 10) - 1]} ${y}`;
}

export default function Reports({
    pickupPoint,
    feeDescription,
    periodSummaries = [],
    availablePeriods = [],
    filters = {},
}) {
    const [periodFrom, setPeriodFrom] = useState(filters.period_from ?? '');
    const [periodTo, setPeriodTo] = useState(filters.period_to ?? '');
    const [sort, setSort] = useState(filters.sort ?? 'desc');
    const [onlyActivity, setOnlyActivity] = useState(filters.only_activity ?? true);

    const applyFilters = (e) => {
        e?.preventDefault();
        const params = { sort, only_activity: onlyActivity ? 1 : 0 };
        if (periodFrom) params.period_from = periodFrom;
        if (periodTo) params.period_to = periodTo;
        router.get('/pvz/reports', params, { preserveScroll: true });
    };

    const downloadReport = (period, format) => {
        window.location.href = `/pvz/reports/export?period=${period}&format=${format}`;
    };

    const downloadAllExcel = () => {
        const params = new URLSearchParams({ format: 'xlsx', all: '1' });
        if (periodFrom) params.set('period_from', periodFrom);
        if (periodTo) params.set('period_to', periodTo);
        window.location.href = `/pvz/reports/export?${params.toString()}`;
    };

    return (
        <PvzLayout title="Отчёты" pickupPoint={pickupPoint}>
            <h1 className="pvz-page-title">Отчёты и выплаты</h1>
            <p className="pvz-fee-note">Формула: {feeDescription?.label}</p>

            <form onSubmit={applyFilters} className="pvz-reports-filters">
                <label>
                    <span className="adm-label">Период с</span>
                    <select
                        className="admin-search-input"
                        value={periodFrom}
                        onChange={(e) => setPeriodFrom(e.target.value)}
                    >
                        <option value="">Все</option>
                        {availablePeriods.map((p) => (
                            <option key={p} value={p}>{formatPeriodLabel(p)}</option>
                        ))}
                    </select>
                </label>
                <label>
                    <span className="adm-label">Период по</span>
                    <select
                        className="admin-search-input"
                        value={periodTo}
                        onChange={(e) => setPeriodTo(e.target.value)}
                    >
                        <option value="">Все</option>
                        {availablePeriods.map((p) => (
                            <option key={p} value={p}>{formatPeriodLabel(p)}</option>
                        ))}
                    </select>
                </label>
                <label>
                    <span className="adm-label">Сортировка</span>
                    <select
                        className="admin-search-input"
                        value={sort}
                        onChange={(e) => setSort(e.target.value)}
                    >
                        <option value="desc">Сначала новые</option>
                        <option value="asc">Сначала старые</option>
                    </select>
                </label>
                <label className="pvz-reports-checkbox">
                    <input
                        type="checkbox"
                        checked={onlyActivity}
                        onChange={(e) => setOnlyActivity(e.target.checked)}
                    />
                    Только с активностью
                </label>
                <button type="submit" className="adm-action-btn adm-btn-view">Применить</button>
                {periodSummaries.length > 0 && (
                    <button
                        type="button"
                        className="adm-action-btn adm-btn-view"
                        onClick={downloadAllExcel}
                    >
                        Excel за период
                    </button>
                )}
            </form>

            {periodSummaries.length === 0 ? (
                <div className="adm-empty" style={{ background: '#fff', padding: 24, borderRadius: 8 }}>
                    Нет данных за выбранный период — отчёты появятся после выдач заказов
                </div>
            ) : (
                <table className="pvz-reports-table">
                    <thead>
                        <tr>
                            <th>Период</th>
                            <th>Выдано</th>
                            <th>Отказов</th>
                            <th>К выплате</th>
                            <th>Скачать</th>
                        </tr>
                    </thead>
                    <tbody>
                        {periodSummaries.map((row) => (
                            <tr key={row.period}>
                                <td>{formatPeriodLabel(row.period)}</td>
                                <td>{row.issued_count}</td>
                                <td>{row.refused_count}</td>
                                <td>{Number(row.earnings).toLocaleString('ru-RU')} ₽</td>
                                <td>
                                    <div className="pvz-export-btns">
                                        <button
                                            type="button"
                                            className="adm-action-btn adm-btn-view"
                                            onClick={() => downloadReport(row.period, 'xlsx')}
                                        >
                                            Excel
                                        </button>
                                        <button
                                            type="button"
                                            className="adm-action-btn adm-btn-view"
                                            onClick={() => downloadReport(row.period, 'pdf')}
                                        >
                                            PDF
                                        </button>
                                        <button
                                            type="button"
                                            className="adm-action-btn adm-btn-view"
                                            onClick={() => downloadReport(row.period, 'csv')}
                                        >
                                            CSV
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </PvzLayout>
    );
}
