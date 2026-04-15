import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/product/CategoryPage.css';
import ProductsCatalog from '@/Components/Product/ProductsCatalog';

export default function Seller({ auth }) {
    
    const { seller, products, filters } = usePage().props;
 
    // Используем хук состояния для хранения количества отображаемых элементов
    const [displayCount, setDisplayCount] = useState(5);
    // Функция для увеличения количества отображаемых элементов
    const showMore = () => {
        // Увеличиваем количество на 10, но не больше, чем длина массива products
        setDisplayCount(prevCount => Math.min(prevCount + 10, products.length));
    };

    return (
        <MainLayout auth={auth}>
            <Head title={`${seller?.name || 'Страница Продавца'} - Маркетплейс`} />
            <div className=''>

                <ProductsCatalog
                    dataProduct={products.slice(0, displayCount) || []}
                    seller={seller}
                    isSellerProfile={true}
                    filters={filters || {}}
                />
                {displayCount < products.length && (
                    <button className="showMore__btn" onClick={showMore}>Показать еще</button>
                )}
            </div>
        </MainLayout>
    );
}
