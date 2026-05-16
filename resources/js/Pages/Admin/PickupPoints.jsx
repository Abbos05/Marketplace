import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/admin/dashboard.css';

export default function AdminPickupPoints({ auth, pickupPoints = [], regions = [] }) {
    const createForm = useForm({
        title: '',
        address: '',
        region_id: regions[0]?.id ?? '',
        sort_order: 0,
    });

    const submitCreate = (e) => {
        e.preventDefault();
        createForm.post(route('admin.pickup-points.store'), {
            preserveScroll: true,
            onSuccess: () => createForm.reset('title', 'address', 'sort_order'),
        });
    };

    const deactivate = (id) => {
        if (!confirm('Отключить этот пункт выдачи?')) return;
        router.delete(route('admin.pickup-points.destroy', id), { preserveScroll: true });
    };

    const [editingId, setEditingId] = useState(null);
    const editForm = useForm({
        title: '',
        address: '',
        region_id: '',
        sort_order: 0,
        is_active: true,
    });

    const startEdit = (p) => {
        setEditingId(p.id);
        editForm.setData({
            title: p.title,
            address: p.address,
            region_id: p.region_id ?? '',
            sort_order: p.sort_order ?? 0,
            is_active: p.is_active,
        });
    };

    const saveEdit = (id) => {
        editForm.patch(route('admin.pickup-points.update', id), {
            preserveScroll: true,
            onSuccess: () => setEditingId(null),
        });
    };

    return (
        <MainLayout auth={auth}>
            <Head title="Пункты выдачи · Админ" />

            <div className="adm-detail-page">
                <div className="adm-detail-nav">
                    <a href="/admin/dashboard" className="adm-back-link">← Панель администратора</a>
                </div>

                <h1 className="adm-title">Пункты выдачи</h1>

                <div className="adm-detail-card" style={{ marginBottom: 24, padding: 20 }}>
                    <h2 className="adm-stat-label" style={{ marginTop: 0, marginBottom: 16, fontSize: 16, fontWeight: 700 }}>
                        Новый пункт
                    </h2>
                    <form onSubmit={submitCreate} style={{ display: 'grid', gap: 12, maxWidth: 720 }}>
                        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                            Название
                            <input
                                type="text"
                                value={createForm.data.title}
                                onChange={(e) => createForm.setData('title', e.target.value)}
                                className="admin-search-input"
                                required
                            />
                        </label>
                        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                            Адрес
                            <input
                                type="text"
                                value={createForm.data.address}
                                onChange={(e) => createForm.setData('address', e.target.value)}
                                className="admin-search-input"
                                required
                            />
                        </label>
                        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                            Регион (город)
                            <select
                                value={createForm.data.region_id}
                                onChange={(e) => createForm.setData('region_id', e.target.value ? Number(e.target.value) : '')}
                                className="admin-search-input"
                            >
                                <option value="">— не указан —</option>
                                {regions.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.name} ({r.delivery_hours} ч)
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                            Порядок
                            <input
                                type="number"
                                min={0}
                                value={createForm.data.sort_order}
                                onChange={(e) => createForm.setData('sort_order', Number(e.target.value))}
                                className="admin-search-input"
                            />
                        </label>
                        <div style={{ alignSelf: 'end' }}>
                            <button type="submit" className="adm-action-btn adm-btn-view" disabled={createForm.processing}>
                                Добавить
                            </button>
                        </div>
                    </form>
                </div>

                <div className="adm-table-wrap">
                    <table className="adm-table">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Адрес</th>
                                <th>Регион</th>
                                <th>Порядок</th>
                                <th>Активен</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {pickupPoints.map((p) =>
                                editingId === p.id ? (
                                    <tr key={p.id}>
                                        <td colSpan={6}>
                                            <div style={{ display: 'grid', gap: 12, padding: 12 }}>
                                                <input
                                                    className="admin-search-input"
                                                    value={editForm.data.title}
                                                    onChange={(e) => editForm.setData('title', e.target.value)}
                                                />
                                                <input
                                                    className="admin-search-input"
                                                    value={editForm.data.address}
                                                    onChange={(e) => editForm.setData('address', e.target.value)}
                                                />
                                                <select
                                                    className="admin-search-input"
                                                    value={editForm.data.region_id}
                                                    onChange={(e) =>
                                                        editForm.setData('region_id', e.target.value ? Number(e.target.value) : '')
                                                    }
                                                >
                                                    <option value="">—</option>
                                                    {regions.map((r) => (
                                                        <option key={r.id} value={r.id}>
                                                            {r.name}
                                                        </option>
                                                    ))}
                                                </select>
                                                <input
                                                    type="number"
                                                    className="admin-search-input"
                                                    value={editForm.data.sort_order}
                                                    onChange={(e) => editForm.setData('sort_order', Number(e.target.value))}
                                                />
                                                <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                    <input
                                                        type="checkbox"
                                                        checked={editForm.data.is_active}
                                                        onChange={(e) => editForm.setData('is_active', e.target.checked)}
                                                    />
                                                    активен
                                                </label>
                                                <div style={{ display: 'flex', gap: 8 }}>
                                                    <button type="button" className="adm-action-btn adm-btn-view" onClick={() => saveEdit(p.id)}>
                                                        Сохранить
                                                    </button>
                                                    <button type="button" className="adm-action-btn" onClick={() => setEditingId(null)}>
                                                        Отмена
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    <tr key={p.id} style={!p.is_active ? { opacity: 0.55 } : undefined}>
                                        <td>{p.title}</td>
                                        <td>{p.address}</td>
                                        <td>{p.region_name ?? '—'}</td>
                                        <td>{p.sort_order}</td>
                                        <td>{p.is_active ? 'да' : 'нет'}</td>
                                        <td>
                                            <button type="button" className="adm-action-btn adm-btn-view" onClick={() => startEdit(p)}>
                                                Изменить
                                            </button>{' '}
                                            {p.is_active ? (
                                                <button type="button" className="adm-action-btn" onClick={() => deactivate(p.id)}>
                                                    Отключить
                                                </button>
                                            ) : null}
                                        </td>
                                    </tr>
                                )
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </MainLayout>
    );
}
