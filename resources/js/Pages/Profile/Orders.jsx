import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import PaymentModal from '@/Components/PaymentModal';
import '../../../css/profile/orders.css';

export default function Orders({ auth, orders = [] }) {
    const [expandedOrder, setExpandedOrder] = useState(null);
    const [paymentModal, setPaymentModal] = useState({ isOpen: false, order: null });

    const activeOrders = orders.filter(o =>
        !['issued', 'canceled', 'returned'].includes(o.status)
    );

    const completedOrders = orders.filter(o =>
        ['issued', 'canceled', 'returned'].includes(o.status)
    );

    const [tab, setTab] = useState('active');
    const current = tab === 'active' ? activeOrders : completedOrders;

    const toggleExpand = (id) => {
        setExpandedOrder(expandedOrder === id ? null : id);
    };

    const openOrder = (id) => {
        router.visit(route('order.show', id));
    };

    const cancelOrder = (id) => {
        if (confirm('Отменить заказ?')) {
            router.post(`/order/${id}/cancel`, {}, {
                onSuccess: () => router.reload()
            });
        }
    };

    const canPay = (order) => {
        return order.payment_status !== 'paid'
            && !['canceled', 'returned'].includes(order.status);
    };

    return (
        <MainLayout auth={auth}>
            <Head title="Мои заказы" />

            <div className="orders-page">

                {/* 🔥 БЛОК С КОДОМ */}
                {activeOrders.length > 0 && (
                    <div className="pickup-code-block">
                        <div className="pickup-code-title">Код для получения</div>
                        <div className="pickup-code-number">
                            {Math.floor(1000 + Math.random() * 9000)}{' '}
                            {Math.floor(1000 + Math.random() * 9000)}
                        </div>
                        <div className="pickup-code-desc">
                            Покажите этот код в пункте выдачи
                        </div>
                    </div>
                )}

                {/* Табы */}
                <div className="orders-tabs">
                    <button
                        className={tab === 'active' ? 'active' : ''}
                        onClick={() => setTab('active')}
                    >
                        Активные ({activeOrders.length})
                    </button>

                    <button
                        className={tab === 'completed' ? 'active' : ''}
                        onClick={() => setTab('completed')}
                    >
                        Архив ({completedOrders.length})
                    </button>
                </div>

                {/* ПУСТО */}
                {current.length === 0 && (
                    <div className="orders-empty">
                        <img src="/img/orders/empty.png" />
                        <h3>Нет заказов</h3>
                        <a href="/">К покупкам</a>
                    </div>
                )}

                {/* СПИСОК */}
                {current.map(order => {
                    const first = order.items[0];

                    return (
                        <div key={order.id} className="order-card">

                            {/* HEADER */}
                            <div className="order-header" onClick={() => openOrder(order.id)}>
                                <div>
                                    <div className="order-number">
                                        Заказ №{order.number || order.id}
                                    </div>
                                    <div className="order-date">
                                        {new Date(order.created_at).toLocaleDateString()}
                                    </div>
                                </div>

                                <div className={`status ${order.status}`}>
                                    {order.status}
                                </div>
                            </div>

                            {/* BODY */}
                            <div className="order-body">

                                {/* LEFT */}
                                <div className="order-left">
                                    <img
                                        src={first?.variant?.product?.image || '/img/default.jpg'}
                                    />

                                    <div>
                                        <div className="title">
                                            {first?.variant?.product?.title}
                                        </div>

                                        {order.items.length > 1 && (
                                            <div className="more">
                                                + ещё {order.items.length - 1} товаров
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* RIGHT */}
                                <div className="order-right">
                                    <div className="price">
                                        {parseFloat(order.total).toLocaleString()} ₽
                                    </div>

                                    <div className="actions">
                                        {canPay(order) && (
                                            <button
                                                className="pay"
                                                onClick={() => setPaymentModal({ isOpen: true, order })}
                                            >
                                                Оплатить
                                            </button>
                                        )}

                                        {order.status === 'new' && (
                                            <button
                                                className="cancel"
                                                onClick={() => cancelOrder(order.id)}
                                            >
                                                Отменить
                                            </button>
                                        )}

                                        <button
                                            className="details"
                                            onClick={() => openOrder(order.id)}
                                        >
                                            Подробнее
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {/* EXPANDED */}
                            {expandedOrder === order.id && (
                                <div className="order-expanded">
                                    {order.items.map(item => (
                                        <div key={item.id} className="expanded-item">
                                            <img src={item.variant?.product?.image} />

                                            <div>
                                                {item.variant?.product?.title}
                                                <div>
                                                    {item.price_at_purchase} ₽ × {item.quantity}
                                                </div>
                                            </div>

                                            <div>
                                                {(item.price_at_purchase * item.quantity).toLocaleString()} ₽
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <div
                                className="expand-toggle"
                                onClick={() => toggleExpand(order.id)}
                            >
                                {expandedOrder === order.id ? 'Скрыть ▲' : 'Подробнее ▼'}
                            </div>
                        </div>
                    );
                })}

            </div>

            <PaymentModal
                isOpen={paymentModal.isOpen}
                onClose={() => setPaymentModal({ isOpen: false, order: null })}
                order={paymentModal.order}
                type="order"
            />
        </MainLayout>
    );
}