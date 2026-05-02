import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import PaymentModal from '@/Components/PaymentModal';
import '../../../css/profile/orders.css';

export default function Orders({ auth, orders = [] }) {
    const [expandedOrder, setExpandedOrder] = useState(null);
    const [paymentModal, setPaymentModal] = useState({ isOpen: false, order: null });

    // Маппинг статусов
    const statusConfig = {
        'new': { text: 'Новый заказ', class: 'status-new', icon: '🆕' },
        'paid': { text: 'Оплачен', class: 'status-paid', icon: '✅' },
        'processing': { text: 'Комплектуется', class: 'status-processing', icon: '📦' },
        'ready_for_pickup': { text: 'Готов к выдаче', class: 'status-ready', icon: '🏪' },
        'in_transit': { text: 'В пути', class: 'status-transit', icon: '🚚' },
        'at_pvz': { text: 'В пункте выдачи', class: 'status-pvz', icon: '📍' },
        'issued': { text: 'Получен', class: 'status-issued', icon: '🎉' },
        'canceled': { text: 'Отменён', class: 'status-canceled', icon: '❌' },
        'returned': { text: 'Возврат', class: 'status-returned', icon: '↩️' }
    };

    // Неоплаченные заказы
    const unpaidOrders = orders.filter(o => 
        o.payment_status !== 'paid' && !['canceled', 'returned'].includes(o.status)
    );
    
    const totalUnpaidAmount = unpaidOrders.reduce((sum, o) => sum + parseFloat(o.total), 0);

    // Активные заказы
    const activeOrders = orders.filter(o => 
        !['issued', 'canceled', 'returned'].includes(o.status)
    );

    // Завершённые заказы
    const completedOrders = orders.filter(o => 
        ['issued', 'canceled', 'returned'].includes(o.status)
    );

    const [activeTab, setActiveTab] = useState('active');
    const currentOrders = activeTab === 'active' ? activeOrders : completedOrders;

    const toggleExpand = (orderId) => {
        setExpandedOrder(expandedOrder === orderId ? null : orderId);
    };

    const getStatusInfo = (status) => {
        return statusConfig[status] || { text: status, class: 'status-default', icon: '📋' };
    };

    // Открыть детальную страницу заказа
    const openOrderDetails = (orderId) => {
        router.visit(route('order.show', orderId));
    };

    // Открыть модалку оплаты для заказа
    const paySingleOrder = (order) => {
        setPaymentModal({ isOpen: true, order: order });
    };

    // Отменить заказ
    const cancelOrder = (orderId) => {
        if (confirm('Вы уверены, что хотите отменить заказ?')) {
            router.post(`/order/${orderId}/cancel`, {}, {
                onSuccess: () => router.reload()
            });
        }
    };

    return (
        <MainLayout auth={auth}>
            <Head title="Мои заказы" />

            <div className="orders-container">
                <div className="orders-header">
                    <div>
                        <h1>Мои заказы</h1>
                        <p>История и статус ваших заказов</p>
                    </div>
                </div>

                {/* Табы */}
                <div className="orders-tabs">
                    <button
                        className={`orders-tab ${activeTab === 'active' ? 'active' : ''}`}
                        onClick={() => setActiveTab('active')}
                    >
                        Активные заказы
                        {activeOrders.length > 0 && (
                            <span className="tab-count">{activeOrders.length}</span>
                        )}
                    </button>
                    <button
                        className={`orders-tab ${activeTab === 'completed' ? 'active' : ''}`}
                        onClick={() => setActiveTab('completed')}
                    >
                        Архив заказов
                        {completedOrders.length > 0 && (
                            <span className="tab-count">{completedOrders.length}</span>
                        )}
                    </button>
                </div>

                {/* Пустое состояние */}
                {currentOrders.length === 0 && (
                    <div className="orders-empty">
                        <img src="/img/orders/empty.png" alt="Нет заказов" />
                        <h3>У вас пока нет заказов</h3>
                        <p>Перейдите в каталог и выберите товары для покупки</p>
                        <a href="/" className="orders-empty-btn">Перейти в каталог</a>
                    </div>
                )}

                {/* Список заказов */}
                <div className="orders-list">
                    {currentOrders.map(order => {
                        const statusInfo = getStatusInfo(order.status);
                        const firstItem = order.items[0];
                        const itemsCount = order.items.length;
                        const otherItemsCount = itemsCount - 1;
                        const isUnpaid = order.payment_status !== 'paid' && order.status !== 'canceled';

                        return (
                            <div key={order.id} className="order-card">
                                {/* Шапка заказа */}
                                <div className="order-card-header" onClick={() => openOrderDetails(order.id)}>
                                    <div className="order-info-left">
                                        <div className="order-number">
                                            Заказ №{order.number || order.id}
                                        </div>
                                        <div className="order-date">
                                            от {new Date(order.created_at).toLocaleDateString('ru-RU')}
                                        </div>
                                    </div>
                                    <div className="order-info-right">
                                        <div className={`order-status ${statusInfo.class}`}>
                                            <span className="status-icon">{statusInfo.icon}</span>
                                            <span className="status-text">{statusInfo.text}</span>
                                        </div>
                                        <button 
                                            className="order-expand-btn"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                toggleExpand(order.id);
                                            }}
                                        >
                                            {expandedOrder === order.id ? 'Свернуть ▲' : 'Подробнее ▼'}
                                        </button>
                                    </div>
                                </div>

                                {/* Основной контент заказа */}
                                <div className="order-card-content">
                                    <div className="order-items-preview">
                                        <div className="preview-image">
                                            <img 
                                                src={firstItem?.variant?.product?.image || '/img/products/default.jpg'} 
                                                alt={firstItem?.variant?.product?.title}
                                                onError={(e) => { e.target.src = '/img/products/default.jpg'; }}
                                            />
                                        </div>
                                        <div className="preview-info">
                                            <div className="preview-title">
                                                {firstItem?.variant?.product?.title}
                                            </div>
                                            {otherItemsCount > 0 && (
                                                <div className="preview-other">
                                                    + ещё {otherItemsCount} товар(ов)
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="order-total">
                                        <div className="total-label">Сумма</div>
                                        <div className="total-price">{parseFloat(order.total).toLocaleString()} ₽</div>
                                        {order.payment_status === 'paid' && (
                                            <div className="payment-status paid">Оплачен</div>
                                        )}
                                        {isUnpaid && (
                                            <div className="payment-status unpaid">Не оплачен</div>
                                        )}
                                    </div>

                                    <div className="order-actions">
                                        {isUnpaid && (
                                            <button 
                                                className="btn-pay" 
                                                onClick={() => paySingleOrder(order)}
                                            >
                                                Оплатить
                                            </button>
                                        )}
                                        {order.status === 'issued' && (
                                            <button className="btn-repeat" onClick={() => {
                                                order.items.forEach(item => {
                                                    router.post('/cart/add', {
                                                        variant_id: item.variant.id,
                                                        quantity: item.quantity
                                                    });
                                                });
                                                router.visit('/profile/cart');
                                            }}>
                                                Повторить
                                            </button>
                                        )}
                                        {order.status === 'new' && (
                                            <button className="btn-cancel" onClick={() => cancelOrder(order.id)}>
                                                Отменить
                                            </button>
                                        )}
                                        <button 
                                            className="btn-details" 
                                            onClick={() => openOrderDetails(order.id)}
                                        >
                                            Подробнее
                                        </button>
                                    </div>
                                </div>

                                {/* Развёрнутая информация */}
                                {expandedOrder === order.id && (
                                    <div className="order-expanded">
                                        <div className="expanded-section">
                                            <h4>Информация о доставке</h4>
                                            <p>Способ: {order.delivery_method === 'pvz' ? 'Пункт выдачи' : 'Курьером'}</p>
                                            <p>Адрес: {order.delivery_address || 'Не указан'}</p>
                                        </div>

                                        <div className="expanded-section">
                                            <h4>Состав заказа</h4>
                                            <div className="expanded-items">
                                                {order.items.map(item => (
                                                    <div key={item.id} className="expanded-item">
                                                        <img 
                                                            src={item.variant?.product?.image || '/img/products/default.jpg'} 
                                                            alt={item.variant?.product?.title}
                                                        />
                                                        <div className="expanded-item-info">
                                                            <div className="item-title">{item.variant?.product?.title}</div>
                                                            <div className="item-price">{item.price_at_purchase.toLocaleString()} ₽ × {item.quantity}</div>
                                                        </div>
                                                        <div className="item-total">
                                                            {(item.price_at_purchase * item.quantity).toLocaleString()} ₽
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Модалка оплаты */}
            <PaymentModal
                isOpen={paymentModal.isOpen}
                onClose={() => setPaymentModal({ isOpen: false, order: null })}
                order={paymentModal.order}
                type="order"
            />
        </MainLayout>
    );
}