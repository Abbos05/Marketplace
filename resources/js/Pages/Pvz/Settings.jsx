import React from 'react';
import { useForm } from '@inertiajs/react';
import PvzLayout from '@/Layouts/PvzLayout';
import '../../../css/pvz/dashboard.css';
import '../../../css/admin/dashboard.css';

export default function Settings({
    pickupPoint = {},
    canRequestClosure = false,
    closureBlockMessage = '',
}) {
    const { data, setData, post, processing } = useForm({
        closure_reason: '',
    });

    const isPending = pickupPoint.closure_status === 'pending';

    const submitClosure = (e) => {
        e.preventDefault();
        if (!confirm('Отправить запрос на закрытие пункта выдачи? Администратор должен подтвердить.')) return;
        post('/pvz/settings/closure', { preserveScroll: true });
    };

    return (
        <PvzLayout title="Настройки ПВЗ" pickupPoint={pickupPoint}>
            <p className="pvz-page-lead">Управление пунктом выдачи</p>


            <div className="pvz-card">
                <h2 className="pvz-page-title" style={{ fontSize: '1.1rem' }}>Закрытие пункта выдачи</h2>
                <p className="pvz-page-lead" style={{ marginBottom: 16 }}>
                    После подтверждения администратором пункт исчезнет из списка при оформлении заказов.
                    Закрыть можно только когда нет заказов в пути и ожидающих выдачу.
                </p>

                {pickupPoint.closure_admin_reject_reason && !isPending && (
                    <div className="pvz-hint-banner pvz-hint-banner--blocked" style={{ marginBottom: 12 }}>
                        Администратор отклонил предыдущий запрос на закрытие
                        {pickupPoint.closure_admin_rejected_at && (
                            <> ({new Date(pickupPoint.closure_admin_rejected_at).toLocaleString('ru-RU')})</>
                        )}
                        : {pickupPoint.closure_admin_reject_reason}. Пункт продолжает работу — можете подать новый запрос.
                    </div>
                )}

                {isPending ? (
                    <div className="pvz-hint-banner">
                        Запрос отправлен {pickupPoint.closure_requested_at
                            ? new Date(pickupPoint.closure_requested_at).toLocaleString('ru-RU')
                            : ''}.
                        Ожидайте решения администратора. До этого выдача текущих заказов доступна.
                    </div>
                ) : (
                    <>
                        {!canRequestClosure && closureBlockMessage && (
                            <div className="pvz-hint-banner pvz-hint-banner--blocked">{closureBlockMessage}</div>
                        )}
                        <form onSubmit={submitClosure}>
                            <label style={{ display: 'block', marginBottom: 12 }}>
                                <span style={{ display: 'block', marginBottom: 6, fontWeight: 600 }}>Причина (необязательно)</span>
                                <textarea
                                    rows={4}
                                    value={data.closure_reason}
                                    onChange={(e) => setData('closure_reason', e.target.value)}
                                    style={{ width: '100%', maxWidth: 480, padding: 10, borderRadius: 10, border: '1px solid #ccc' }}
                                    disabled={!canRequestClosure || processing}
                                />
                            </label>
                            <button
                                type="submit"
                                className="pvz-btn pvz-btn--outline"
                                disabled={!canRequestClosure || processing}
                            >
                                {processing ? 'Отправка…' : 'Запросить закрытие пункта'}
                            </button>
                        </form>
                    </>
                )}
            </div>

            <div className="pvz-card">
                <h2 className="pvz-page-title" style={{ fontSize: '1.1rem' }}>Роль на платформе</h2>
                <p className="pvz-page-lead">
                    Сейчас у вас роль оператора ПВЗ. Раздел «Мои компании» (продавец) в профиле недоступен —
                    на платформе одна основная роль на аккаунт. Чтобы стать продавцом, завершите работу пункта
                    и используйте другой аккаунт для заявки продавца.
                </p>
            </div>
        </PvzLayout>
    );
}
