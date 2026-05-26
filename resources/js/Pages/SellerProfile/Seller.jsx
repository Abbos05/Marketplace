import React from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { resolveAvatarUrl } from '@/lib/avatarUrl';
import '../../../css/product/ShopPage.css';
import ProductsCatalog from '@/Components/Product/FilterProducts';

function formatCount(n) {
    return new Intl.NumberFormat('ru-RU').format(Number(n) || 0);
}

function formatRating(rating) {
    if (rating === null || rating === undefined || rating === '') {
        return null;
    }
    const value = Number(rating);
    if (Number.isNaN(value) || value <= 0) {
        return null;
    }
    return value.toFixed(1);
}

export default function Seller({ auth }) {
    const { seller, sellerId, products, filters, facets = {}, total = 0, pagination = null } =
        usePage().props;

    const ratingLabel = formatRating(seller?.rating);
    const avatarUrl = resolveAvatarUrl(seller?.img);

    return (
        <MainLayout auth={auth}>
            <Head title={`${seller?.name || 'Магазин'} — Маркетплейс`} />

            <div className="seller-page">
                <section className="seller-hero">
                    <div className="seller-hero__inner">
                        <div className="seller-hero__main">
                            <div className="seller-hero__avatar">
                                {avatarUrl ? (
                                    <img
                                        src={avatarUrl}
                                        alt=""
                                        className="seller-hero__avatar-img"
                                    />
                                ) : (
                                    <span className="seller-hero__avatar-letter">
                                        {seller?.name?.charAt(0)?.toUpperCase() || 'М'}
                                    </span>
                                )}
                            </div>

                            <div className="seller-hero__info">
                                <span className="seller-hero__badge">Магазин</span>
                                <h1 className="seller-hero__name">{seller?.name || 'Магазин'}</h1>

                                <div className="seller-hero__stats">
                                    {ratingLabel && (
                                        <div className="seller-hero__stat seller-hero__stat--rating">
                                            <span className="seller-hero__stat-icon" aria-hidden>★</span>
                                            <span className="seller-hero__stat-value">{ratingLabel}</span>
                                            <span className="seller-hero__stat-label">рейтинг</span>
                                        </div>
                                    )}
                                    <div className="seller-hero__stat">
                                        <span className="seller-hero__stat-value">
                                            {formatCount(seller?.reviews_count)}
                                        </span>
                                        <span className="seller-hero__stat-label">отзывов</span>
                                    </div>
                                    <div className="seller-hero__stat">
                                        <span className="seller-hero__stat-value">
                                            {formatCount(seller?.orders)}
                                        </span>
                                        <span className="seller-hero__stat-label">заказов</span>
                                    </div>
                                    <div className="seller-hero__stat seller-hero__stat--muted">
                                        <span className="seller-hero__stat-value">
                                            {formatCount(seller?.likes)}
                                        </span>
                                        <span className="seller-hero__stat-label">в избранном</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="seller-hero__actions">
                            <button
                                type="button"
                                className="seller-hero__btn seller-hero__btn--primary"
                                onClick={() => {
                                    if (!auth?.user) {
                                        window.location.href = '/login';
                                        return;
                                    }
                                    if (seller?.id && auth.user.id === seller.id) {
                                        return;
                                    }
                                    if (!seller?.id) {
                                        return;
                                    }
                                    router.post(route('messages.open'), {
                                        type: 'seller_shop',
                                        seller_id: seller.id,
                                    });
                                }}
                            >
                                Написать продавцу
                            </button>
                        </div>
                    </div>
                </section>

                <ProductsCatalog
                    dataProduct={products || []}
                    seller={seller}
                    sellerId={sellerId ?? seller?.id}
                    isSellerProfile={true}
                    filters={filters || {}}
                    facets={facets}
                    total={total}
                    pagination={pagination}
                />
            </div>
        </MainLayout>
    );
}
