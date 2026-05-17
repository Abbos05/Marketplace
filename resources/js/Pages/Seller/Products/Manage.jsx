import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';
import ArticleNumber from '@/Components/Product/ArticleNumber';
import '../../../../css/seller/manage.css';

const STATUS_LABELS = {
    moderation: { label: 'На модерации', cls: 'moderation' },
    approved:   { label: 'Опубликован',  cls: 'approved'   },
    rejected:   { label: 'Отклонён',     cls: 'rejected'   },
    hidden:     { label: 'Скрыт',        cls: 'hidden'      },
    draft:      { label: 'Черновик',     cls: 'draft'       },
};

function formatOptions(options) {
    if (!options || typeof options !== 'object') return '—';
    return Object.values(options).filter(Boolean).join(' / ') || '—';
}

export default function Manage({ product, variants }) {
    const [stockValues, setStockValues]   = useState(() =>
        Object.fromEntries(variants.map((v) => [v.id, String(v.stock)])),
    );
    const [savingStock, setSavingStock]   = useState({});
    const [processing, setProcessing]     = useState(false);

    const status = STATUS_LABELS[product.status] ?? { label: product.status, cls: 'draft' };

    const handleVisibilityToggle = () => {
        setProcessing(true);
        router.post(
            route('seller.products.visibility', product.id),
            {},
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    const handleVariantToggle = (variantId) => {
        router.post(
            route('seller.products.variant.toggle', { product: product.id, variant: variantId }),
            {},
            { preserveScroll: true },
        );
    };

    const handleStockSave = (variantId) => {
        setSavingStock((s) => ({ ...s, [variantId]: true }));
        router.post(
            route('seller.products.variant.stock', { product: product.id, variant: variantId }),
            { stock: parseInt(stockValues[variantId], 10) || 0 },
            {
                preserveScroll: true,
                onFinish: () => setSavingStock((s) => ({ ...s, [variantId]: false })),
            },
        );
    };

    const canHide = product.status === 'approved' || product.status === 'hidden';

    return (
        <SellerLayout title={`Управление: ${product.title}`}>
            <Head title={`Управление: ${product.title}`} />

            {/* Toolbar */}
            <div className="manage-toolbar">
                <Link href={route('seller.products')} className="manage-back-btn">
                    ← Мои товары
                </Link>
                <div className="manage-toolbar-actions">
                    <Link
                        href={route('seller.products.edit', product.id)}
                        className="manage-btn manage-btn--secondary"
                    >
                        Редактировать
                    </Link>
                    {canHide && (
                        <button
                            type="button"
                            className={`manage-btn ${product.status === 'hidden' ? 'manage-btn--success' : 'manage-btn--warn'}`}
                            onClick={handleVisibilityToggle}
                            disabled={processing}
                        >
                            {product.status === 'hidden' ? 'Показать на витрине' : '🚫 Скрыть с витрины'}
                        </button>
                    )}
                    {product.status === 'approved' && product.is_listed !== false && (
                        <a
                            href={`/product/${product.id}`}
                            target="_blank"
                            rel="noreferrer"
                            className="manage-btn manage-btn--ghost"
                        >
                            На витрине ↗
                        </a>
                    )}
                </div>
            </div>

            {/* Product header */}
            <div className="manage-header">
                <div className="manage-header-img">
                    {product.main_image ? (
                        <img src={product.main_image} alt={product.title} />
                    ) : (
                        <div className="manage-header-img-placeholder">Нет фото</div>
                    )}
                </div>
                <div className="manage-header-info">
                    <h1 className="manage-title">{product.title}</h1>
                    <p className="manage-meta">
                        Категория: <strong>{product.category}</strong>
                    </p>
                    <p className="manage-meta">
                        Добавлен: <strong>{product.created_at}</strong>
                    </p>
                    {product.moderation_comment && (
                        <div className="manage-moderation-banner" role="alert">
                            <strong>Комментарий модератора</strong>
                            <p>{product.moderation_comment}</p>
                        </div>
                    )}
                    <span className={`manage-status-badge manage-status-badge--${status.cls}`}>
                        {status.label}
                    </span>
                </div>
            </div>

            {/* Stats row */}
            <div className="manage-stats">
                <div className="manage-stat-card">
                    <div className="manage-stat-value">{product.variants_count ?? variants.length}</div>
                    <div className="manage-stat-label">Вариантов</div>
                </div>
                <div className="manage-stat-card">
                    <div className="manage-stat-value">{product.total_stock}</div>
                    <div className="manage-stat-label">Остаток (шт)</div>
                </div>
                <div className="manage-stat-card">
                    <div className="manage-stat-value">{product.active_variants}</div>
                    <div className="manage-stat-label">Активных</div>
                </div>
                {product.hidden_variants > 0 && (
                    <div className="manage-stat-card manage-stat-card--warn">
                        <div className="manage-stat-value">{product.hidden_variants}</div>
                        <div className="manage-stat-label">Скрытых</div>
                    </div>
                )}
                <div className="manage-stat-card">
                    <div className="manage-stat-value">
                        {product.min_price.toLocaleString('ru-RU')} ₽
                    </div>
                    <div className="manage-stat-label">Цена от</div>
                </div>
                <div className="manage-stat-card">
                    <div className="manage-stat-value">{product.views_count}</div>
                    <div className="manage-stat-label">Просмотры</div>
                </div>
                <div className="manage-stat-card">
                    <div className="manage-stat-value">{product.sales_count}</div>
                    <div className="manage-stat-label">Продаж</div>
                </div>
            </div>

            {/* Variants table */}
            <div className="manage-section">
                <h2 className="manage-section-title">Варианты товара</h2>

                {variants.length === 0 ? (
                    <p className="manage-empty">Нет вариантов</p>
                ) : (
                    <div className="manage-table-wrap">
                        <table className="manage-table">
                            <thead>
                                <tr>
                                    <th>Фото</th>
                                    <th>Вариант</th>
                                    <th>Артикул</th>
                                    <th>Цена</th>
                                    <th>Остаток</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                {variants.map((v) => {
                                    const stockZero = v.stock === 0 && v.is_active;

                                    return (
                                        <tr
                                            key={v.id}
                                            className={
                                                !v.is_active
                                                    ? 'manage-row--hidden'
                                                    : stockZero
                                                    ? 'manage-row--warn'
                                                    : ''
                                            }
                                        >
                                            {/* Фото */}
                                            <td>
                                                {v.image_url ? (
                                                    <img
                                                        src={v.image_url}
                                                        className="manage-variant-thumb"
                                                        alt=""
                                                    />
                                                ) : (
                                                    <div className="manage-variant-thumb manage-variant-thumb--empty" />
                                                )}
                                            </td>

                                            {/* Вариант */}
                                            <td className="manage-variant-options">
                                                {formatOptions(v.options)}
                                            </td>

                                            {/* Артикул */}
                                            <td className="manage-sku">
                                                <ArticleNumber sku={v.sku} />
                                            </td>

                                            {/* Цена */}
                                            <td>
                                                <div className="manage-price">
                                                    {v.price.toLocaleString('ru-RU')} ₽
                                                </div>
                                                {v.old_price && (
                                                    <div className="manage-old-price">
                                                        <s>{v.old_price.toLocaleString('ru-RU')} ₽</s>
                                                    </div>
                                                )}
                                            </td>

                                            {/* Остаток — inline edit */}
                                            <td>
                                                <div className="manage-stock-cell">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        className={`manage-stock-input ${stockZero ? 'manage-stock-input--zero' : ''}`}
                                                        value={stockValues[v.id]}
                                                        onChange={(e) =>
                                                            setStockValues((s) => ({
                                                                ...s,
                                                                [v.id]: e.target.value,
                                                            }))
                                                        }
                                                        onKeyDown={(e) => {
                                                            if (e.key === 'Enter') handleStockSave(v.id);
                                                        }}
                                                    />
                                                    {String(stockValues[v.id]) !== String(v.stock) && (
                                                        <button
                                                            type="button"
                                                            className="manage-stock-save"
                                                            onClick={() => handleStockSave(v.id)}
                                                            disabled={savingStock[v.id]}
                                                        >
                                                            {savingStock[v.id] ? '…' : '✓'}
                                                        </button>
                                                    )}
                                                </div>
                                                {stockZero && (
                                                    <div className="manage-stock-warn">Нет в наличии</div>
                                                )}
                                            </td>

                                            {/* Статус */}
                                            <td>
                                                <span
                                                    className={`manage-variant-badge ${v.is_active ? 'manage-variant-badge--active' : 'manage-variant-badge--hidden'}`}
                                                >
                                                    {v.is_active ? 'Активен' : 'Скрыт'}
                                                </span>
                                            </td>

                                            {/* Действия */}
                                            <td>
                                                <button
                                                    type="button"
                                                    className={`manage-btn-sm ${v.is_active ? 'manage-btn-sm--warn' : 'manage-btn-sm--success'}`}
                                                    onClick={() => handleVariantToggle(v.id)}
                                                >
                                                    {v.is_active ? 'Скрыть' : 'Показать'}
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Tips */}
            <div className="manage-tips">
                <p>💡 <strong>Остаток = 0</strong> и вариант активен — товар показывается как «нет в наличии».</p>
                <p>💡 Скрытый вариант не виден покупателям, но остаётся в истории заказов.</p>
                <p>💡 После редактирования товар снова отправляется на модерацию.</p>
            </div>
        </SellerLayout>
    );
}
