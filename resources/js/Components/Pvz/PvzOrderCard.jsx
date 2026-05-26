import React, { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import ConfirmModal from '@/Components/ConfirmModal';

const ORDER_STATUS_MAP = {
    NEW: 'Новый заказ',
    INTRANSIT: 'В пути',
    DELIVERED: 'В пункте выдачи',
    ISSUED: 'Выдан',
    CANCELED: 'Отменён',
    REFUSED: 'Отказ от получения',
};

const PAYMENT_STATUS_MAP = {
    pending: 'Ожидает оплаты',
    paid: 'Оплачен',
    failed: 'Не оплачено',
    refunded: 'Возврат',
};

function formatPhone(p) {
    if (!p) return '';
    const d = p.replace(/\D/g, '');
    if (d.length < 7) return '+' + d;
    return `+7 ${d.slice(1, 4)} ${d.slice(4, 7)} ${d.slice(7, 9)} ${d.slice(9, 11)}`;
}

export default function PvzOrderCard({
    order,
    pickupPointId,
    mode = 'issue',
}) {
    const [expanded, setExpanded] = useState(false);
    const [issueModalOpen, setIssueModalOpen] = useState(false);
    const [refuseModalOpen, setRefuseModalOpen] = useState(false);
    const [refusalCodeSent, setRefusalCodeSent] = useState(false);
    const [refusalCode, setRefusalCode] = useState('');
    const [processing, setProcessing] = useState(false);
    const { errors = {} } = usePage().props;

    const isPreview = mode === 'preview';
    const isFinal = ['ISSUED', 'REFUSED', 'CANCELED'].includes(order.status);
    const isUnpaid = order.payment_status !== 'paid';
    const canIssue = Boolean(order.can_issue);
    const showIssueActions = !isPreview && order.can_manage;
    const atMyPickup = order.is_at_my_pickup ?? order.pickup_point_id === pickupPointId;

    const closeRefuseModal = () => {
        setRefuseModalOpen(false);
        setRefusalCodeSent(false);
        setRefusalCode('');
        setProcessing(false);
    };

    const sendRefusalCode = () => {
        setProcessing(true);
        router.post(`/pvz/orders/${order.id}/refusal-code`, {}, {
            preserveScroll: true,
            onSuccess: () => setRefusalCodeSent(true),
            onFinish: () => setProcessing(false),
        });
    };

    const confirmIssue = () => {
        setProcessing(true);
        router.post(`/pvz/orders/${order.id}/status`, { status: 'ISSUED' }, {
            preserveScroll: true,
            onSuccess: () => setIssueModalOpen(false),
            onFinish: () => setProcessing(false),
        });
    };

    const confirmRefusal = () => {
        setProcessing(true);
        router.post(`/pvz/orders/${order.id}/status`, {
            status: 'REFUSED',
            refusal_code: refusalCode.trim(),
        }, {
            preserveScroll: true,
            onSuccess: () => closeRefuseModal(),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <div className={`adm-order-row ${isUnpaid && !isPreview ? 'pvz-order-row--unpaid' : ''} ${!atMyPickup ? 'pvz-order-row--other-pvz' : ''} ${isFinal ? 'pvz-order-row--final' : ''} ${order.status === 'DELIVERED' && atMyPickup ? 'pvz-order-row--ready' : ''}`}>
                <div className="adm-order-header" onClick={() => setExpanded(!expanded)}>
                    <div className="adm-order-header-left">
                        <strong>#{order.number}</strong>
                        {atMyPickup ? (
                            <span className="pvz-pvz-badge pvz-pvz-badge--mine">Ваш ПВЗ</span>
                        ) : (
                            <span className="pvz-pvz-badge pvz-pvz-badge--other" title={order.pickup_point?.address}>
                                {order.pickup_point?.title || 'Другой ПВЗ'}
                            </span>
                        )}
                        <span className={`adm-order-status status-${order.status}`}>
                            {ORDER_STATUS_MAP[order.status] || order.status}
                        </span>
                        {order.payment_status && (
                            <span className={`adm-pay-status ${isUnpaid ? 'adm-pay-status--unpaid' : ''}`}>
                                {PAYMENT_STATUS_MAP[order.payment_status] || order.payment_status}
                            </span>
                        )}
                        {isPreview && order.status === 'DELIVERED' && (
                            <span className="pvz-preview-tag">Выдача в «Поиск / скан»</span>
                        )}
                        {isPreview && (order.status === 'INTRANSIT' || order.status === 'NEW') && (
                            <span className="pvz-preview-tag pvz-preview-tag--incoming">В пути</span>
                        )}
                    </div>
                    <div className="adm-order-header-right">
                        <span className="adm-order-total">{Number(order.total).toLocaleString('ru-RU')} ₽</span>
                        <span className="adm-accordion-arrow">{expanded ? '▲' : '▼'}</span>
                    </div>
                </div>

                {expanded && (
                    <div className="adm-order-details">
                        {!isPreview && isUnpaid && (
                            <div className="pvz-unpaid-banner" role="alert">
                                <strong>Заказ не оплачен</strong>
                                <p>Покупателю нужно оплатить заказ в личном кабинете. Выдача недоступна.</p>
                            </div>
                        )}

                        {!isPreview && order.issue_block_reason && !isUnpaid && (
                            <div className="pvz-hint-banner">
                                {order.issue_block_reason}
                            </div>
                        )}

                        {!order.can_manage && !isPreview && (
                            <div className={`pvz-hint-banner ${!atMyPickup ? 'pvz-hint-banner--blocked' : ''}`}>
                                {order.manage_hint}
                            </div>
                        )}

                        {order.buyer && (
                            <div className="adm-order-buyer-card">
                                <div className="adm-order-buyer-info">
                                    <div className="adm-order-buyer-name">
                                        {[order.buyer.name, order.buyer.last_name].filter(Boolean).join(' ') || 'Покупатель'}
                                    </div>
                                    <div className="adm-order-buyer-contacts">
                                        {order.buyer.email && <span>{order.buyer.email}</span>}
                                        {order.buyer.phone && <span>{formatPhone(order.buyer.phone)}</span>}
                                    </div>
                                </div>
                            </div>
                        )}

                        {!atMyPickup && order.pickup_point && (
                            <div className="adm-order-meta-grid">
                                <div>
                                    <span className="adm-label">Пункт выдачи</span>
                                    {order.pickup_point.title}
                                    {order.pickup_point.address && (
                                        <div className="pvz-other-pvz-address">{order.pickup_point.address}</div>
                                    )}
                                </div>
                            </div>
                        )}

                        {order.delivery_address && atMyPickup && (
                            <div className="adm-order-meta-grid">
                                <div>
                                    <span className="adm-label">Адрес</span>
                                    {order.delivery_address}
                                </div>
                            </div>
                        )}

                        <div className="adm-order-items-full">
                            {order.items?.map((item) => (
                                <div key={item.id} className="adm-order-item-full">
                                    {item.product_image && (
                                        <img src={item.product_image} className="adm-product-thumb" alt="" />
                                    )}
                                    <div className="adm-order-item-info">
                                        <span className="adm-order-item-name">{item.product_name}</span>
                                        <span className="adm-order-item-meta">
                                            {item.quantity} шт. × {Number(item.price_at_purchase).toLocaleString('ru-RU')} ₽
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {showIssueActions && (
                            <div className="pvz-actions-row">
                                <button
                                    type="button"
                                    className="adm-action-btn adm-btn-approve adm-btn-big"
                                    onClick={() => setIssueModalOpen(true)}
                                    disabled={!canIssue}
                                >
                                    ✓ Выдать заказ
                                </button>
                                <button
                                    type="button"
                                    className="adm-action-btn adm-btn-reject adm-btn-big"
                                    onClick={() => setRefuseModalOpen(true)}
                                >
                                    Отказ от получения
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </div>

            <ConfirmModal
                isOpen={issueModalOpen}
                onClose={() => !processing && setIssueModalOpen(false)}
                onConfirm={confirmIssue}
                title="Выдать заказ"
                message={`Подтвердите выдачу заказа №${order.number} покупателю. Заказ оплачен и готов к выдаче.`}
                confirmText="Выдать"
                cancelText="Отмена"
                processing={processing}
            />

            <ConfirmModal
                isOpen={refuseModalOpen}
                onClose={() => !processing && closeRefuseModal()}
                onConfirm={refusalCodeSent ? confirmRefusal : sendRefusalCode}
                title="Отказ от получения"
                message={
                    refusalCodeSent
                        ? 'Покупателю отправлен код в раздел «Уведомления». Введите код, который он вам назовёт.'
                        : 'Для безопасности код подтверждения будет отправлен покупателю в уведомления. Попросите покупателя назвать код из уведомления.'
                }
                confirmText={refusalCodeSent ? 'Оформить отказ' : 'Отправить код покупателю'}
                cancelText="Отмена"
                variant="danger"
                processing={processing}
                confirmDisabled={refusalCodeSent && refusalCode.replace(/\D/g, '').length < 4}
            >
                {refusalCodeSent && (
                    <label className="pvz-refusal-code-field">
                        <span className="adm-label">Код из уведомлений покупателя</span>
                        <input
                            type="text"
                            className="phone-modal-input"
                            inputMode="numeric"
                            autoComplete="off"
                            placeholder="6 цифр"
                            maxLength={8}
                            value={refusalCode}
                            onChange={(e) => setRefusalCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                        />
                        {(errors.refusal_code || errors.status) && (
                            <p className="pvz-modal-error">{errors.refusal_code || errors.status}</p>
                        )}
                    </label>
                )}
            </ConfirmModal>
        </>
    );
}
