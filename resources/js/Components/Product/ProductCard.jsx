    // src/components/NFTCard.jsx
    import React from 'react';
    import { useState, useEffect } from 'react';
    import { router } from '@inertiajs/react';
    import '../../../css/product/product.css';

    export default function ProductCard({ product }) {
        if (!product) return;
        const [isFavorite, setIsFavorite] = useState(product.is_favorite);
        const [isToggling, setIsToggling] = useState(false);


        // Следим за изменением пропсов
        useEffect(() => {
            setIsFavorite(product.is_favorite);
        }, [product.is_favorite]);

        const toggleFavorite = () => {
            // Защита от спама
            if (isToggling) return;
            
            if (!product?.id) return;
            
            const previousValue = isFavorite;
            const newValue = !isFavorite;
            
            setIsToggling(true);
            setIsFavorite(newValue);
            
            router.post(route('favorites.toggle', product.id), {}, {
                preserveState: true,
                preserveScroll: true,
                onError: () => {
                    setIsFavorite(previousValue);
                    alert('Ошибка, попробуйте позже');
                },
                onFinish: () => {
                    setIsToggling(false);
                }
            });
        };
        return (

            <div
                className="products__card"
            >
                <div className="products__image-wrapper">
                    <div className="products__image-float"
                        key={product.id}
                        onClick={() => router.visit(`/product/${product.id}`)}>
                        <img
                            src={product.image ?? '/img/products/default.jpg'}
                            alt={`Product: ${product.title}`}
                            className="products__image"

                        />
                        <div className="product__img-stock">
                            <div className="product__stock__item">
                                <p>Акция наnhfdsghsdgf товар</p>
                            </div>
                            <div className="product__stock__item">
                                <p>Акция на товар</p>
                            </div>
                        </div>
                    </div>
                    <svg onClick={() => toggleFavorite()} className={isFavorite ? 'active' : 'noActive'} id={`favorite-icon-${product.id}`} width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 5C5.93913 5 4.92172 5.42143 4.17157 6.17157C3.42143 6.92172 3 7.93913 3 9C3 12.552 5.218 15.296 7.621 17.22C8.96786 18.2885 10.438 19.1916 12 19.91C13.5608 19.1907 15.0302 18.2876 16.377 17.22C18.78 15.294 21 12.551 21 9C21 7.93913 20.5786 6.92172 19.8284 6.17157C19.0783 5.42143 18.0609 5 17 5C15.043 5 13.348 6.396 12.98 8.2C12.9341 8.42606 12.8115 8.62929 12.6329 8.77528C12.4542 8.92126 12.2307 9.001 12 9.001C11.7693 9.001 11.5458 8.92126 11.3671 8.77528C11.1885 8.62929 11.0659 8.42606 11.02 8.2C10.652 6.396 8.957 5 7 5Z" fill="#FF0202" />
                        <path d="M12 22C11.684 21.98 11.44 21.853 11.152 21.722C9.44651 20.9359 7.84139 19.9482 6.371 18.78C3.777 16.705 1 13.449 1 9C1 7.4087 1.63214 5.88258 2.75736 4.75736C3.88258 3.63214 5.4087 3 7 3C7.97708 3.0023 8.9397 3.23625 9.80885 3.68265C10.678 4.12905 11.4289 4.77517 12 5.568C12.5711 4.77517 13.322 4.12905 14.1911 3.68265C15.0603 3.23625 16.0229 3.0023 17 3C18.5913 3 20.1174 3.63214 21.2426 4.75736C22.3679 5.88258 23 7.4087 23 9C23 13.448 20.22 16.705 17.625 18.78C16.1544 19.9473 14.5497 20.935 12.845 21.722C12.302 21.971 12.113 22 12 22ZM7 5C5.93913 5 4.92172 5.42143 4.17157 6.17157C3.42143 6.92172 3 7.93913 3 9C3 12.552 5.218 15.296 7.621 17.22C8.96786 18.2885 10.438 19.1916 12 19.91C13.5608 19.1907 15.0302 18.2876 16.377 17.22C18.78 15.294 21 12.551 21 9C21 7.93913 20.5786 6.92172 19.8284 6.17157C19.0783 5.42143 18.0609 5 17 5C15.043 5 13.348 6.396 12.98 8.2C12.9341 8.42606 12.8115 8.62929 12.6329 8.77528C12.4542 8.92126 12.2307 9.001 12 9.001C11.7693 9.001 11.5458 8.92126 11.3671 8.77528C11.1885 8.62929 11.0659 8.42606 11.02 8.2C10.652 6.396 8.957 5 7 5Z" fill="#FF0000" />
                    </svg>
                </div>

                <div className="products__info">

                    <div className="products__prices">
                        <div className="products__current-price">10 000₽</div>
                        {!product.oldPrice && (
                            <div className="products__old-price">
                                <sub><span>{product.oldPrice ?? '20 000'}₽</span>
                                    <span className="products__discount">-{product.discoun ?? 50}%</span></sub>
                            </div>
                        )}
                    </div>
                    {product.filltr !== undefined ? '' :
                        <>
                            <div className="products__stock">
                                <p>{product.stock || '100 шт осталось'}</p>
                            </div>

                            <div className="products__seller">
                                <span className="products__shop-name" title={product.verified ?? 'Доверительная компания'}>Name Shop
                                    {product.verified !== undefined ? '' : <span className="products__verified" title='Оригинал'> ✓</span>}</span>
                            </div>
                        </>
                    }
                    <div className="products__title">
                        <p className="products__title-text"
                            key={product.id}
                            onClick={() => router.visit(`/product/${product.id}`)}>
                            {product.title || 'Какое-то название товара'}
                        </p>
                    </div>
                    <div className="products__rating">
                        <div className="products__rating-stars">
                            <img src="/img/products/star-icon.png" alt="Рейтинг" />
                            <span>5.0</span>
                        </div>
                        <div className="products__reviews">
                            <img src="/img/products/reviews-icon.png" alt="Отзывы" />
                            <span>100 отзывов</span>
                        </div>
                    </div>
                    <div className="products__btn">
                        <button key={product.id}
                            onClick={() => router.visit(`/product/${product.id}`)}>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 16 16" class="products__basket"><path fill="currentColor" d="M10.19 1.393a.834.834 0 0 1 1.084.465L12.73 5.5h.842c.897 0 1.345 0 1.594.288s.185.732.056 1.618l-.227 1.554c-.396 2.721-.593 4.082-1.532 4.895s-2.314.812-5.064.812h-.8c-2.749 0-4.125 0-5.064-.812S1.4 11.68 1.003 8.96L.778 7.406c-.13-.886-.194-1.33.055-1.618.25-.288.698-.288 1.594-.288h.842l1.457-3.642a.834.834 0 0 1 1.548.618L5.064 5.5h5.872l-1.21-3.024a.834.834 0 0 1 .465-1.083m-3.857 7.44a.834.834 0 0 0-.833.834v1.666a.834.834 0 1 0 1.666 0V9.667a.834.834 0 0 0-.833-.834m3.333 0a.834.834 0 0 0-.833.834v1.666a.834.834 0 1 0 1.667 0V9.667a.834.834 0 0 0-.834-.834"></path></svg>
                            <p className='products__date-delivery'>Послезавтра</p>
                        </button>
                    </div>
                </div>
            </div>
        );
    };
