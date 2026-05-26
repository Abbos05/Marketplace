import React, { useEffect, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/admin/dashboard.css';

const inertiaOpts = { preserveScroll: true, preserveState: false };

export default function AdminPickupPoints({ auth, pickupPoints = [], regions = [], assignableUsers = [] }) {
    const assignForm = useForm({ user_id: '', pickup_point_id: '' });
    const createForm = useForm({
        title: '',
        address: '',
        region_id: regions[0]?.id ?? '',
        sort_order: 0,
    });

    const submitCreate = (e) => {
        e.preventDefault();
        createForm.post(route('admin.pickup-points.store'), {
            ...inertiaOpts,
            onSuccess: () => createForm.reset('title', 'address', 'sort_order'),
        });
    };

    const assignOperator = (pickupPointId) => {
        if (!assignForm.data.user_id) {
            alert('Выберите пользователя');
            return;
        }
        router.post(route('admin.pickup-points.assign-operator', pickupPointId), {
            user_id: assignForm.data.user_id,
        }, { ...inertiaOpts, onSuccess: () => assignForm.reset() });
    };

    const deactivate = (id) => {
        if (!confirm('Отключить этот пункт выдачи?')) return;
        router.delete(route('admin.pickup-points.destroy', id), inertiaOpts);
    };

    const approveClosure = (id) => {
        if (!confirm('Подтвердить закрытие пункта? Новые заказы на него приниматься не будут, оператор будет снят.')) return;
        router.post(route('admin.pickup-points.approve-closure', id), {}, inertiaOpts);
    };

    const rejectClosure = (id) => {
        const reason = prompt('Причина отклонения закрытия (обязательно, от 5 символов). Оператор увидит её в уведомлениях:');
        if (reason === null) return;
        const trimmed = reason.trim();
        if (trimmed.length < 5) {
            alert('Укажите причину не короче 5 символов.');
            return;
        }
        router.post(route('admin.pickup-points.reject-closure', id), { reject_reason: trimmed }, inertiaOpts);
    };

    const closureLabel = (p) => {
        if (p.closure_status === 'pending') return 'Ожидает подтверждения';
        if (p.closure_status === 'closed') return 'Закрыт окончательно';
        return '—';
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
            ...inertiaOpts,
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
                                <th>Оператор</th>
                                <th>Закрытие</th>
                                <th>Активен</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {pickupPoints.map((p) =>
                                editingId === p.id ? (
                                    <tr key={p.id}>
                                        <td colSpan={8}>
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
                                                    активен (включение сбрасывает статус «закрыт» и ожидание закрытия)
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
                                        <td>
                                            {p.operator ? (
                                                <span>{p.operator.name}<br /><small>{p.operator.email}</small></span>
                                            ) : (
                                                <span style={{ color: '#92400e', fontSize: 12 }}>Без оператора — недоступен в заказах</span>
                                            )}
                                            {!p.operator && p.closure_status !== 'pending' ? (
                                                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', alignItems: 'center', marginTop: 6 }}>
                                                    <select
                                                        className="admin-search-input"
                                                        style={{ minWidth: 140 }}
                                                        value={assignForm.data.pickup_point_id === p.id ? assignForm.data.user_id : ''}
                                                        onChange={(e) => {
                                                            assignForm.setData('user_id', e.target.value);
                                                            assignForm.setData('pickup_point_id', p.id);
                                                        }}
                                                    >
                                                        <option value="">Назначить…</option>
                                                        {assignableUsers.map((u) => (
                                                            <option key={u.id} value={u.id}>
                                                                {[u.name, u.last_name].filter(Boolean).join(' ') || u.email}
                                                            </option>
                                                        ))}
                                                    </select>
                                                    <button
                                                        type="button"
                                                        className="adm-action-btn adm-btn-view"
                                                        onClick={() => assignOperator(p.id)}
                                                    >
                                                        OK
                                                    </button>
                                                </div>
                                            ) : null}
                                        </td>
                                        <td>
                                            {p.closure_status === 'pending' && p.operator ? (
                                                <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                                                    <span style={{ fontSize: 12, color: '#92400e' }}>Запрос закрытия</span>
                                                    {p.closure_reason && <small>{p.closure_reason}</small>}
                                                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                                        <button type="button" className="adm-action-btn adm-btn-approve" onClick={() => approveClosure(p.id)}>
                                                            Подтвердить закрытие
                                                        </button>
                                                        <button type="button" className="adm-action-btn adm-btn-reject" onClick={() => rejectClosure(p.id)}>
                                                            Отклонить
                                                        </button>
                                                    </div>
                                                </div>
                                            ) : (
                                                <span style={{ fontSize: 12 }}>{closureLabel(p)}</span>
                                            )}
                                        </td>
                                        <td>{p.is_active ? 'да' : 'нет'}</td>
                                        <td>
                                            <button type="button" className="adm-action-btn adm-btn-view" onClick={() => startEdit(p)}>
                                                Изменить
                                            </button>{' '}
                                            {p.is_active && p.closure_status !== 'closed' && p.closure_status !== 'pending' ? (
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
