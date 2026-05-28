import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/admin/dashboard.css';

const INITIAL = {
    badge_label: '',
    ends_at: '',
    product_ids: [],
};

export default function Promotions({ promotions = [], products = [] }) {
    const [form, setForm] = useState(INITIAL);

    const toggleProduct = (id) => {
        setForm((prev) => ({
            ...prev,
            product_ids: prev.product_ids.includes(id)
                ? prev.product_ids.filter((x) => x !== id)
                : [...prev.product_ids, id],
        }));
    };

    return (
        <MainLayout>
            <Head title="Акции" />
            <div className="adm-page">
                <h1>Маркетинговые акции</h1>
                <p className="adm-muted">
                    Текст на карточке товара в каталоге. Отдельно от скидки по цене и промокодов.
                </p>

                <table className="adm-table adm-mb">
                    <thead>
                        <tr>
                            <th>Текст на карточке</th>
                            <th>Товаров</th>
                            <th>До</th>
                            <th>Статус</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {promotions.map((p) => (
                            <tr key={p.id}>
                                <td>{p.badge_label}</td>
                                <td>{p.products_count}</td>
                                <td>{p.ends_at ?? '—'}</td>
                                <td>{p.is_active_now ? 'Активна' : p.status}</td>
                                <td>
                                    <button
                                        type="button"
                                        className="adm-action-btn"
                                        onClick={() =>
                                            router.post(route('admin.promotions.toggle', p.id), {}, {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        Переключить
                                    </button>
                                    <button
                                        type="button"
                                        className="adm-action-btn adm-btn-reject"
                                        onClick={() => {
                                            if (confirm('Удалить?')) {
                                                router.delete(route('admin.promotions.destroy', p.id), {
                                                    preserveScroll: true,
                                                });
                                            }
                                        }}
                                    >
                                        Удалить
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <form
                    className="adm-form-block"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.post(route('admin.promotions.store'), form, {
                            preserveScroll: true,
                            onSuccess: () => setForm(INITIAL),
                        });
                    }}
                >
                    <h2>Новая акция</h2>
                    <input
                        className="admin-search-input adm-mb"
                        placeholder="Текст на карточке (например: Хит)"
                        value={form.badge_label}
                        onChange={(e) => setForm({ ...form, badge_label: e.target.value })}
                        required
                    />
                    <label className="adm-muted adm-mb" style={{ display: 'block' }}>
                        Действует до (необязательно)
                    </label>
                    <input
                        type="datetime-local"
                        className="admin-search-input adm-mb"
                        value={form.ends_at}
                        onChange={(e) => setForm({ ...form, ends_at: e.target.value })}
                    />
                    <div className="adm-products-pick adm-mb" style={{ maxHeight: 200, overflow: 'auto' }}>
                        {products.map((pr) => (
                            <label key={pr.id} style={{ display: 'block' }}>
                                <input
                                    type="checkbox"
                                    checked={form.product_ids.includes(pr.id)}
                                    onChange={() => toggleProduct(pr.id)}
                                />
                                {pr.title}
                            </label>
                        ))}
                    </div>
                    <button type="submit" className="adm-action-btn adm-btn-approve">
                        Создать
                    </button>
                </form>
            </div>
        </MainLayout>
    );
}
