import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';
import '../../../../css/seller/promocodes.css';

function expiryClass(raw) {
    if (!raw) return 'promo-date--none';
    const diff = (new Date(raw) - new Date()) / (1000 * 60 * 60 * 24);
    if (diff < 0)  return 'promo-date--expired';
    if (diff <= 7) return 'promo-date--soon';
    return 'promo-date--ok';
}

const INITIAL_FORM = {
    code:             '',
    discount_value:   '',
    expires_at:       '',
    usage_limit:      '',
    min_order_amount: '',
};

export default function Index({ promocodes }) {
    const { props } = usePage();
    const flash  = props.flash ?? {};
    const errors = props.errors ?? {};

    const [form, setForm]         = useState(INITIAL_FORM);
    const [submitting, setSubmitting] = useState(false);

    const handleChange = (e) => {
        const { name, value } = e.target;
        setForm(prev => ({ ...prev, [name]: name === 'code' ? value.toUpperCase() : value }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        router.post(route('seller.promocodes.store'), form, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => setForm(INITIAL_FORM),
        });
    };

    const handleToggle = (promo) => {
        router.post(route('seller.promocodes.toggle', promo.id), {}, { preserveScroll: true });
    };

    const handleDelete = (promo) => {
        if (!confirm(`Удалить промокод «${promo.code}»?`)) return;
        router.delete(route('seller.promocodes.destroy', promo.id), { preserveScroll: true });
    };

    const getStatusInfo = (promo) => {
        if (promo.is_expired) return { cls: 'promo-status--expired', label: 'Истёк' };
        if (!promo.is_active) return { cls: 'promo-status--inactive', label: 'Выключен' };
        return { cls: 'promo-status--active', label: 'Активен' };
    };

    return (
        <SellerLayout title="Промокоды">
            <Head title="Промокоды" />


            <div className="promo-layout">
                {/* Left: table */}
                <div className="promo-card">
                    <div className="promo-card__header">
                        <span className="promo-card__title">Мои промокоды</span>
                        <span className="promo-card__count">{promocodes.length} шт.</span>
                    </div>

                    {promocodes.length === 0 ? (
                        <div className="promo-empty">
                            <div className="promo-empty__icon">🏷️</div>
                            <div className="promo-empty__title">Промокодов пока нет</div>
                            <div className="promo-empty__sub">Создайте первый промокод с помощью формы справа</div>
                        </div>
                    ) : (
                        <table className="promo-table">
                            <thead>
                                <tr>
                                    <th>Код</th>
                                    <th>Скидка</th>
                                    <th>Мин. сумма</th>
                                    <th>Срок действия</th>
                                    <th>Использований</th>
                                    <th>Статус</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {promocodes.map((promo) => {
                                    const status  = getStatusInfo(promo);
                                    const dateCls = expiryClass(promo.expires_at_raw);
                                    const canToggle = !promo.is_expired;

                                    return (
                                        <tr key={promo.id}>
                                            <td>
                                                <span className="promo-code-cell">{promo.code}</span>
                                            </td>
                                            <td>
                                                <span className="promo-discount-badge">
                                                    -{promo.discount_value}%
                                                </span>
                                            </td>
                                            <td>
                                                {promo.min_order_amount
                                                    ? `от ${Number(promo.min_order_amount).toLocaleString('ru-RU')} ₽`
                                                    : <span style={{ color: '#94a3b8' }}>—</span>
                                                }
                                            </td>
                                            <td>
                                                <span className={dateCls}>
                                                    {promo.expires_at ?? '∞ Бессрочно'}
                                                </span>
                                            </td>
                                            <td>
                                                <span className="promo-usage">
                                                    <span className="promo-usage__used">{promo.usages_count}</span>
                                                    <span className="promo-usage__limit">
                                                        {promo.usage_limit ? ` / ${promo.usage_limit}` : ''}
                                                    </span>
                                                </span>
                                            </td>
                                            <td>
                                                <span className={`promo-status ${status.cls}`}>
                                                    {status.label}
                                                </span>
                                            </td>
                                            <td>
                                                <div className="promo-actions">
                                                    {canToggle && (
                                                        <button
                                                            type="button"
                                                            className={`promo-btn ${promo.is_active ? 'promo-btn--toggle-on' : 'promo-btn--toggle-off'}`}
                                                            onClick={() => handleToggle(promo)}
                                                        >
                                                            {promo.is_active ? 'Выкл.' : 'Вкл.'}
                                                        </button>
                                                    )}
                                                    {promo.usages_count === 0 && (
                                                        <button
                                                            type="button"
                                                            className="promo-btn promo-btn--delete"
                                                            onClick={() => handleDelete(promo)}
                                                        >
                                                            Удалить
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}
                </div>

                {/* Right: create form */}
                <div className="promo-card">
                    <div className="promo-card__header">
                        <span className="promo-card__title">Создать промокод</span>
                    </div>
                    <div className="promo-card__body">
                        <form onSubmit={handleSubmit} className="promo-form">

                            <div className="promo-form__row">
                                <label className="promo-form__label">Код промокода *</label>
                                <input
                                    type="text"
                                    name="code"
                                    className={`promo-form__input ${errors.code ? 'error' : ''}`}
                                    placeholder="SUMMER2026"
                                    value={form.code}
                                    onChange={handleChange}
                                    maxLength={30}
                                    required
                                />
                                <span className="promo-form__hint">Только буквы, цифры, _ и -</span>
                                {errors.code && <span className="promo-form__error">{errors.code}</span>}
                            </div>

                            <div className="promo-form__row">
                                <label className="promo-form__label">Скидка, % *</label>
                                <input
                                    type="number"
                                    name="discount_value"
                                    className={`promo-form__input ${errors.discount_value ? 'error' : ''}`}
                                    placeholder="10"
                                    min={1}
                                    max={100}
                                    maxLength={3}
                                    value={form.discount_value}
                                    onChange={handleChange}
                                    required
                                />
                                {errors.discount_value && <span className="promo-form__error">{errors.discount_value}</span>}
                            </div>

                            <div className="promo-form__row">
                                <label className="promo-form__label">Дата окончания</label>
                                <input
                                    type="date"
                                    name="expires_at"
                                    className={`promo-form__input ${errors.expires_at ? 'error' : ''}`}
                                    value={form.expires_at}
                                    onChange={handleChange}
                                    min={new Date().toISOString().split('T')[0]}
                                />
                                <span className="promo-form__hint">Оставьте пустым для бессрочного</span>
                                {errors.expires_at && <span className="promo-form__error">{errors.expires_at}</span>}
                            </div>

                            <div className="promo-form__row">
                                <label className="promo-form__label">Лимит использований</label>
                                <input
                                    type="number"
                                    name="usage_limit"
                                    className={`promo-form__input ${errors.usage_limit ? 'error' : ''}`}
                                    placeholder="100"
                                    min={1}
                                    max={1000000}
                                    value={form.usage_limit}
                                    onChange={handleChange}
                                />
                                <span className="promo-form__hint">Сколько раз можно применить всего (пусто = ∞)</span>
                                {errors.usage_limit && <span className="promo-form__error">{errors.usage_limit}</span>}
                            </div>

                            <div className="promo-form__row">
                                <label className="promo-form__label">Мин. сумма заказа, ₽</label>
                                <input
                                    type="number"
                                    name="min_order_amount"
                                    className={`promo-form__input ${errors.min_order_amount ? 'error' : ''}`}
                                    placeholder="500"
                                    min={0}
                                    value={form.min_order_amount}
                                    onChange={handleChange}
                                />
                                <span className="promo-form__hint">Минимальная сумма товаров для применения (пусто = любая)</span>
                                {errors.min_order_amount && <span className="promo-form__error">{errors.min_order_amount}</span>}
                            </div>

                            <div style={{ padding: '12px', background: '#f8fafc', borderRadius: '10px', fontSize: '13px', color: '#64748b', lineHeight: 1.5 }}>
                                Каждый покупатель может использовать промокод только <strong>1 раз</strong>.
                                Скидка применяется на ваши товары в корзине покупателя.
                            </div>

                            <button
                                type="submit"
                                className="promo-form__submit"
                                disabled={submitting}
                            >
                                {submitting ? 'Создаём...' : 'Создать промокод'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </SellerLayout>
    );
}
