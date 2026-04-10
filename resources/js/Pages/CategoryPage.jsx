import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../css/product/CategoryPage.css';
import ProductsCatalog from '@/Components/Product/ProductsCatalog';
import LikeProductsCom from '@/components/Product/TopNFTs';

export default function CategoryPage({ auth }) {
    const { category, products, filters, LikeProducts } = usePage().props;

    // Используем хук состояния для хранения количества отображаемых элементов
    const [displayCount, setDisplayCount] = useState(5);

    // Функция для увеличения количества отображаемых элементов
    const showMore = () => {
        // Увеличиваем количество на 10, но не больше, чем длина массива LikeProducts
        setDisplayCount(prevCount => Math.min(prevCount + 10, LikeProducts.length));
    };

    return (
        <MainLayout auth={auth}>
            <Head title={`${category?.name || 'Категория'} - Маркетплейс`} />
            <div className='container'>
                <section className="category-header container">
                    <div className="category-header__actions">
                        <a
                            className="category-header__nav-btn"
                            onClick={() => {
                                router.visit('/category', {
                                    preserveState: true,
                                });
                            }}
                        >
                            Категории
                        </a>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" class="gd6_11"><path fill="currentColor" d="M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8"></path></svg>
                        <a className='category-header__nav-btn'>{category.name}</a>
                    </div>
                </section>
                <ProductsCatalog
                    dataProduct={products || []}
                    category={category}
                    filters={filters || {}}
                />
                {LikeProducts && LikeProducts.length > 0 && (
                    <>
                        <section className="category-header">
                            <h2>Возможно, вам понравится</h2>
                        </section>
                        {/* Передаём только первые `displayCount` элементов */}
                        <LikeProductsCom
                            key={LikeProducts.id}
                            nftsData={LikeProducts.slice(0, displayCount)}
                        />
                        {/* Кнопка показывается только если есть еще элементы для отображения */}
                        {displayCount < LikeProducts.length && (
                            <button className="showMore__btn" onClick={showMore}>Показать еще</button>
                        )}
                    </>
                )}
            </div>
        </MainLayout>
    );
}
