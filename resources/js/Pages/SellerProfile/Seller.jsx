import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/product/ShopPage.css';
import ProductsCatalog from '@/Components/Product/FilterProducts';

export default function Seller({ auth }) {

    const { seller, products, filters } = usePage().props;

    // Используем хук состояния для хранения количества отображаемых элементов
    const [displayCount, setDisplayCount] = useState(12);
    // Функция для увеличения количества отображаемых элементов
    const showMore = () => {
        // Увеличиваем количество на 10, но не больше, чем длина массива products
        setDisplayCount(prevCount => Math.min(prevCount + 10, products.length));
    };

    return (
        <MainLayout auth={auth}>
            <Head title={`${seller?.name || 'Страница Продавца'} - Маркетплейс`} />
            <div className='seller'>
                <div className="seller__inner">
                    <div className="seller__main">
                        {/* Блок с аватаром */}
                        <div className="seller__avatar">
                            {seller?.img ? (
                                <img src={products?.img || "/img/products/default.jpg"} onClick={() => { window.open(products?.img || "/img/products/default.jpg", "_blank"); }} alt={`${seller.name}'s avatar`} className="seller__avatar-img" />
                            ) : (
                                <div className="seller__avatar-placeholder">
                                    <span>{seller?.name?.charAt(0) || 'A'}</span>
                                </div>
                            )}
                        </div>

                        {/* Блок с информацией о продавце */}
                        <div className="seller__info">
                            <h2 className="seller__name ">{seller?.name || "LabuMarket"}</h2>
                            <div className="seller__details">
                                <div className="seller__shop seller__details--shop">
                                    <span className="seller__shop-value">Магазин</span>
                                </div>
                                <div className="seller__stat seller__stat--rating">
                                    <svg width="16px" height="16px" viewBox="0 -0.5 33 33" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns: xlink="http://www.w3.org/1999/xlink">
                                        <g id="Vivid.JS" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <g id="Vivid-Icons" transform="translate(-903.000000, -411.000000)" fill="#000">
                                                <g id="Icons" transform="translate(37.000000, 169.000000)">
                                                    <g id="star" transform="translate(858.000000, 234.000000)">
                                                        <g transform="translate(7.000000, 8.000000)" id="Shape">
                                                            <polygon points="27.865 31.83 17.615 26.209 7.462 32.009 9.553 20.362 0.99 12.335 12.532 10.758 17.394 0 22.436 10.672 34 12.047 25.574 20.22">
                                                            </polygon>
                                                        </g>
                                                    </g>
                                                </g>
                                            </g>
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

                    {/* Блок с действиями */}
                    <div className="seller__actions">
                        <button className="seller__button seller__button--message">
                            <span className="seller__message-icon">
                                <img src="/img/products/reviews-icon.png" alt="Отзывы" />
                            </span>
                            Написать</button>
                        <div className="seller__likes">
                            <span className="seller__likes-icon">❤️</span>
                            <span className="seller__likes-count">{seller?.likes ?? "1 000"}</span>
                        </div>
                    </div>
                </div>
                <ProductsCatalog
                    dataProduct={products.slice(0, displayCount) || []}
                    seller={seller}
                    isSellerProfile={true}
                    filters={filters || {}}
                />
                {displayCount < products.length && (
                    <button className="showMore__btn showMore__btn--active" onClick={showMore}>Показать еще</button>
                )}
            </div>
        </MainLayout >
    );
}
