import React, { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import '../../../css/admin/dashboard.css';

const STATUS_TABS = [
    { value: 'all', label: 'Все' },
    { value: 'pending', label: 'На модерации' },
    { value: 'published', label: 'Опубликованные' },
    { value: 'hidden', label: 'Скрытые' },
];

export default function AdminReviews({ auth, reviews, status: initialStatus = 'pending', search: searchFromServer = '' }) {
    const [searchInput, setSearchInput] = useState(searchFromServer);
    const [confirmModal, setConfirmModal] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const [previewImage, setPreviewImage] = useState(null);

    useEffect(() => {
        setSearchInput(searchFromServer);
    }, [searchFromServer]);

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

    const redirectBody = (extra = {}) => ({
        redirect_status: initialStatus,
        redirect_search: searchInput.trim(),
        ...extra,
    });

    const approve = (row) => {
        setConfirmModal({
            title: 'Опубликовать отзыв?',
            message: 'Отзыв станет виден на странице товара.',
            confirmText: 'Опубликовать',
            variant: 'default',
            review: row,
            onConfirm: () => {
                router.post(route('admin.reviews.approve', row.id), redirectBody(), { preserveScroll: true });
                setConfirmModal(null);
            },
        });
    };

    const openRejectModal = (row, isHide = false) => {
        setRejectReason('');
        setConfirmModal({
            title: isHide ? 'Скрыть отзыв?' : 'Отклонить отзыв?',
            message: isHide
                ? 'Отзыв будет скрыт. Укажите причину — её увидит только администрация.'
                : 'Отзыв будет отклонён. Покупатель сможет оставить новый. Укажите причину.',
            confirmText: isHide ? 'Скрыть' : 'Отклонить',
            variant: 'danger',
            showReason: true,
            review: row,
            onConfirm: (reason) => {
                const comment = (reason || '').trim();
                if (comment.length < 3) {
                    return;
                }
                router.post(route('admin.reviews.reject', row.id), redirectBody({ moderation_comment: comment }), {
                    preserveScroll: true,
                });
                setConfirmModal(null);
                setRejectReason('');
            },
        });
    };

    const restoreReview = (id) => {
        setConfirmModal({
            title: 'Восстановить отзыв?',
            message: 'Отзыв вернётся в очередь модерации.',
            confirmText: 'Восстановить',
            variant: 'default',
            onConfirm: () => {
                router.post(route('admin.reviews.restore', id), {}, { preserveScroll: true });
                setConfirmModal(null);
            },
        });
    };

    const statusLabel = (r) => {
        if (r.is_hidden || initialStatus === 'hidden') {
            return <span style={{ color: '#6b7280' }}>Скрыт</span>;
        }
        if (r.is_moderated) {
            return <span style={{ color: '#059669' }}>Опубликован</span>;
        }
        return <span style={{ color: '#b45309' }}>На модерации</span>;
    };

    return (
        <MainLayout auth={auth}>
            <Head title="Отзывы · Админ" />

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
                                        <td style={{ maxWidth: 320, fontSize: 13, whiteSpace: 'pre-wrap' }}>
                                            {r.comment_snippet || '—'}
                                            {r.images?.length > 0 && (
                                                <div className="adm-review-images">
                                                    {r.images.map((img) => (
                                                        <button
                                                            key={img.id}
                                                            type="button"
                                                            className="adm-review-images__thumb"
                                                            onClick={() => setPreviewImage(img.url)}
                                                        >
                                                            <img src={img.url} alt="" />
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                            {r.moderation_comment && (
                                                <div style={{ marginTop: 6, color: '#b91c1c', fontSize: 12 }}>
                                                    Причина: {r.moderation_comment}
                                                </div>
                                            )}
                                        </td>
                                        <td>{statusLabel(r)}</td>
                                        <td style={{ whiteSpace: 'nowrap', fontSize: 13 }}>
                                            {r.deleted_at || r.created_at}
                                        </td>
                                        <td style={{ whiteSpace: 'nowrap' }}>
                                            {initialStatus === 'hidden' || r.is_hidden ? (
                                                <button type="button" className="adm-action-btn adm-btn-view" onClick={() => restoreReview(r.id)}>
                                                    Восстановить
                                                </button>
                                            ) : !r.is_moderated ? (
                                                <>
                                                    <button type="button" className="adm-action-btn adm-btn-view" onClick={() => approve(r)}>
                                                        Опубликовать
                                                    </button>{' '}
                                                    <button type="button" className="adm-action-btn" onClick={() => openRejectModal(r, false)}>
                                                        Отклонить
                                                    </button>
                                                </>
                                            ) : (
                                                <button type="button" className="adm-action-btn" onClick={() => openRejectModal(r, true)}>
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

            {confirmModal && (
                <ReviewConfirmModal
                    {...confirmModal}
                    rejectReason={rejectReason}
                    onRejectReasonChange={setRejectReason}
                    onClose={() => {
                        setConfirmModal(null);
                        setRejectReason('');
                    }}
                />
            )}

            {previewImage && (
                <div className="adm-review-lightbox" onClick={() => setPreviewImage(null)} role="presentation">
                    <div className="adm-review-lightbox__inner" onClick={(e) => e.stopPropagation()}>
                        <button type="button" className="adm-review-lightbox__close" onClick={() => setPreviewImage(null)} aria-label="Закрыть">
                            ×
                        </button>
                        <img src={previewImage} alt="" />
                    </div>
                </div>
            )}
        </MainLayout>
    );
}

function ReviewConfirmModal({
    title,
    message,
    confirmText,
    variant,
    showReason,
    review,
    rejectReason,
    onRejectReasonChange,
    onConfirm,
    onClose,
}) {
    const [processing, setProcessing] = useState(false);
    const [reasonError, setReasonError] = useState('');

    const handleConfirm = () => {
        if (showReason) {
            const reason = (rejectReason || '').trim();
            if (reason.length < 3) {
                setReasonError('Укажите причину (минимум 3 символа).');
                return;
            }
            setReasonError('');
            onConfirm(reason);
            return;
        }
        onConfirm();
    };

    return (
        <div className="phone-modal-overlay" onClick={onClose} role="presentation">
            <div className="phone-modal" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                <button type="button" onClick={onClose} className="modal-close-btn" aria-label="Закрыть">
                    ×
                </button>
                <h3 className="phone-modal-title">{title}</h3>
                <p className="phone-modal-text">{message}</p>
                {review?.comment && (
                    <div className="adm-review-modal-preview">
                        <p className="adm-review-modal-preview__text">{review.comment}</p>
                        {review.images?.length > 0 && (
                            <div className="adm-review-images">
                                {review.images.map((img) => (
                                    <a key={img.id} href={img.url} target="_blank" rel="noreferrer" className="adm-review-images__thumb">
                                        <img src={img.url} alt="" />
                                    </a>
                                ))}
                            </div>
                        )}
                    </div>
                )}
                {showReason && (
                    <label style={{ display: 'flex', flexDirection: 'column', gap: 6, marginTop: 12 }}>
                        <span className="adm-stat-label">Причина</span>
                        <textarea
                            className="admin-search-input"
                            rows={3}
                            value={rejectReason}
                            onChange={(e) => {
                                onRejectReasonChange(e.target.value);
                                setReasonError('');
                            }}
                            placeholder="Например: оскорбления, спам, не по товару…"
                        />
                        {reasonError && <span style={{ color: '#b91c1c', fontSize: 13 }}>{reasonError}</span>}
                    </label>
                )}
                <div className="phone-modal-actions" style={{ marginTop: 16 }}>
                    <button type="button" className="phone-modal-btn phone-modal-btn--cancel" onClick={onClose} disabled={processing}>
                        Отмена
                    </button>
                    <button
                        type="button"
                        className={`phone-modal-btn phone-modal-btn--submit${variant === 'danger' ? ' phone-modal-btn--danger' : ''}`}
                        onClick={() => {
                            setProcessing(true);
                            handleConfirm();
                            setProcessing(false);
                        }}
                        disabled={processing}
                    >
                        {confirmText}
                    </button>
                </div>
            </div>
        </div>
    );
}
