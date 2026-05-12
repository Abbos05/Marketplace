import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import PaymentModal from '@/Components/PaymentModal';
import '../../../css/profile/order-show.css';

export default function OrderShow({ auth, order }) {
    const [paymentModalOpen, setPaymentModalOpen] = useState(false);


    // время для написание отзыва
    const canReview = (() => {
        if (order.status !== 'issued') return false;

        const issuedDate = new Date(order.updated_at);
        const now = new Date();

        const diff = (now - issuedDate) / (1000 * 60 * 60 * 24);

        return diff <= 90; // 3 месяца
    })();
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

    const statusText = statusMap[order?.status] || 'Ожидает подстверждение';

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

                <a href="/orders" className="order-back">
                    ← К списку заказов
                </a>

                <div className="order-headerShow">
                    <div>
                        <h1>
                            Заказ от {new Date(order.created_at).toLocaleDateString('ru-RU', {
                                day: 'numeric',
                                month: 'long',
                            })}
                        </h1>
                    </div>

                    <div className="order-stroke">
                        <div className="order-number">
                        № {order.number}
                    </div>
                    <div className="order-number">
                                                <div className="order-storder-blockatus">{statusText}</div>

                    </div>
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
                            <h3>Товары</h3>

                            {order.items?.map((item) => {
                                const existingReview = item.review;

                                const [open, setOpen] = useState(false);
                                const [rating, setRating] = useState(existingReview?.rating || 0);
                                const [comment, setComment] = useState(existingReview?.comment || '');
                                const [hover, setHover] = useState(0);

                                // Внутри map, где создается форма для каждого товара

                                const handleSave = () => {
                                    // Проверяем, есть ли уже отзыв
                                    if (existingReview) {
                                        // Обновление существующего отзыва
                                        router.put(`/reviews/${existingReview.id}`, {
                                            rating,
                                            comment
                                        }, {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setOpen(false); // Скрываем форму

                                            }
                                        });
                                    } else {
                                        // Создание нового отзыва
                                        router.post('/reviews', {
                                            product_id: item.variant.product.id,
                                            variant_id: item.variant.id,
                                            order_id: order.id,
                                            rating,
                                            comment
                                        }, {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setOpen(false);
                                            }
                                        });
                                    }
                                };
                                return (
                                    <div key={item.id} className="order-item review-item" >

                                        <div className="order-item--data"
                                            onClick={() => {
                                                router.get(`/product/${item.variant?.product.id}`);
                                            }}>
                                            <img
                                                className="order-item-image"
                                                src={item.variant?.product?.image || '/img/products/default.png'}
                                            />

                                            <div className="order-item-info">
                                                <div className="order-item-title">
                                                    {item.variant?.product?.title}
                                                </div>

                                                <div className="order-item-price">
                                                    {Number(item.price_at_purchase).toLocaleString()} ₽
                                                </div>
                                            </div>
                                        </div>

                                        {/* ЕСЛИ ЕСТЬ ОТЗЫВ */}
                                        {existingReview && !open && (
                                            <div className="review-view">

                                                {/* верх */}
                                                <div className="review-top">

                                                    <div className="review-left">
                                                        <div className={`review-status ${existingReview.is_moderated ? 'ok' : 'pending'}`}>
                                                            {existingReview.is_moderated ? 'Опубликован' : 'На модерации'}
                                                        </div>
                                                    </div>

                                                    <div className="review-right">

                                                        {/* звезды */}
                                                        <div className="review-stars">
                                                            {[1, 2, 3, 4, 5].map(star => (
                                                                <span
                                                                    key={star}
                                                                    className={`star star-public ${existingReview.rating >= star ? 'active' : ''}`}
                                                                >
                                                                    ★
                                                                </span> 
                                                            ))}
                                                        </div>

                                                        <button
                                                            className="review-open-btn"
                                                            onClick={() => setOpen(true)}
                                                        >
                                                            Редактировать
                                                        </button>

                                                    </div>
                                                </div>

                                                {/* текст */}
                                                <div className="review-text">
                                                    {existingReview.comment}
                                                </div>

                                            </div>
                                        )}

                                        {/* КНОПКА ЕСЛИ НЕТ ОТЗЫВА */}
                                        {!existingReview && (
                                            <button
                                                className="review-open-btn"
                                                onClick={() => setOpen(true)}
                                            >
                                                Оставить отзыв
                                            </button>
                                        )}

                                        {/* ФОРМА */}
                                        {open && (
                                            <div className="review-box">

                                                <div className="review-stars">
                                                    {[1, 2, 3, 4, 5].map(star => (
                                                        <span
                                                            key={star}
                                                            className={`star ${(hover || rating) >= star ? 'active' : ''}`}
                                                            onMouseEnter={() => setHover(star)}
                                                            onMouseLeave={() => setHover(0)}
                                                            onClick={() => setRating(star)}
                                                        >
                                                            ★
                                                        </span>
                                                    ))}
                                                </div>

                                                <textarea
                                                    className="review-textarea"
                                                    value={comment}
                                                    onChange={(e) => {
                                                        setComment(e.target.value);

                                                        // авто-рост
                                                        e.target.style.height = 'auto';
                                                        e.target.style.height = e.target.scrollHeight + 'px';
                                                    }}
                                                />

                                                <div className="review-actions">
                                                    <button
                                                        type="button"
                                                        className="save-btn"
                                                        onClick={handleSave}
                                                    >
                                                        Сохранить
                                                    </button>

                                                    {existingReview && (
                                                        <button
                                                            type="button"
                                                            className="delete-btn"
                                                            onClick={() => {
                                                                if (confirm('Удалить отзыв?')) {
                                                                    router.delete(`/reviews/${existingReview.id}`, {
                                                                        preserveScroll: true,

                                                                        onSuccess: () => {
                                                                            setOpen(false);
                                                                        }
                                                                    });

                                                                }
                                                            }}
                                                        >
                                                            Удалить
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
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
                            {/* отмена */}
                            {['new', 'processing'].includes(order.status) && (
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