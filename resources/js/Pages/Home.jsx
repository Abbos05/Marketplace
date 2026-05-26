import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import Slider from '@/Components/Slider/Slider';
import ProductPage from '@/Components/Product/ProductPage';

import ProductsCatalog from '@/Components/Product/FilterProducts';
import '../../css/Home.css';
import ProductRecommendationsSection from '@/Components/Product/ProductRecommendationsSection';

export default function Home({ auth, categoryData, showModal, homeSlides = [] }) {
    const {
        mysqlProductsData,
        search = '',
        filters = {},
        initialCount = 50,
        step = 30,
        maxCount,
        facets = {},
        total = 0,
        pagination = null,
        LikeProducts,
    } = usePage().props;

    const [displayCount, setDisplayCount] = useState(initialCount);
    
    const cap = maxCount != null ? Math.min(maxCount, mysqlProductsData.length) : mysqlProductsData.length;
    const visible = mysqlProductsData.slice(0, Math.min(displayCount, cap));
    const canShowMore = visible.length < cap;

    const showMore = () => {
        setDisplayCount((prev) => Math.min(prev + step, cap));
    };
    return (
        <MainLayout auth={auth} categories={categoryData} showModal={showModal}>
            <Head title="Home" />

            {search ? (
                <>
                    <ProductsCatalog
                        dataProduct={mysqlProductsData}
                        category={{ name: 'Результаты поиска', id: null }}
                        filters={{ ...filters, search }}
                        facets={facets}
                        total={total}
                        pagination={pagination}
                        isHomePage={true}
                    />
                    <ProductRecommendationsSection
                        products={LikeProducts}
                        titleClassName="info_dop"
                    />
                </>
            ) : (
                <>
                    {homeSlides.length > 0 && (
                                          <Slider slides={homeSlides} />

                    )}
                    <ProductPage products={visible} />
                    {canShowMore && (
                <button type="button" className="showMore__btn" onClick={showMore}>
                    Показать еще
                </button>
            )}
                </>
            )}
        </MainLayout>
    );
}
