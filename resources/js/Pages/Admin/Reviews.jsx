import React, { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/admin/dashboard.css';

const STATUS_TABS = [
    { value: 'pending', label: 'На модерации' },
    { value: 'published', label: 'Опубликованные' },
    { value: 'all', label: 'Все' },
];

export default function AdminReviews({ auth, reviews, status: initialStatus = 'pending', search: searchFromServer = '' }) {
    const { flash } = usePage().props;
    const [flashMsg, setFlashMsg] = useState(null);
    const [searchInput, setSearchInput] = useState(searchFromServer);

    useEffect(() => {
        setSearchInput(searchFromServer);
    }, [searchFromServer]);

    useEffect(() => {
        if (flash?.success) {
            setFlashMsg(flash.success);
            const t = setTimeout(() => setFlashMsg(null), 4000);
            return () => clearTimeout(t);
        }
        return undefined;
    }, [flash?.success]);

    const rows = reviews?.data ?? [];
    const links = reviews?.links ?? [];

    const appliedSearch = searchFromServer;
    const queryPayload = () => {
        const s = searchInput.trim();
        return s === '' ? { status: initialStatus } : { status: initialStatus, search: s };
    };

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(route('admin.reviews.index'), queryPayload(), { preserveScroll: true });
    };

    const clearSearch = () => {
        setSearchInput('');
        router.get(route('admin.reviews.index'), { status: initialStatus }, { preserveScroll: true });
    };

    const setStatus = (value) => {
        const s = searchInput.trim();
        router.get(
            route('admin.reviews.index'),
            s === '' ? { status: value } : { status: value, search: s },
            { preserveScroll: true }
        );
    };

    const redirectBody = () => ({
        redirect_status: initialStatus,
        redirect_search: searchInput.trim(),
    });

    const approve = (id) => {
        router.post(route('admin.reviews.approve', id), redirectBody(), { preserveScroll: true });
    };

    const reject = (id) => {
        if (!confirm('Отклонить отзыв? Он будет скрыт, покупатель сможет написать новый.')) return;
        router.post(route('admin.reviews.reject', id), redirectBody(), { preserveScroll: true });
    };

    return (
        <MainLayout auth={auth}>
            <Head title="Отзывы · Админ" />

            {flashMsg && (
                <div className="adm-flash" onClick={() => setFlashMsg(null)} style={{ margin: '16px auto', maxWidth: 1100 }}>
                    {flashMsg}
                </div>
            )}

            <div className="adm-detail-page">
                <div className="adm-detail-nav">
                    <a href="/admin/dashboard" className="adm-back-link">
                        ← Панель администратора
                    </a>
                </div>

                <h1 className="adm-title">Модерация отзывов о товарах</h1>

                <form
                    className="adm-detail-card"
                    style={{ marginBottom: 20, padding: 16, display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'flex-end' }}
                    onSubmit={submitSearch}
                >
                    <label style={{ flex: '1 1 240px', display: 'flex', flexDirection: 'column', gap: 6, margin: 0 }}>
                        <span className="adm-stat-label" style={{ fontSize: 13, fontWeight: 600 }}>
                            Поиск по ID, товару, автору, тексту
                        </span>
                        <input
                            type="search"
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            className="admin-search-input"
                            placeholder="Например: наушники, Иван или 42"
                            autoComplete="off"
                        />
                    </label>
                    <button type="submit" className="adm-action-btn adm-btn-view">
                        Найти
                    </button>
                    {appliedSearch ? (
                        <button type="button" className="adm-action-btn" onClick={clearSearch}>
                            Сбросить
                        </button>
                    ) : null}
                </form>

                <div className="adm-detail-card" style={{ marginBottom: 20, padding: 12, display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    {STATUS_TABS.map((tab) => (
                        <button
                            key={tab.value}
                            type="button"
                            className={`adm-nav-item ${initialStatus === tab.value ? 'active' : ''}`}
                            style={{ border: '1px solid var(--adm-border, #e5e7eb)', borderRadius: 8, padding: '8px 14px', cursor: 'pointer' }}
                            onClick={() => setStatus(tab.value)}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                <div className="adm-table-wrap">
                    <table className="adm-table">
                        <thead>
                            <tr>
                                <th>Товар</th>
                                <th>Вариант</th>
                                <th>Автор</th>
                                <th>Оценка</th>
                                <th>Комментарий</th>
                                <th>Статус</th>
                                <th>Дата</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={8} style={{ padding: 24, textAlign: 'center', color: '#6b7280' }}>
                                        {appliedSearch
                                            ? 'Ничего не найдено — попробуйте другой запрос или сбросьте поиск'
                                            : 'Нет отзывов в этой выборке'}
                                    </td>
                                </tr>
                            ) : (
                                rows.map((r) => (
                                    <tr key={r.id}>
                                        <td>
                                            {r.product ? (
                                                <a href={route('product.show', r.product.id)} className="adm-back-link" style={{ fontWeight: 600 }}>
                                                    {r.product.title}
                                                </a>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td style={{ maxWidth: 140, fontSize: 13 }}>{r.variant_label ?? '—'}</td>
                                        <td>{r.user?.name ?? '—'}</td>
                                        <td>
                                            <span style={{ color: '#f59e0b', letterSpacing: 1 }}>{'★'.repeat(r.rating)}</span>
                                        </td>
                                        <td style={{ maxWidth: 280, fontSize: 13, whiteSpace: 'pre-wrap' }}>{r.comment_snippet || '—'}</td>
                                        <td>{r.is_moderated ? <span style={{ color: '#059669' }}>Опубликован</span> : <span style={{ color: '#b45309' }}>На модерации</span>}</td>
                                        <td style={{ whiteSpace: 'nowrap', fontSize: 13 }}>{r.created_at}</td>
                                        <td style={{ whiteSpace: 'nowrap' }}>
                                            {!r.is_moderated ? (
                                                <>
                                                    <button type="button" className="adm-action-btn adm-btn-view" onClick={() => approve(r.id)}>
                                                        Опубликовать
                                                    </button>{' '}
                                                    <button type="button" className="adm-action-btn" onClick={() => reject(r.id)}>
                                                        Отклонить
                                                    </button>
                                                </>
                                            ) : (
                                                <button type="button" className="adm-action-btn" onClick={() => reject(r.id)}>
                                                    Скрыть
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {links.length > 3 && (
                    <nav className="adm-detail-card" style={{ marginTop: 16, padding: 12, display: 'flex', flexWrap: 'wrap', gap: 6, alignItems: 'center' }}>
                        {links.map((l, i) =>
                            l.url ? (
                                <Link key={i} href={l.url} className="adm-action-btn adm-btn-view" preserveScroll style={{ textDecoration: 'none' }}>
                                    <span dangerouslySetInnerHTML={{ __html: l.label }} />
                                </Link>
                            ) : (
                                <span key={i} className="adm-action-btn" style={{ opacity: 0.5, cursor: 'default' }} dangerouslySetInnerHTML={{ __html: l.label }} />
                            )
                        )}
                    </nav>
                )}
            </div>
        </MainLayout>
    );
}
