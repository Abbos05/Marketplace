import React from 'react';
import { Link } from '@inertiajs/react';
import PvzLayout from '@/Layouts/PvzLayout';
import PvzOrderCard from '@/Components/Pvz/PvzOrderCard';
import PvzNavIcon from '@/Components/Pvz/PvzNavIcon';
import '../../../css/pvz/dashboard.css';
import '../../../css/admin/dashboard.css';

export default function Queue({ pickupPoint, feeDescription, orders = [] }) {
    return (
        <PvzLayout title="К выдаче" pickupPoint={pickupPoint}>
            <h1 className="pvz-page-title">Заказы к выдаче</h1>
            <p className="pvz-page-lead">
                Заказы в статусе «В пункте выдачи» на вашем ПВЗ. {feeDescription?.label}
            </p>

            <div className="pvz-issue-cta">
                <div className="pvz-issue-cta__icon">
                    <PvzNavIcon name="search" />
                </div>
                <div className="pvz-issue-cta__text">
                    <strong>Выдача по коду покупателя</strong>
                    <p>
                        Попросите показать суточный код (8 цифр) или код заказа (10 символов) и найдите заказ в разделе поиска.
                    </p>
                </div>
                <Link href="/pvz/orders" className="pvz-btn pvz-btn--outline">
                    Поиск / скан
                </Link>
            </div>

            {orders.length === 0 ? (
                <div className="adm-empty">Сейчас нет заказов, ожидающих выдачи</div>
            ) : (
                <div className="adm-orders-list" style={{ padding: 0 }}>
                    <div className="adm-result-count">В очереди: {orders.length}</div>
                    {orders.map((o) => (
                        <PvzOrderCard key={o.id} order={o} pickupPointId={pickupPoint.id} mode="preview" />
                    ))}
                </div>
            )}
        </PvzLayout>
    );
}
