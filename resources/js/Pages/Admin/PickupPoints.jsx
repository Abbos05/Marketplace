import React, { useState, useMemo, useEffect } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/admin/dashboard.css';

const inertiaOpts = { preserveScroll: true, preserveState: false };

export default function AdminPickupPoints({ auth, pickupPoints = [], regions = [] }) {
    const [searchQuery, setSearchQuery] = useState('');
    const [filterStatus, setFilterStatus] = useState('all'); // 'all', 'active', 'inactive'
    const [sortColumn, setSortColumn] = useState('sort_order');
    const [sortDirection, setSortDirection] = useState('asc');
    const [editingId, setEditingId] = useState(null);
    let count = pickupPoints.length < 200 ? pickupPoints.length : 200 ;

    // Сброс редактирования при изменении фильтров
    useEffect(() => {
        setEditingId(null);
    }, [searchQuery, filterStatus, sortColumn, sortDirection]);

    // Фильтрация
    const filteredPoints = useMemo(() => {
        let result = [...pickupPoints];

        // Фильтр по активности
        if (filterStatus === 'active') {
            result = result.filter(p => p.is_active === true);
        } else if (filterStatus === 'inactive') {
            result = result.filter(p => p.is_active === false);
        }

        // Поиск
        if (searchQuery.trim()) {
            const query = searchQuery.trim().toLowerCase();
            result = result.filter(p => {
                count = 50;
                const titleMatch = p.title?.toLowerCase().includes(query);
                const addressMatch = p.address?.toLowerCase().includes(query);
                const operatorNameMatch = p.operator?.name?.toLowerCase().includes(query);
                const operatorEmailMatch = p.operator?.email?.toLowerCase().includes(query);
                const operatorPhoneMatch = p.operator?.phone?.toLowerCase().includes(query);
                return titleMatch || addressMatch || operatorNameMatch || operatorEmailMatch || operatorPhoneMatch;
            });
        }

        return result;
    }, [pickupPoints, searchQuery, filterStatus]);

    // Сортировка
    const sortedPoints = useMemo(() => {
        const sorted = [...filteredPoints];
        const direction = sortDirection === 'asc' ? 1 : -1;

        sorted.sort((a, b) => {
            let valA, valB;

            switch (sortColumn) {
                case 'title':
                    valA = a.title || '';
                    valB = b.title || '';
                    break;
                case 'address':
                    valA = a.address || '';
                    valB = b.address || '';
                    break;
                case 'region_name':
                    valA = a.region_name || '';
                    valB = b.region_name || '';
                    break;
                case 'sort_order':
                    valA = a.sort_order ?? 0;
                    valB = b.sort_order ?? 0;
                    return (valA - valB) * direction;
                case 'operator_name':
                    valA = a.operator?.name || '';
                    valB = b.operator?.name || '';
                    break;
                case 'is_active':
                    valA = a.is_active ? 1 : 0;
                    valB = b.is_active ? 1 : 0;
                    return (valA - valB) * direction;
                default:
                    return 0;
            }

            if (typeof valA === 'string') {
                return valA.localeCompare(valB) * direction;
            }
            return (valA - valB) * direction;
        });

        return sorted;
    }, [filteredPoints, sortColumn, sortDirection]);

    const handleSort = (column) => {
        if (sortColumn === column) {
            setSortDirection(prev => prev === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('asc');
        }
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

    const SortIcon = ({ column }) => {
        if (sortColumn !== column) return <span style={{ opacity: 0.3 }}>↕️</span>;
        return sortDirection === 'asc' ? <span>↑</span> : <span>↓</span>;
    };
    return (
        <MainLayout auth={auth}>
            <Head title="Пункты выдачи · Админ" />

            <div className="adm-detail-page">
                <div className="adm-detail-nav">
                    <a href="/admin/dashboard" className="adm-back-link">← Панель администратора</a>
                </div>

                <h1 className="adm-title">Пункты выдачи</h1>

                {/* Панель фильтров */}
                <div style={{ display: 'flex', gap: 16, marginBottom: 24, flexWrap: 'wrap', alignItems: 'center' }}>
                    <input
                        type="text"
                        placeholder="Поиск по названию, адресу, оператору (имя, email, телефон)..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="admin-search-input"
                        style={{ flex: '1 1 300px', minWidth: 200 }}
                    />
                    <select
                        value={filterStatus}
                        onChange={(e) => setFilterStatus(e.target.value)}
                        className="admin-search-input"
                        style={{ width: 'auto' }}
                    >
                        <option value="all">Все пункты</option>
                        <option value="active">Только активные</option>
                        <option value="inactive">Только неактивные</option>
                    </select>
                    <button
                        type="button"
                        className="adm-action-btn"
                        onClick={() => {
                            setSearchQuery('');
                            setFilterStatus('all');
                            setSortColumn('sort_order');
                            setSortDirection('asc');
                        }}
                    >
                        Сбросить фильтры
                    </button>
                    <span style={{ fontSize: 14, color: '#4b5563' }}>
                        Найдено: {sortedPoints.length} из {pickupPoints.length}
                    </span>
                </div>

                <div className="adm-table-wrap">
                    <table className="adm-table">
                        <thead>
                            <tr>
                                <th onClick={() => handleSort('title')} style={{ cursor: 'pointer' }}>
                                    Название <SortIcon column="title" />
                                </th>
                                <th onClick={() => handleSort('address')} style={{ cursor: 'pointer' }}>
                                    Адрес <SortIcon column="address" />
                                </th>
                                <th onClick={() => handleSort('region_name')} style={{ cursor: 'pointer' }}>
                                    Регион <SortIcon column="region_name" />
                                </th>
                                <th onClick={() => handleSort('sort_order')} style={{ cursor: 'pointer' }}>
                                    Порядок <SortIcon column="sort_order" />
                                </th>
                                <th onClick={() => handleSort('operator_name')} style={{ cursor: 'pointer' }}>
                                    Оператор <SortIcon column="operator_name" />
                                </th>
                                <th>Закрытие</th>
                                <th onClick={() => handleSort('is_active')} style={{ cursor: 'pointer' }}>
                                    Активен <SortIcon column="is_active" />
                                </th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {sortedPoints.slice(0, count).map((p) =>
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
                                                        disabled={p.closure_status === 'closed'}
                                                    />
                                                    активен (включение сбрасывает статус «закрыт» и ожидание закрытия)
                                                    {p.closure_status === 'closed' && (
                                                        <span style={{ color: '#b91c1c', marginLeft: 8, fontSize: 12 }}>
                                                            ⚠️ Пункт окончательно закрыт, активация невозможна
                                                        </span>
                                                    )}
                                                </label>
                                                <div style={{ display: 'flex', gap: 8 }}>
                                                    <button
                                                        type="button"
                                                        className="adm-action-btn adm-btn-view"
                                                        disabled={p.closure_status === 'closed'}
                                                        onClick={() => saveEdit(p.id)}
                                                    >
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
                                                <span>
                                                    {p.operator.name || 'Не указано'}
                                                    <br />
                                                    <small>{p.operator.email || p.operator.phone}</small>
                                                </span>
                                            ) : (
                                                <span style={{ color: '#92400e', fontSize: 12 }}>Без оператора — недоступен в заказах</span>
                                            )}
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
                            {sortedPoints.length === 0 && (
                                <tr>
                                    <td colSpan={8} style={{ textAlign: 'center', padding: 32 }}>
                                        Ничего не найдено
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </MainLayout>
    );
}