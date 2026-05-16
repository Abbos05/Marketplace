import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import PaymentModal from '@/Components/PaymentModal';
import Barcode from 'react-barcode';
import '../../../css/profile/order-show.css';

const PAYMENT_STATUS_LABELS = {
    pending: 'Ожидает оплаты',
    paid: 'Оплачен',
    failed: 'Ошибка оплаты',
    refunded: 'Деньги возвращены на карту',
};

export default function OrderShow({ auth, order, deliveryTrack = null, documents = null }) {
    const { flash } = usePage().props;
    const code = order?.order_code || '123456789';

    const [barcodeOpen, setBarcodeOpen] = useState(false);

    const [paymentModalOpen, setPaymentModalOpen] = useState(false);
    const [deliveryDetailsOpen, setDeliveryDetailsOpen] = useState(false);
    const [refundSubmitting, setRefundSubmitting] = useState(false);


    // время для написание отзыва
    const canReview = (() => {
        if (order.status !== 'ISSUED') return false;

        const issuedDate = new Date(order.updated_at);
        const now = new Date();

        const diff = (now - issuedDate) / (1000 * 60 * 60 * 24);

        return diff <= 90; // 3 месяца
    })();
    const total = parseFloat(order?.total || 0);

    const needsPayment =
        order?.payment_status !== 'paid' &&
        order?.payment_status !== 'refunded' &&
        !['CANCELED', 'REFUSED'].includes(order?.status);

    const canRequestRefund =
        order?.payment_status === 'paid' &&
        ['CANCELED', 'REFUSED'].includes(order?.status);

    const isRefunded = order?.payment_status === 'refunded';

    const handleRefund = () => {
        if (refundSubmitting) return;
        setRefundSubmitting(true);
        router.visit(route('order.refund.checkout', order.id), {
            onFinish: () => setRefundSubmitting(false),
        });
    };

    const statusMap = {
        NEW: 'Новый заказ',
        INTRANSIT: 'В пути',
        DELIVERED: 'В пункте выдачи',
        ISSUED: 'Выдан',
        CANCELED: 'Отменён',
        REFUSED: 'Отказ от получения',
    };

    const statusText = statusMap[order?.status] || 'Ожидает подтверждение';

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

                {flash?.success && <div className="order-flash order-flash--success">{flash.success}</div>}
                {flash?.error && <div className="order-flash order-flash--error">{flash.error}</div>}

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
                        <button
                            type="button"
                            className="order-back"
                            style={{ marginTop: 8, border: '1px solid #cbd5e1', borderRadius: 8, padding: '6px 12px', background: '#fff', cursor: 'pointer' }}
                            onClick={() => router.post(route('messages.open'), { type: 'order', order_id: order.id })}
                        >
                            Чат по заказу
                        </button>
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
                    <div className="order-left ">

                        {/* доставка */}
                        <div className="order-block ">
                            <h3 className="green-title">
                                {order.status === 'INTRANSIT' ? 'В пути к вам' : statusText}
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
                        <div className="order-block order-blockSc">
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
                                <span>0 ₽</span>
                            </div>

                            <div className="summary-row total">
                                <strong>К оплате</strong>
                                <strong>{total.toLocaleString()} ₽</strong>
                            </div>

                            <div className={`order-payment-status order-payment-status--${order.payment_status}`}>
                                <span>Оплата:</span>
                                <strong>{PAYMENT_STATUS_LABELS[order.payment_status] ?? order.payment_status}</strong>
                            </div>

                            {isRefunded && (
                                <p className="order-refund-hint muted">
                                    Возврат оформлен через Stripe. Срок зачисления зависит от банка (обычно 3–10 рабочих дней).
                                </p>
                            )}

                            {canRequestRefund && (
                                <>
                                    <p className="order-refund-hint muted">
                                        Откроется страница подтверждения возврата (как при оплате картой).
                                    </p>
                                    <button
                                        type="button"
                                        className="refund-btn"
                                        disabled={refundSubmitting}
                                        onClick={handleRefund}
                                    >
                                        {refundSubmitting ? 'Открываем…' : 'Вернуть деньги на карту'}
                                    </button>
                                </>
                            )}

                            {needsPayment && (
                                <>

                                    <button
                                        type="button"
                                        className="pay-btn"
                                        onClick={() => setPaymentModalOpen(true)}
                                    >
                                        Оплатить заказ
                                    </button>
                                </>
                            )}
                            {order.status === 'NEW' && (
                                <button
                                    className="cancel-btn"
                                    onClick={() => {
                                        const msg = order.payment_status === 'paid'
                                            ? 'Отменить заказ? Оплата будет возвращена на карту через Stripe (3–10 рабочих дней).'
                                            : 'Отменить заказ?';
                                        if (confirm(msg)) {
                                            router.post(`/order/${order.id}/cancel`);
                                        }
                                    }}
                                >
                                    Отменить заказ
                                </button>
                            )}
                        </div>

                        {deliveryTrack?.steps?.length > 0 && (
                            <div className="order-block order-track-card">
                                <button
                                    type="button"
                                    className="order-track-toggle"
                                    onClick={() => setDeliveryDetailsOpen((v) => !v)}
                                    aria-expanded={deliveryDetailsOpen}
                                >
                                    <span className="order-track-toggle__main">
                                        <span className="order-track-toggle__title">Детали доставки</span>
                                        <span className="order-track-toggle__summary">{deliveryTrack.summary}</span>
                                    </span>
                                    <span className={`order-track-toggle__chevron ${deliveryDetailsOpen ? 'is-open' : ''}`} aria-hidden />
                                </button>

                                {deliveryDetailsOpen && (
                                    <div className="order-track-body">
                                        <div className="order-track-chips">
                                            <span className="order-track-chip">{deliveryTrack.method_label}</span>
                                            {deliveryTrack.region && (
                                                <span className="order-track-chip order-track-chip--muted">{deliveryTrack.region}</span>
                                            )}
                                        </div>
                                        <p className="order-track-destination">
                                            <span className="order-track-destination__label">Куда везём</span>
                                            <span className="order-track-destination__value">{deliveryTrack.destination}</span>
                                        </p>
                                        {deliveryTrack.eta_hint ? (
                                            <p className="order-track-eta">{deliveryTrack.eta_hint}</p>
                                        ) : null}

                                        <div className="order-track-timeline" role="list">
                                            {deliveryTrack.steps.map((step, idx) => (
                                                <div
                                                    key={step.id}
                                                    className={`order-track-step order-track-step--${step.state}`}
                                                    role="listitem"
                                                >
                                                    <div className="order-track-step__rail">
                                                        <span className="order-track-step__dot" />
                                                        {idx < deliveryTrack.steps.length - 1 ? (
                                                            <span className="order-track-step__line" aria-hidden />
                                                        ) : null}
                                                    </div>
                                                    <div className="order-track-step__content">
                                                        <div className="order-track-step__title">{step.title}</div>
                                                        <div className="order-track-step__detail">{step.detail}</div>
                                                        {step.meta ? (
                                                            <div className="order-track-step__meta">{step.meta}</div>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}


                        {/* код получения */}
                        <div className="order-block">
                            <h3>Получите товары по коду</h3>

                            <div className="barcode-container">
                                <div className="barcode-box-pink" onClick={() => setBarcodeOpen(true)}>
                                    <Barcode value={code.replace(/\s/g, '')} />
                                </div>
                               
                            </div>
                            <div className="pickup-code">
                                {code}
                            </div>
                            <p className="muted-barcode-box-pink-container">
                                    Один код действует на весь заказ
                                </p>
                        </div>
                        {documents && (
                            <div className="order-block order-documents">
                                <h3>Документы</h3>
                                <ul className="order-documents__list">
                                    {documents.receipt && (
                                        <li>
                                            <a href={documents.receipt} className="order-documents__link" target="_blank" rel="noopener noreferrer">
                                                Чек по заказу (PDF)
                                            </a>
                                        </li>
                                    )}
                                    {documents.payment && (
                                        <li>
                                            <a href={documents.payment} className="order-documents__link" target="_blank" rel="noopener noreferrer">
                                                Документ об оплате (PDF)
                                            </a>
                                        </li>
                                    )}
                                    {documents.refund && (
                                        <li>
                                            <a href={documents.refund} className="order-documents__link" target="_blank" rel="noopener noreferrer">
                                                Документ о возврате (PDF)
                                            </a>
                                        </li>
                                    )}
                                    {documents.cancel && (
                                        <li>
                                            <a href={documents.cancel} className="order-documents__link" target="_blank" rel="noopener noreferrer">
                                                Документ об отмене (PDF)
                                            </a>
                                        </li>
                                    )}
                                </ul>
                            </div>
                        )}



                    </div>
                </div>
                {barcodeOpen && (

                    <div className="barcode-modal" onClick={() => setBarcodeOpen(false)}>
                        <div className="barcode-box" onClick={(e) => e.stopPropagation()}>
                            <Barcode value={code.replace(/\s/g, '')} />
                        </div>
                    </div>
                )}
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