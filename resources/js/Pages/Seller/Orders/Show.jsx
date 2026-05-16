import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';
import '../../../../css/seller/orders.css';

const PAYMENT_STATUS_LABELS = {
    pending:  'Ожидает оплаты',
    paid:     'Оплачен',
    failed:   'Ошибка оплаты',
    refunded: 'Возвращён',
};

const DELIVERY_METHOD_LABELS = {
    pvz:     'Пункт выдачи',
    courier: 'Курьер',
    post:    'Почта России',
};

const PAYMENT_METHOD_LABELS = {
    card:   'Банковская карта',
    wallet: 'Кошелёк',
    cod:    'Наложенный платёж',
};

export default function Show({ order, items, nextStatuses, statusLabels }) {
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [submitting, setSubmitting] = useState(false);

    const handleStatusUpdate = (status) => {
        if (submitting) return;
        setSubmitting(true);
        router.post(
            route('seller.orders.status', order.id),
            { status },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const itemsTotal = items.reduce((sum, i) => sum + i.subtotal, 0);

    return (
        <SellerLayout title={`Заказ ${order.number}`}>
            <Head title={`Заказ ${order.number}`} />

            {/* Flash messages */}
            {flash.success && <div className="ord-flash ord-flash--success">{flash.success}</div>}
            {flash.error   && <div className="ord-flash ord-flash--error">{flash.error}</div>}

            {/* Back link */}
            <Link href={route('seller.orders')} className="ord-back">
                ← Все заказы
            </Link>

            {/* Header */}
            <div className="ord-detail-header">
                <div className="ord-detail-title">
                    {order.number}
                    <span className={`ord-badge ord-badge--${order.status}`}>
                        {statusLabels[order.status] ?? order.status}
                    </span>
                    <span className="ord-detail-meta">от {order.created_at}</span>
                </div>
            </div>

            <div className="ord-detail-grid">
                {/* Left column */}
                <div>
                    {/* Items */}
                    <div className="ord-card">
                        <div className="ord-card__header">
                            <span className="ord-card__title">Ваши товары в заказе</span>
                            <span className="ord-items-count">{items.length} позиций</span>
                        </div>
                        <table className="ord-items-table">
                            <thead>
                                <tr>
                                    <th>Товар</th>
                                    <th>Кол-во</th>
                                    <th>Цена</th>
                                    <th>Итог</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr key={item.id}>
                                        <td>
                                            <div className="ord-item-product">
                                                {item.product.image ? (
                                                    <img
                                                        src={item.product.image}
                                                        alt={item.product.title}
                                                        className="ord-item-img"
                                                    />
                                                ) : (
                                                    <div className="ord-item-img-placeholder">📦</div>
                                                )}
                                                <div>
                                                    <div className="ord-item-name">{item.product.title}</div>
                                                    {item.variant.options && (
                                                        <div className="ord-item-variant">{item.variant.options}</div>
                                                    )}
                                                    {item.variant.sku && (
                                                        <div className="ord-item-variant">SKU: {item.variant.sku}</div>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td>{item.quantity}</td>
                                        <td>
                                            {Number(item.price_at_purchase).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽
                                        </td>
                                        <td>
                                            <strong>
                                                {Number(item.subtotal).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽
                                            </strong>
                                        </td>
                                    </tr>
                                ))}
                                <tr className="ord-items-total-row">
                                    <td colSpan={3} style={{ textAlign: 'right' }}>Итого по вашим товарам:</td>
                                    <td>
                                        {Number(itemsTotal).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Buyer info */}
                    <div className="ord-card">
                        <div className="ord-card__header">
                            <span className="ord-card__title">Информация о покупателе</span>
                        </div>
                        <div className="ord-card__body">
                            <div className="ord-info-row">
                                <span className="ord-info-row__label">Покупатель</span>
                                <span className="ord-info-row__value">{order.buyer.name}</span>
                            </div>
                            <div className="ord-info-row">
                                <span className="ord-info-row__label">Email</span>
                                <span className="ord-info-row__value">{order.buyer.email}</span>
                            </div>
                            {order.region && (
                                <div className="ord-info-row">
                                    <span className="ord-info-row__label">Регион</span>
                                    <span className="ord-info-row__value">{order.region.name}</span>
                                </div>
                            )}
                            {order.delivery_address && (
                                <div className="ord-info-row">
                                    <span className="ord-info-row__label">Адрес доставки</span>
                                    <span className="ord-info-row__value">{order.delivery_address}</span>
                                </div>
                            )}
                            <div className="ord-info-row">
                                <span className="ord-info-row__label">Способ доставки</span>
                                <span className="ord-info-row__value">
                                    {DELIVERY_METHOD_LABELS[order.delivery_method] ?? order.delivery_method ?? '—'}
                                </span>
                            </div>
                            {order.payment_method && (
                                <div className="ord-info-row">
                                    <span className="ord-info-row__label">Способ оплаты</span>
                                    <span className="ord-info-row__value">
                                        {PAYMENT_METHOD_LABELS[order.payment_method] ?? order.payment_method}
                                    </span>
                                </div>
                            )}
                            {order.comment && (
                                <div className="ord-info-row">
                                    <span className="ord-info-row__label">Комментарий</span>
                                    <span className="ord-info-row__value">{order.comment}</span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Payment info */}
                    <div className="ord-card">
                        <div className="ord-card__header">
                            <span className="ord-card__title">Оплата</span>
                            <span className={`ord-badge ord-badge--pay-${order.payment_status === 'paid' ? 'paid' : order.payment_status === 'failed' ? 'failed' : 'pending'}`}>
                                {PAYMENT_STATUS_LABELS[order.payment_status] ?? order.payment_status}
                            </span>
                        </div>
                        <div className="ord-card__body">
                            <div className="ord-info-row">
                                <span className="ord-info-row__label">Сумма заказа</span>
                                <span className="ord-info-row__value">
                                    {Number(order.total).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽
                                </span>
                            </div>
                            {order.discount > 0 && (
                                <div className="ord-info-row">
                                    <span className="ord-info-row__label">Скидка</span>
                                    <span className="ord-info-row__value" style={{ color: '#10b981' }}>
                                        −{Number(order.discount).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽
                                    </span>
                                </div>
                            )}
                            <div className="ord-info-row">
                                <span className="ord-info-row__label">Ваша сумма</span>
                                <span className="ord-info-row__value" style={{ fontWeight: 700 }}>
                                    {Number(itemsTotal).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ₽
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Right column */}
                <div>
                    {/* Status management */}
                    <div className="ord-card">
                        <div className="ord-card__header">
                            <span className="ord-card__title">Управление статусом</span>
                        </div>
                        <div className="ord-card__body ord-status-section">
                            <div className="ord-status-current">
                                <span className="ord-status-label">Текущий статус:</span>
                                <span className={`ord-badge ord-badge--${order.status}`}>
                                    {statusLabels[order.status] ?? order.status}
                                </span>
                            </div>

                            {nextStatuses.length > 0 ? (
                                <div className="ord-next-statuses">
                                    {nextStatuses.map((s) => (
                                        <button
                                            key={s.value}
                                            type="button"
                                            className="ord-next-status-btn"
                                            disabled={submitting}
                                            onClick={() => handleStatusUpdate(s.value)}
                                        >
                                            → {s.label}
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <div className="ord-no-transitions">
                                    {order.status === 'ISSUED' && 'Заказ выдан покупателю'}
                                    {order.status === 'DELIVERED' && 'Заказ в пункте выдачи — ожидает выдачи'}
                                    {order.status === 'CANCELED' && 'Заказ отменён'}
                                    {order.status === 'REFUSED' && 'Покупатель отказался от получения'}
                                    {!['ISSUED','DELIVERED','CANCELED','REFUSED'].includes(order.status) && 'Изменение статуса недоступно'}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Timeline */}
                    <div className="ord-card">
                        <div className="ord-card__header">
                            <span className="ord-card__title">История</span>
                        </div>
                        <div className="ord-card__body">
                            <div className="ord-timeline">
                                <div className="ord-timeline-item">
                                    <div className="ord-timeline-dot"></div>
                                    <div className="ord-timeline-line"></div>
                                    <div className="ord-timeline-content">
                                        <div className="ord-timeline-title">Заказ создан</div>
                                        <div className="ord-timeline-date">{order.created_at}</div>
                                    </div>
                                </div>
                                {order.created_at !== order.updated_at && (
                                    <div className="ord-timeline-item">
                                        <div className="ord-timeline-dot"></div>
                                        <div className="ord-timeline-line"></div>
                                        <div className="ord-timeline-content">
                                            <div className="ord-timeline-title">
                                                Статус: {statusLabels[order.status] ?? order.status}
                                            </div>
                                            <div className="ord-timeline-date">{order.updated_at}</div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Quick info */}
                    <div className="ord-card">
                        <div className="ord-card__header">
                            <span className="ord-card__title">Детали заказа</span>
                        </div>
                        <div className="ord-card__body">
                            <div className="ord-info-row">
                                <span className="ord-info-row__label">Номер заказа</span>
                                <span className="ord-info-row__value" style={{ fontFamily: 'monospace' }}>
                                    {order.number}
                                </span>
                            </div>
                            <div className="ord-info-row">
                                <span className="ord-info-row__label">Код заказа</span>
                                <span className="ord-info-row__value" style={{ fontFamily: 'monospace' }}>
                                    {order.order_code}
                                </span>
                            </div>
                            <div className="ord-info-row">
                                <span className="ord-info-row__label">Дата создания</span>
                                <span className="ord-info-row__value">{order.created_at}</span>
                            </div>
                            <div className="ord-info-row">
                                <span className="ord-info-row__label">Обновлён</span>
                                <span className="ord-info-row__value">{order.updated_at}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SellerLayout>
    );
}
