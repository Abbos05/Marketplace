import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';
import '../../../../css/seller/manage.css';

// Метки для фильтр-табов (вкладки)
const STATUS_META = {
    all:        { label: 'Все',              cls: 'all'        },
    approved:   { label: 'Опубликованы',     cls: 'approved'   },
    moderation: { label: 'На модерации',     cls: 'moderation' },
    rejected:   { label: 'Отклонённые',      cls: 'rejected'   },
    hidden:     { label: 'Скрытые',          cls: 'hidden'     },
};

// Метки для бейджа на карточке (единственное число)
const BADGE_META = {
    approved:   { label: 'Опубликован',   cls: 'approved'   },
    moderation: { label: 'На модерации',  cls: 'moderation' },
    rejected:   { label: 'Отклонён',      cls: 'rejected'   },
    hidden:     { label: 'Скрыт',         cls: 'hidden'     },
    draft:      { label: 'Черновик',      cls: 'draft'      },
};

const SORT_OPTIONS = [
    { value: 'newest',     label: 'Сначала новые'    },
    { value: 'oldest',     label: 'Сначала старые'   },
    { value: 'price_asc',  label: 'Цена: по возр.'   },
    { value: 'price_desc', label: 'Цена: по убыв.'   },
    { value: 'title',      label: 'По названию'       },
];

export default function Index({ products, statusCounts = {}, filters = {} }) {
    const currentStatus = filters.status ?? 'all';
    const currentSort   = filters.sort   ?? 'newest';


    const totalCount = Object.values(statusCounts).reduce((a, b) => a + Number(b), 0);

    const applyFilter = (status) => {
        router.get(route('seller.products'), { status, sort: currentSort }, { preserveState: true });
    };

    const applySort = (sort) => {
        router.get(route('seller.products'), { status: currentStatus, sort }, { preserveState: true });
    };

    const handleVisibilityToggle = (productId) => {
        router.post(
            route('seller.products.visibility', productId),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <SellerLayout title="Мои товары">
            <Head title="Мои товары" />

            {/* Toolbar */}
            <div className="idx-toolbar">
                <div className="idx-toolbar-left">
                    <span className="idx-count">Всего: {totalCount}</span>
                </div>
                <div className="idx-toolbar-right">
                    <select
                        className="idx-sort-select"
                        value={currentSort}
                        onChange={(e) => applySort(e.target.value)}
                    >
                        {SORT_OPTIONS.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                    <Link href={route('seller.products.create')} className="idx-add-btn">
                        + Добавить товар
                    </Link>
                </div>
            </div>

            {/* Status filter tabs */}
            <div className="idx-tabs">
                {Object.entries(STATUS_META).map(([key, meta]) => {
                    const count = key === 'all' ? totalCount : (statusCounts[key] ?? 0);
                    return (
                        <button
                            key={key}
                            type="button"
                            className={`idx-tab idx-tab--${meta.cls} ${currentStatus === key ? 'idx-tab--active' : ''}`}
                            onClick={() => applyFilter(key)}
                        >
                            {meta.label}
                            {count > 0 && <span className="idx-tab-count">{count}</span>}
                        </button>
                    );
                })}
            </div>

            {/* Empty state */}
            {products.length === 0 ? (
                <div className="idx-empty">
                    <p>
                        {currentStatus === 'all'
                            ? 'Пока нет товаров — добавьте первый!'
                            : `Нет товаров со статусом «${STATUS_META[currentStatus]?.label}»`}
                    </p>
                    {currentStatus === 'all' && (
                        <Link href={route('seller.products.create')} className="idx-add-btn">
                            Добавить товар
                        </Link>
                    )}
                </div>
            ) : (
                <div className="idx-grid">
                    {products.map((product) => {
                        const statusMeta = BADGE_META[product.status] ?? { label: product.status, cls: 'draft' };
                        const canToggle  = product.status === 'approved' || product.status === 'hidden';
                        const stockWarn  = product.total_stock === 0 && product.status === 'approved';

                        return (
                            <div key={product.id} className="idx-card">
                                {/* Image */}
                                <div className="idx-card-img">
                                    {product.main_image ? (
                                        <img src={product.main_image} alt={product.title} />
                                    ) : (
                                        <div className="idx-card-img-placeholder">Нет фото</div>
                                    )}
                                    <span className={`idx-status-badge idx-status-badge--${statusMeta.cls}`}>
                                        {statusMeta.label}
                                    </span>
                                </div>

                                {/* Info */}
                                <div className="idx-card-body">
                                    <h3 className="idx-card-title" title={product.title}>
                                        {product.title}
                                    </h3>
                                    <p className="idx-card-cat">{product.category?.name || '—'}</p>

                                    <div className="idx-card-stats">
                                        <span className="idx-stat">
                                            {product.min_price.toLocaleString('ru-RU')} ₽
                                        </span>
                                        <span className="idx-stat idx-stat--muted">
                                            {product.variants_count} вар.
                                        </span>
                                        <span className={`idx-stat ${stockWarn ? 'idx-stat--warn' : ''}`}>
                                            Склад: {product.total_stock}
                                        </span>
                                    </div>

                                    {stockWarn && (
                                        <div className="idx-card-alert">⚠ Товар закончился</div>
                                    )}
                                </div>

                                {/* Actions */}
                                <div className="idx-card-actions">
                                    <Link
                                        href={route('seller.products.manage', product.id)}
                                        className="idx-action-btn idx-action-btn--primary"
                                    >
                                        Управление
                                    </Link>
                                    <Link
                                        href={route('seller.products.edit', product.id)}
                                        className="idx-action-btn"
                                    >
                                        Изменить
                                    </Link>
                                    {canToggle && (
                                        <button
                                            type="button"
                                            className={`idx-action-btn ${product.status === 'hidden' ? 'idx-action-btn--show' : 'idx-action-btn--hide'}`}
                                            onClick={() => handleVisibilityToggle(product.id)}
                                        >
                                            {product.status === 'hidden' ? 'Показать' : 'Скрыть'}
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </SellerLayout>
    );
}
