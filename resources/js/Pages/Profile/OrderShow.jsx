// resources/js/Pages/Profile/OrderShow.jsx
import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import PaymentModal from '@/Components/PaymentModal';
import '../../../css/profile/order-show.css';

export default function OrderShow({ auth, order }) {
    const [paymentModalOpen, setPaymentModalOpen] = useState(false);

    const total = parseFloat(order?.total) || 0;
    const isUnpaid = order?.payment_status !== 'paid' && order?.status !== 'canceled';

    // Статусы
    const statusMap = {
        'new': { text: 'Новый заказ', class: 'status-new' },
        'paid': { text: 'Оплачен', class: 'status-paid' },
        'processing': { text: 'Комплектуется', class: 'status-processing' },
        'ready_for_pickup': { text: 'Готов к выдаче', class: 'status-ready' },
        'in_transit': { text: 'В пути', class: 'status-transit' },
        'at_pvz': { text: 'В пункте выдачи', class: 'status-pvz' },
        'issued': { text: 'Получен', class: 'status-issued' },
        'canceled': { text: 'Отменён', class: 'status-canceled' },
        'returned': { text: 'Возврат', class: 'status-returned' }
    };

    const status = statusMap[order?.status] || { text: order?.status, class: '' };

    if (!order) {
        return (
            <MainLayout auth={auth}>
                <div className="order-not-found">
                    <h2>Заказ не найден</h2>
                    <a href="/orders">← Вернуться к заказам</a>
                </div>
            </MainLayout>
        );
    }

    return (
        <MainLayout auth={auth}>
            <Head title={`Заказ ${order.number || order.id}`} />

            <div className="order-detail-page">
                <div className="order-detail-container">
                    {/* Навигация */}
                    <div className="order-detail-nav">
                        <a href="/orders" className="back-link">← К списку заказов</a>
                    </div>

                    {/* Заголовок */}
                    <div className="order-detail-header">
                        <div>
                            <h1>
                                Заказ от {new Date(order.created_at).toLocaleDateString('ru-RU', {
                                    day: 'numeric', month: 'long'
                                })}
                            </h1>
                            <div className={`order-detail-status ${status.class}`}>
                                {status.text}
                            </div>
                        </div>
                        <div className="order-detail-number">
                            № {order.number || order.id}
                        </div>
                    </div>

                    {/* Статус доставки */}
                    <div className="order-delivery-status">
                        <div className="delivery-status-icon">📍</div>
                        <div className="delivery-status-info">
                            <h3>Доставлено в пункт выдачи</h3>
                            <p>Пункт Ozon, Россия, Иркутск, Проходной проезд, 2</p>
                            <p className="delivery-deadline">
                                Можно забирать до {new Date(order.updated_at).setDate(new Date(order.updated_at).getDate() + 14)}
                                (через 14 дней)
                            </p>
                        </div>
                    </div>

                    {/* Информация о получателе */}
                    <div className="order-recipient">
                        <h3>Получатель</h3>
                        <p>{auth.user?.name || 'Не указан'}, {auth.user?.phone || 'Не указан'}</p>
                        <p className="recipient-note">
                            Забрать заказ может любой человек по вашему штрихкоду или коду получения
                        </p>
                    </div>

                    {/* Товары */}
                    <div className="order-items-detail">
                        {order.items?.map(item => (
                            <div key={item.id} className="order-item-detail">
                                <img
                                    src={item.variant?.product?.image || '/img/default.jpg'}
                                    alt={item.variant?.product?.title}
                                />
                                <div className="item-detail-info">
                                    <div className="item-detail-title">
                                        {item.variant?.product?.title}
                                    </div>
                                    <div className="item-detail-price">
                                        {item.price_at_purchase.toLocaleString()} ₽
                                    </div>
                                    <div className="item-detail-quantity">
                                        Количество: {item.quantity}
                                    </div>
                                </div>
                                <div className="item-detail-total">
                                    {(item.price_at_purchase * item.quantity).toLocaleString()} ₽
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Сводка по заказу */}
                    <div className="order-summary-detail">
                        <div className="summary-row">
                            <span>Товары ({order.items?.length || 0} шт.)</span>
                            <span>{total.toLocaleString()} ₽</span>
                        </div>
                        <div className="summary-row">
                            <span>Доставка</span>
                            <span>Бесплатно</span>
                        </div>
                        <div className="summary-row total">
                            <span>К оплате</span>
                            <strong>{total.toLocaleString()} ₽</strong>
                        </div>

                        {isUnpaid && (
                            <button
                                className="order-pay-btn"
                                onClick={() => setPaymentModalOpen(true)}
                            >
                                Оплатить заказ
                            </button>
                        )}

                        {order.status === 'issued' && (
                            <button
                                className="order-repeat-btn"
                                onClick={() => {
                                    order.items.forEach(item => {
                                        router.post('/cart/add', {
                                            variant_id: item.variant.id,
                                            quantity: item.quantity
                                        });
                                    });
                                    router.visit('/profile/cart');
                                }}
                            >
                                Повторить заказ
                            </button>
                        )}
                    </div>

                    {/* Код получения */}
                    <div className="order-pickup-code">
                        <h3>Получите товары по коду</h3>
                        <div className="pickup-code-display">
                            {order.pickup_code || `${Math.floor(Math.random() * 10000)} ${Math.floor(Math.random() * 10000)}`}
                        </div>
                        <p className="code-note">
                            Код обновляется ежедневно. Если кто-то получает заказ за вас,
                            убедитесь, что у него актуальный код
                        </p>
                    </div>

                    {/* Действия */}
                    <div className="order-actions-detail">
                        <button className="action-support">Вопросы по заказу</button>
                        {order.status === 'new' && (
                            <button
                                className="action-cancel"
                                onClick={() => {
                                    if (confirm('Отменить заказ?')) {
                                        router.post(`/order/${order.id}/cancel`, {}, {
                                            onSuccess: () => router.reload()
                                        });
                                    }
                                }}
                            >
                                Отменить заказ
                            </button>
                        )}
                    </div>
                </div>
            </div>

          // Модалка
            <PaymentModal
                isOpen={paymentModalOpen}
                onClose={() => setPaymentModalOpen(false)}
                order={order}
                type="order"
            />
        </MainLayout>
    );
}