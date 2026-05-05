import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import PaymentModal from '@/Components/PaymentModal';
import '../../../css/profile/order-show.css';

export default function OrderShow({ auth, order }) {
    const [paymentModalOpen, setPaymentModalOpen] = useState(false);

    const total = parseFloat(order?.total || 0);

    const isUnpaid =
        order?.payment_status !== 'paid' &&
        !['canceled', 'returned', 'issued'].includes(order?.status);

    const statusMap = {
        new: 'Новый заказ',
        processing: 'Собирается',
        ready_for_pickup: 'Готов к выдаче',
        at_pvz: 'Можно забирать',
        issued: 'Получен',
        canceled: 'Отменён',
        returned: 'Возврат',
    };

    const statusText = statusMap[order?.status] || 'Заказ';

    if (!order) {
        return (
            <MainLayout auth={auth}>
                <div className="order-not-found">
                    <h2>Заказ не найден</h2>
                </div>
            </MainLayout>
        );
    }

    return (
        <MainLayout auth={auth}>
            <Head title={`Заказ ${order.number}`} />

            <div className="order-page">

                <a href="/profile/orders" className="order-back">
                    ← К списку заказов
                </a>

                <div className="order-header">
                    <div>
                        <h1>
                            Заказ от {new Date(order.created_at).toLocaleDateString('ru-RU', {
                                day: 'numeric',
                                month: 'long',
                            })}
                        </h1>
                        <div className="order-status">{statusText}</div>
                    </div>

                    <div className="order-number">
                        № {order.number}
                    </div>
                </div>

                <div className="order-grid">

                    {/* ЛЕВАЯ КОЛОНКА */}
                    <div className="order-left">

                        {/* доставка */}
                        <div className="order-block">
                            <h3 className="green-title">
                                {order.status === 'at_pvz' ? 'Можно забирать' : statusText}
                            </h3>

                            <p className="muted">
                                {order.delivery_address || 'Пункт выдачи пока не указан'}
                            </p>
                        </div>

                        {/* получатель */}
                        <div className="order-block">
                            <h3>Получатель</h3>

                            <p>
                                {auth.user?.name || 'Не указан'}
                            </p>

                            <p className="muted">
                                {auth.user?.phone || 'Телефон не указан'}
                            </p>
                        </div>

                        {/* товары */}
                        <div className="order-block">
                            {order.items?.map((item) => (
                                <div key={item.id} className="order-item">

                                    <img
                                        className="order-item-image"
                                        src={item.variant?.product?.image || '/img/default.jpg'}
                                        alt={item.variant?.product?.title}
                                    />

                                    <div className="order-item-info">
                                        <div className="order-item-title">
                                            {item.variant?.product?.title}
                                        </div>

                                        <div className="order-item-price">
                                            {Number(item.price_at_purchase).toLocaleString()} ₽
                                        </div>

                                        <div className="order-item-qty">
                                            {item.quantity} шт.
                                        </div>
                                    </div>

                                    <div className="order-item-total">
                                        {(item.quantity * item.price_at_purchase).toLocaleString()} ₽
                                    </div>

                                </div>
                            ))}
                        </div>
                    </div>

                    {/* ПРАВАЯ КОЛОНКА */}
                    <div className="order-right">

                        {/* оплата */}
                        <div className="order-block">
                            <h3>Ваш заказ</h3>

                            <div className="summary-row">
                                <span>Товары</span>
                                <span>{total.toLocaleString()} ₽</span>
                            </div>

                            <div className="summary-row">
                                <span>Доставка</span>
                                <span>Без доплат</span>
                            </div>

                            <div className="summary-row total">
                                <strong>К оплате</strong>
                                <strong>{total.toLocaleString()} ₽</strong>
                            </div>

                            {isUnpaid && (
                                <button
                                    className="pay-btn"
                                    onClick={() => setPaymentModalOpen(true)}
                                >
                                    Оплатить
                                </button>
                            )}
                        </div>

                        {/* код получения */}
                        <div className="order-block">
                            <h3>Получите товары по коду</h3>

                            <div className="pickup-code">
                                {order.order_code || '1409 3864 3907'}
                            </div>

                            <p className="muted">
                                Один код действует на весь заказ
                            </p>
                        </div>

                        {/* отмена */}
                        {['new', 'processing'].includes(order.status) && (
                            <div className="order-block">
                                <button
                                    className="cancel-btn"
                                    onClick={() => {
                                        if (confirm('Отменить заказ?')) {
                                            router.post(`/order/${order.id}/cancel`);
                                        }
                                    }}
                                >
                                    Отменить заказ
                                </button>
                            </div>
                        )}

                    </div>
                </div>

                <PaymentModal
                    isOpen={paymentModalOpen}
                    onClose={() => setPaymentModalOpen(false)}
                    order={order}
                    type="order"
                />
            </div>
        </MainLayout>
    );
}