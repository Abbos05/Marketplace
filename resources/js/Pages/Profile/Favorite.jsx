import React, { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import FavoriteProduct from '@/Components/Product/ProductCard';
import '../../../css/favorite/favorite.css';
import Recommendations from '@/Components/Product/ProductPage';
import { expandCatalogProductRows } from '@/lib/catalogListing';

export default function Favorite({ auth, product}) {
    const initialProducts = useMemo(() => expandCatalogProductRows(product), [product]);
        const { LikeProducts } = usePage().props;
    console.log(LikeProducts);
    
  // Используем хук состояния для хранения количества отображаемых элементов
    const [displayCount, setDisplayCount] = useState(5);
    // Функция для увеличения количества отображаемых элементов
    const showMore = () => {
        // Увеличиваем количество на 10, но не больше, чем длина массива LikeProducts
        setDisplayCount(prevCount => Math.min(prevCount + 10, LikeProducts.length));
    };
    return (
        <MainLayout>
            <Head title="Избранное" />

            <div className="favorite">
                <h2 className='favorite__title'>Избранное</h2>
                    {initialProducts.length > 0 ? (
                        <div className="products__grid">
                            {initialProducts.map(product => (
                                <FavoriteProduct key={product.listing_key ?? product.id} product={product} />
                            ))}
                        </div>
                    ) : (
                        <div className="NoFeatured">
                            <img className='NoFeatured__image' src="./img/favorites/NoFeatured.png" alt="NoFeatured" />
                            <p className='NoFeatured__title'>В избранное пока пусто</p>
                            <p className="NoFeatured__text">Добавляйте товары с помощью ❤️‍, чтобы не потерять их и купить позже</p>
                        </div>
                    )}
            </div>
            {LikeProducts && LikeProducts.length > 0 && (
                <>

                    <section className="category-header">
                        <h2>Возможно, вам понравится</h2>
                    </section>
                    {/* Передаём только первые `displayCount` элементов */}
                    <Recommendations
                        products={LikeProducts.slice(0, displayCount)}
                    />
                    {/* Кнопка показывается только если есть еще элементы для отображения */}
                    {displayCount < LikeProducts.length && (
                        <button className="showMore__btn" onClick={showMore}>Показать еще</button>
                    )}
                </>
            )}
        </MainLayout>
    );
}