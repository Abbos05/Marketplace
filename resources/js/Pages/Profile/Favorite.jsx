import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import FavoriteProduct from '@/Components/Product/ProductCard';
import ProductRecommendationsSection from '@/Components/Product/ProductRecommendationsSection';
import '../../../css/favorite/favorite.css';
import { expandCatalogProductRows } from '@/lib/catalogListing';

export default function Favorite({ auth, product, LikeProducts = [] }) {
    const initialProducts = useMemo(() => expandCatalogProductRows(product), [product]);
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
            <ProductRecommendationsSection products={LikeProducts} />
        </MainLayout>
    );
}