import React from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/product/ShopPage.css';
import ProductsCatalog from '@/Components/Product/FilterProducts';

export default function Seller({ auth }) {
    const { seller, products, filters, facets = {}, total = 0 } = usePage().props;

    return (
        <MainLayout auth={auth}>
            <Head title={`${seller?.name || 'Страница Продавца'} - Маркетплейс`} />
            <div className="seller">
                <div className="seller__inner">
                    <div className="seller__main">
                        <div className="seller__avatar">
                            {seller?.img ? (
                                <img
                                    src={seller.img || '/img/products/default.png'}
                                    onClick={() => {
                                        window.open(
                                            seller.img || '/img/products/default.png',
                                            '_blank'
                                        );
                                    }}
                                    alt={`${seller.name}'s avatar`}
                                    className="seller__avatar-img"
                                />
                            ) : (
                                <div className="seller__avatar-placeholder">
                                    <span>{seller?.name?.charAt(0) || 'A'}</span>
                                </div>
                            )}
                        </div>

                        <div className="seller__info">
                            <h2 className="seller__name ">{seller?.name || 'LabuMarket'}</h2>
                            <div className="seller__details">
                                <div className="seller__shop seller__details--shop">
                                    <span className="seller__shop-value">Магазин</span>
                                </div>
                                <div className="seller__stat seller__stat--rating">
                                    <svg
                                        width="16px"
                                        height="16px"
                                        viewBox="0 -0.5 33 33"
                                        version="1.1"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <g fill="#000">
                                            <polygon points="27.865 31.83 17.615 26.209 7.462 32.009 9.553 20.362 0.99 12.335 12.532 10.758 17.394 0 22.436 10.672 34 12.047 25.574 20.22" />
                                        </g>
                                    </svg>
                                    <span className="seller__stat-value">{seller?.rating ?? '5.0'}</span>
                                </div>
                                <div className="seller__stat seller__stat--reviews">
                                    <span className="seller__stat-value">{seller?.review ?? 0}</span>
                                    <span className="seller__stat-label"> отзывов</span>
                                </div>
                                <div className="seller__stat seller__stat--orders">
                                    <span className="seller__stat-value">{seller?.orders ?? 0}</span>
                                    <span className="seller__stat-label"> заказов</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="seller__actions">
                        <button
                            type="button"
                            className="seller__button seller__button--message"
                            onClick={() => {
                                if (!auth?.user) {
                                    window.location.href = '/login';
                                    return;
                                }
                                if (seller?.id && auth.user.id === seller.id) return;
                                if (!seller?.id) return;
                                router.post(route('messages.open'), {
                                    type: 'seller_shop',
                                    seller_id: seller.id,
                                });
                            }}
                        >
                            <span className="seller__message-icon">
                                <img src="/img/products/reviews-icon.png" alt="Отзывы" />
                            </span>
                            Написать
                        </button>
                        <div className="seller__likes">
                            <span className="seller__likes-icon">❤️</span>
                            <span className="seller__likes-count">
                                {new Intl.NumberFormat('ru-RU').format(Number(seller?.likes ?? 0))}
                            </span>
                        </div>
                    </div>
                </div>
                <ProductsCatalog
                    dataProduct={products || []}
                    seller={seller}
                    isSellerProfile={true}
                    filters={filters || {}}
                    facets={facets}
                    total={total}
                />
            </div>
        </MainLayout>
    );
}
