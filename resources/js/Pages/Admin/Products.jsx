import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/admin/dashboard.css';

const PRODUCT_STATUSES = [
    { value: 'moderation', label: 'На модерации' },
    { value: 'approved',   label: 'Одобрен' },
    { value: 'rejected',   label: 'Отклонён' },
    { value: 'hidden',     label: 'Скрыт' },
    { value: 'archived',   label: 'Архив' },
    { value: 'draft',      label: 'Черновик' },
];
const PRODUCT_STATUS_MAP = Object.fromEntries(PRODUCT_STATUSES.map(s => [s.value, s.label]));

function productStatusColor(s) {
    if (s === 'approved') return 'adm-pstatus-approved';
    if (s === 'rejected') return 'adm-pstatus-rejected';
    if (s === 'moderation') return 'adm-pstatus-moderation';
    if (s === 'hidden' || s === 'archived') return 'adm-pstatus-hidden';
    return 'adm-pstatus-default';
}

export default function AdminProducts({ auth, products = [], search = '', status = 'all', counts = {} }) {
    const [message, setMessage] = useState(null);
    const [q, setQ] = useState(search);
    const [productComments, setProductComments] = useState({});

    const flash = (msg, isError = false) => {
        setMessage({ text: msg, error: isError });
        setTimeout(() => setMessage(null), 4000);
    };

    const reload = (overrides = {}) => {
        router.get('/admin/products', {
            search: overrides.search ?? q,
            status: overrides.status ?? status,
        }, { preserveScroll: true, preserveState: false });
    };

    const submitSearch = (e) => {
        e?.preventDefault();
        reload({ search: q });
    };

    const updateProductStatus = (productId, newStatus) => {
        const comment = productComments[productId] || '';
        router.post(`/admin/products/${productId}/status`, { status: newStatus, moderation_comment: comment }, {
            preserveScroll: true, preserveState: false,
            onSuccess: () => flash(`Статус товара #${productId} изменён`),
        });
    };

    const filters = [
        { k: 'all',        l: 'Все',          c: counts.all },
        { k: 'moderation', l: 'На модерации', c: counts.moderation },
        { k: 'approved',   l: 'Одобрены',     c: counts.approved },
        { k: 'rejected',   l: 'Отклонены',    c: counts.rejected },
        { k: 'hidden',     l: 'Скрыты',       c: counts.hidden },
        { k: 'archived',   l: 'Архив',        c: counts.archived },
        { k: 'draft',      l: 'Черновики',    c: counts.draft },
    ];

    return (
        <MainLayout auth={auth}>
            <Head title="Все товары · Админ" />

            {message && (
                <div className={`adm-flash ${message.error ? 'adm-flash-error' : ''}`} onClick={() => setMessage(null)}>
                    {message.text}
                </div>
            )}

            <div className="adm-detail-page">
                <div className="adm-detail-nav">
                    <a href="/admin/dashboard" className="adm-back-link">← Панель администратора</a>
                    <span style={{ margin: '0 8px', color: '#94a3b8' }}>|</span>
                    <a href="/admin/home-slides" className="adm-back-link">Слайдер главной</a>
                    <span style={{ margin: '0 8px', color: '#94a3b8' }}>|</span>
                    <a href="/admin/pickup-points" className="adm-back-link">Пункты выдачи</a>
                </div>

                <h1 className="adm-title">Все товары ({counts.all || 0})</h1>

                {/* Filter pills */}
                <div className="adm-filter-row">
                    {filters.map(f => (
                        <button
                            key={f.k}
                            className={`adm-filter-pill ${status === f.k ? 'active' : ''}`}
                            onClick={() => reload({ status: f.k })}
                        >
                            {f.l}{f.c !== undefined ? ` (${f.c})` : ''}
                        </button>
                    ))}
                </div>

                {/* Search */}
                <form onSubmit={submitSearch} className="adm-search-bar adm-mb">
                    <input
                        type="text"
                        className="admin-search-input"
                        placeholder="Поиск по названию, ID, имени продавца, email..."
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                    <button type="submit" className="adm-action-btn adm-btn-view">Найти</button>
                    {search && (
                        <button
                            type="button"
                            className="adm-action-btn"
                            onClick={() => { setQ(''); reload({ search: '' }); }}
                        >Очистить</button>
                    )}
                </form>

                {products.length === 0 ? (
                    <div className="adm-empty">
                        {search ? `Товары по запросу «${search}» не найдены` : 'Товары не найдены'}
                    </div>
                ) : (
                    <>
                        <div className="adm-result-count">Показано: {products.length}</div>
                        <div className="adm-products-admin-list">
                            {products.map(p => (
                                <div key={p.id} className="adm-product-admin-card">
                                    <img
                                        src={p.image || '/img/products/default.png'}
                                        className="adm-product-admin-img"
                                        alt={p.title}
                                    />
                                    <div className="adm-product-admin-info">
                                        <div className="adm-product-name">
                                            #{p.id} · {p.title}
                                            {p.is_on_action && <span className="adm-action-tag"> Акция</span>}
                                        </div>
                                        <div className="adm-product-price">{Number(p.min_price).toLocaleString('ru-RU')} ₽</div>
                                        <div className="adm-product-meta">
                                            {p.category && <span>{p.category.name} · </span>}
                                            Продавец:&nbsp;
                                            {p.seller ? (
                                                <a href={`/admin/users/${p.seller.id}/detail`} className="adm-link">
                                                    {p.seller.name}
                                                </a>
                                            ) : '—'}
                                        </div>
                                        <div className="adm-product-meta">
                                            Продаж: {p.sales_count || 0} · Просмотров: {p.views_count || 0}
                                        </div>
                                        {p.moderation_comment && (
                                            <div className="adm-product-comment">
                                                Комментарий модератора: {p.moderation_comment}
                                            </div>
                                        )}
                                    </div>
                                    <div className="adm-product-admin-actions">
                                        <span className={`adm-product-status-badge ${productStatusColor(p.status)}`}>
                                            {PRODUCT_STATUS_MAP[p.status] || p.status}
                                        </span>
                                        <select
                                            className="adm-status-select"
                                            defaultValue={p.status}
                                            onChange={(e) => updateProductStatus(p.id, e.target.value)}
                                        >
                                            {PRODUCT_STATUSES.map(s => (
                                                <option key={s.value} value={s.value}>{s.label}</option>
                                            ))}
                                        </select>
                                        <input
                                            type="text"
                                            className="adm-comment-input"
                                            placeholder="Комментарий..."
                                            value={productComments[p.id] || ''}
                                            onChange={(e) => setProductComments(prev => ({ ...prev, [p.id]: e.target.value }))}
                                        />
                                        <a
                                            href={`/product/${p.id}`}
                                            className="adm-action-btn adm-btn-view"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Открыть
                                        </a>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </MainLayout>
    );
}
