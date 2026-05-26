import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';
import '../../../../css/seller/promocodes.css';

const INITIAL = {
    title: '',
    badge_label: '',
    description: '',
    starts_at: '',
    ends_at: '',
    product_ids: [],
    is_featured: false,
};

export default function Index({ promotions = [], products = [] }) {
    const [form, setForm] = useState(INITIAL);
    const [submitting, setSubmitting] = useState(false);

    const toggleProduct = (id) => {
        setForm((prev) => {
            const ids = prev.product_ids.includes(id)
                ? prev.product_ids.filter((x) => x !== id)
                : [...prev.product_ids, id];
            return { ...prev, product_ids: ids };
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        router.post(route('seller.promotions.store'), form, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => setForm(INITIAL),
        });
    };

    return (
        <SellerLayout title="Акции">
            <Head title="Акции" />
            <div className="promo-layout">
                <div className="promo-card">
                    <div className="promo-card__header">
                        <span className="promo-card__title">Мои акции</span>
                        <span className="promo-card__count">{promotions.length} шт.</span>
                    </div>
                    {promotions.length === 0 ? (
                        <div className="promo-empty">
                            <div className="promo-empty__title">Акций пока нет</div>
                            <div className="promo-empty__sub">Создайте акцию и привяжите товары — они появятся с бейджем в каталоге</div>
                        </div>
                    ) : (
                        <table className="promo-table">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Бейдж</th>
                                    <th>Товаров</th>
                                    <th>Период</th>
                                    <th>Статус</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {promotions.map((p) => (
                                    <tr key={p.id}>
                                        <td>{p.title}</td>
                                        <td><span className="promo-discount-badge">{p.badge_label}</span></td>
                                        <td>{p.products_count}</td>
                                        <td>
                                            {p.starts_at ?? '—'} — {p.ends_at ?? '∞'}
                                        </td>
                                        <td>
                                            <span className={p.is_active_now ? 'promo-status--active' : 'promo-status--inactive'}>
                                                {p.is_active_now ? 'Активна' : 'Неактивна'}
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" className="promo-btn promo-btn--sm" onClick={() => router.post(route('seller.promotions.toggle', p.id), {}, { preserveScroll: true })}>
                                                {p.status === 'active' ? 'Пауза' : 'Включить'}
                                            </button>
                                            <button type="button" className="promo-btn promo-btn--sm promo-btn--danger" onClick={() => { if (confirm('Удалить акцию?')) router.delete(route('seller.promotions.destroy', p.id), { preserveScroll: true }); }}>
                                                Удалить
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                <form className="promo-card promo-form" onSubmit={handleSubmit}>
                    <div className="promo-card__header">
                        <span className="promo-card__title">Новая акция</span>
                    </div>
                    <label className="promo-form__label">Название *</label>
                    <input className="promo-form__input" name="title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
                    <label className="promo-form__label">Текст на карточке (бейдж) *</label>
                    <input className="promo-form__input" name="badge_label" value={form.badge_label} onChange={(e) => setForm({ ...form, badge_label: e.target.value })} placeholder="Например: Распродажа" required />
                    <label className="promo-form__label">Описание</label>
                    <textarea className="promo-form__input" rows={3} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
                    <label className="promo-form__label">Начало</label>
                    <input type="datetime-local" className="promo-form__input" value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })} />
                    <label className="promo-form__label">Окончание</label>
                    <input type="datetime-local" className="promo-form__input" value={form.ends_at} onChange={(e) => setForm({ ...form, ends_at: e.target.value })} />
                    <label className="promo-form__label promo-form__checkbox">
                        <input type="checkbox" checked={form.is_featured} onChange={(e) => setForm({ ...form, is_featured: e.target.checked })} />
                        Показывать в блоке «Акции» на главной
                    </label>
                    <label className="promo-form__label">Товары *</label>
                    <div className="promo-products-pick">
                        {products.map((pr) => (
                            <label key={pr.id} className="promo-form__checkbox">
                                <input type="checkbox" checked={form.product_ids.includes(pr.id)} onChange={() => toggleProduct(pr.id)} />
                                {pr.title}
                            </label>
                        ))}
                    </div>
                    <button type="submit" className="promo-btn promo-btn--primary" disabled={submitting}>
                        Создать акцию
                    </button>
                </form>
            </div>
        </SellerLayout>
    );
}
