import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/profile/refund-checkout.css';

const STATUS_LABELS = {
    CANCELED: 'Отменён',
    REFUSED: 'Отказ от получения',
};

export default function RefundCheckout({ auth, order }) {
    const [loading, setLoading] = useState(false);
    const [step, setStep] = useState(1);

    const amount = parseFloat(order?.total || 0);

    const handleConfirm = () => {
        if (loading) return;
        setLoading(true);
        setStep(2);

        setTimeout(() => {
            router.post(route('order.refund.complete', order.id), {}, {
                onFinish: () => setLoading(false),
            });
        }, 1200);
    };

    return (
        <MainLayout auth={auth}>
            <Head title={`Возврат — заказ ${order.number}`} />

            <div className="refund-page">
                <Link
                    href={route('order.show', { order: order.id, view: 1 })}
                    className="refund-back"
                >
                    ← Назад к заказу
                </Link>

                <div className="refund-checkout-card">
                    <div className="refund-checkout-header">
                        <div className="refund-checkout-logo">Stripe</div>
                        <span className="refund-checkout-badge">Возврат средств</span>
                    </div>

                    <h1 className="refund-checkout-title">Возврат на карту</h1>

                    <div className="refund-checkout-amount">
                        <span className="refund-checkout-amount__label">Сумма возврата</span>
                        <span className="refund-checkout-amount__value">+ {amount.toLocaleString('ru-RU')} ₽</span>
                    </div>

                    <div className="refund-checkout-meta">
                        <div>
                            <span className="refund-checkout-meta__label">Заказ</span>
                            <span>№ {order.number}</span>
                        </div>
                        <div>
                            <span className="refund-checkout-meta__label">Статус</span>
                            <span>{STATUS_LABELS[order.status] ?? order.status}</span>
                        </div>
                    </div>

                    <div className="refund-checkout-steps">
                        <div className={`refund-step ${step >= 1 ? 'refund-step--done' : ''} ${step === 1 && loading ? 'refund-step--active' : ''}`}>
                            <span className="refund-step__dot" />
                            <span>Проверка заказа и оплаты</span>
                        </div>
                        <div className={`refund-step ${step >= 2 ? 'refund-step--active' : ''}`}>
                            <span className="refund-step__dot" />
                            <span>Возврат на банковскую карту</span>
                        </div>
                    </div>

                    {loading ? (
                        <div className="refund-checkout-loading">
                            <div className="refund-spinner" />
                            <p>Обрабатываем возврат…</p>
                        </div>
                    ) : (
                        <>
                            <button type="button" className="refund-confirm-btn" onClick={handleConfirm}>
                                Подтвердить возврат
                            </button>
                            <p className="refund-checkout-note">
                                Средства вернутся на карту, с которой была оплата (3–10 рабочих дней).
                            </p>
                        </>
                    )}
                </div>
            </div>
        </MainLayout>
    );
}
