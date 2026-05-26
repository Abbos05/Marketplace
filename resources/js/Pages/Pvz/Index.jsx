import React from 'react';
import { Link } from '@inertiajs/react';
import PvzLayout from '@/Layouts/PvzLayout';
import PvzOrderCard from '@/Components/Pvz/PvzOrderCard';
import '../../../css/pvz/dashboard.css';
import '../../../css/admin/dashboard.css';

function formatHandledAt(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '—';
    }
}

export default function Index({
    pickupPoint,
    feeDescription,
    monthStats = {},
    incomingPreview = [],
    incomingCount = 0,
    queuePreview = [],
    queueCount = 0,
    recentOperations = [],
}) {
    return (
        <PvzLayout title="Панель ПВЗ" pickupPoint={pickupPoint}>
            <h1 className="pvz-page-title">Главная</h1>
            <p className="pvz-fee-note">Вознаграждение: {feeDescription?.label}</p>

            <div className="pvz-stats-row">
                <div className="pvz-stat-card">
                    <div className="pvz-stat-value">{incomingCount}</div>
                    <div className="pvz-stat-label">Едут к нам</div>
                </div>
                <div className="pvz-stat-card">
                    <div className="pvz-stat-value">{queueCount}</div>
                    <div className="pvz-stat-label">Ожидают выдачи</div>
                </div>
                <div className="pvz-stat-card">
                    <div className="pvz-stat-value">{monthStats.issued_count ?? 0}</div>
                    <div className="pvz-stat-label">Выдано за месяц</div>
                </div>
                <div className="pvz-stat-card pvz-stat-card--refused">
                    <div className="pvz-stat-value">{monthStats.refused_count ?? 0}</div>
                    <div className="pvz-stat-label">Отказов за месяц</div>
                </div>
                <div className="pvz-stat-card">
                    <div className="pvz-stat-value">
                        {Number(monthStats.earnings ?? 0).toLocaleString('ru-RU')} ₽
                    </div>
                    <div className="pvz-stat-label">К выплате ({monthStats.period})</div>
                </div>
            </div>

            <section className="pvz-dashboard-section">
                <div className="pvz-section-head">
                    <h2>Заказы к нам</h2>
                    {incomingCount > 0 && (
                        <span className="pvz-section-badge pvz-section-badge--incoming">{incomingCount}</span>
                    )}
                </div>
                <p className="pvz-section-desc">Новые и в пути — заранее видно, что скоро приедет на ваш пункт.</p>
                {incomingPreview.length === 0 ? (
                    <div className="adm-empty">Сейчас нет заказов в пути на ваш ПВЗ</div>
                ) : (
                    <div className="adm-orders-list" style={{ padding: 0 }}>
                        {incomingPreview.map((o) => (
                            <PvzOrderCard
                                key={o.id}
                                order={o}
                                pickupPointId={pickupPoint.id}
                                mode="preview"
                            />
                        ))}
                    </div>
                )}
            </section>

            <section className="pvz-dashboard-section">
                <div className="pvz-section-head">
                    <h2>Ближайшие к выдаче</h2>
                    <Link href="/pvz/queue" className="adm-action-btn adm-btn-view">
                        Все ({queueCount})
                    </Link>
                </div>
                {queuePreview.length === 0 ? (
                    <div className="adm-empty">Нет заказов, готовых к выдаче</div>
                ) : (
                    <div className="adm-orders-list" style={{ padding: 0 }}>
                        {queuePreview.map((o) => (
                            <PvzOrderCard key={o.id} order={o} pickupPointId={pickupPoint.id} mode="preview" />
                        ))}
                    </div>
                )}
            </section>

            {recentOperations.length > 0 && (
                <section className="pvz-dashboard-section">
                    <div className="pvz-section-head">
                        <h2>Недавние операции</h2>
                    </div>
                    <p className="pvz-section-desc">Последние выдачи и отказы, оформленные вами.</p>
                    <ul className="pvz-recent-ops">
                        {recentOperations.map((op) => (
                            <li
                                key={`${op.order_id}-${op.action}`}
                                className={`pvz-recent-op ${op.action === 'refused' ? 'pvz-recent-op--refused' : 'pvz-recent-op--issued'}`}
                            >
                                <span className="pvz-recent-op__badge">{op.action_label}</span>
                                <span className="pvz-recent-op__num">№{op.number}</span>
                                <span className="pvz-recent-op__sum">
                                    {Number(op.total).toLocaleString('ru-RU')} ₽
                                </span>
                                <span className="pvz-recent-op__time">{formatHandledAt(op.handled_at)}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </PvzLayout>
    );
}
