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

export default function Index({ products, statusCounts = {}, filters = {}, highlightVariantId = null }) {
    const currentStatus = filters.status ?? 'all';
    const currentSort   = filters.sort   ?? 'newest';
    const currentSearch = filters.search ?? '';
    const [search, setSearch] = React.useState(currentSearch);

    React.useEffect(() => {
        setSearch(currentSearch);
    }, [currentSearch]);

    React.useEffect(() => {
        if (!highlightVariantId) return undefined;
        const timer = window.setTimeout(() => {
            document
                .getElementById(`seller-variant-card-${highlightVariantId}`)
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 250);
        return () => window.clearTimeout(timer);
    }, [highlightVariantId]);

    const totalCount = Object.values(statusCounts).reduce((a, b) => a + Number(b), 0);
    const items = products?.data ?? [];
    const currentPage = products?.current_page ?? 1;
    const lastPage = products?.last_page ?? 1;
    const totalItems = products?.total ?? items.length;

    const applyFilter = (status) => {
        router.get(route('seller.products'), { status, sort: currentSort, search: currentSearch, page: 1 }, { preserveState: true });
    };

    const applySort = (sort) => {
        router.get(route('seller.products'), { status: currentStatus, sort, search: currentSearch, page: 1 }, { preserveState: true });
    };

    const applySearch = (e) => {
        e.preventDefault();
        router.get(route('seller.products'), { status: currentStatus, sort: currentSort, search: search.trim(), page: 1 }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const goToPage = (page) => {
        if (page < 1 || page > lastPage) return;
        router.get(route('seller.products'), { status: currentStatus, sort: currentSort, search: currentSearch, page }, {
            preserveState: true,
            preserveScroll: true,
        });
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
                    <span className="idx-count">Всего вариантов: {totalCount}</span>
                </div>
                <div className="idx-toolbar-right">
                    <form className="idx-search-form" onSubmit={applySearch}>
                        <input
                            type="text"
                            className="idx-search-input"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Поиск по названию товара"
                        />
                        <button type="submit" className="idx-search-btn">Найти</button>
                    </form>
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
            {items.length === 0 ? (
                <div className="idx-empty">
                    <p>
                        {currentStatus === 'all'
                            ? 'Ничего не найдено — попробуйте изменить фильтры или поиск.'
                            : `Нет вариантов со статусом «${STATUS_META[currentStatus]?.label}»`}
                    </p>
                    {currentStatus === 'all' && (
                        <Link href={route('seller.products.create')} className="idx-add-btn">
                            Добавить товар
                        </Link>
                    )}
                </div>
            ) : (
                <>
                <div className="idx-grid">
                    {items.map((product) => {
                        const statusMeta = BADGE_META[product.status] ?? { label: product.status, cls: 'draft' };
                        const canToggle  = product.status === 'approved' || product.status === 'hidden';
                        const stockWarn  = product.total_stock === 0 && product.status === 'approved';
                        const productId = product.product_id ?? product.id;

                        const isHighlighted = highlightVariantId === product.id;

                        return (
                            <div
                                key={product.id}
                                id={`seller-variant-card-${product.id}`}
                                className={`idx-card${isHighlighted ? ' idx-card--highlighted' : ''}`}
                            >
                                {/* Image */}
                                <div className="idx-card-img">
                                    {product.main_image ? (
                                        <img src={product.main_image}
                                        onError={(e) => {
                                            e.currentTarget.onerror = null;
                                            e.currentTarget.src = '/img/products/default.png';
                                        }}
                                        alt={product.title} />
                                    ) : (
                                        <div className="idx-card-img-placeholder">Нет фото</div>
                                    )}
                                    <span className={`idx-status-badge idx-status-badge--${statusMeta.cls}`}>
                                        {statusMeta.label}
                                    </span>
                                    {isHighlighted && (
                                        <span className="idx-changed-badge">Изменено</span>
                                    )}
                                    {product.promotion_label && (
                                        <span className="idx-promo-badge" title="Акция на карточке">
                                            {product.promotion_label}
                                        </span>
                                    )}
                                </div>

                                {/* Info */}
                                <div className="idx-card-body">
                                    <h3 className="idx-card-title" title={product.title}>
                                        {product.title}
                                    </h3>
                                    <p className="idx-card-cat idx-card-cat--variant">{product.variant_label}</p>
                                    <p className="idx-card-cat">{product.category?.name || '—'}</p>

                                    {(product.promotion_badges?.length ?? 0) > 0 && (
                                        <p className="idx-card-promo-line">
                                            Акция:{' '}
                                            {product.promotion_badges.map((b) => b.label).join(', ')}
                                        </p>
                                    )}

                                    <div className="idx-card-stats">
                                        <span className="idx-stat">
                                            {product.min_price.toLocaleString('ru-RU')} ₽
                                        </span>
                                        <span className="idx-stat idx-stat--muted">
                                            В товаре: {product.variants_count} вар.
                                        </span>
                                        <span className={`idx-stat ${stockWarn ? 'idx-stat--warn' : ''}`}>
                                            Склад: {product.total_stock}
                                        </span>
                                    </div>

                                    {stockWarn && (
                                        <div className="idx-card-alert">⚠ Товар закончился</div>
                                    )}

                                    {product.moderation_comment && (
                                        <div className="idx-card-moderation">
                                            <strong>Комментарий модератора:</strong>
                                            <span>{product.moderation_comment}</span>
                                        </div>
                                    )}
                                </div>

                                {/* Actions */}
                                <div className="idx-card-actions">
                                    <Link
                                        href={route('seller.products.manage', productId)}
                                        className="idx-action-btn idx-action-btn--primary"
                                    >
                                        Управление
                                    </Link>
                                    <Link
                                        href={route('seller.products.edit', productId)}
                                        className="idx-action-btn"
                                    >
                                        Изменить
                                    </Link>
                                    {canToggle && (
                                        <button
                                            type="button"
                                            className={`idx-action-btn ${product.status === 'hidden' ? 'idx-action-btn--show' : 'idx-action-btn--hide'}`}
                                            onClick={() => handleVisibilityToggle(productId)}
                                        >
                                            {product.status === 'hidden' ? 'Показать' : 'Скрыть'}
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
                <div className="idx-pagination">
                    <span className="idx-pagination-info">
                        Страница {currentPage} из {lastPage} · Всего: {totalItems}
                    </span>
                    <div className="idx-pagination-actions">
                        <button
                            type="button"
                            className="idx-page-btn"
                            disabled={currentPage <= 1}
                            onClick={() => goToPage(currentPage - 1)}
                        >
                            Назад
                        </button>
                        <button
                            type="button"
                            className="idx-page-btn"
                            disabled={currentPage >= lastPage}
                            onClick={() => goToPage(currentPage + 1)}
                        >
                            Вперёд
                        </button>
                    </div>
                </div>
                </>
            )}
        </SellerLayout>
    );
}
