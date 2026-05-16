import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import Slider from '@/Components/Slider/Slider';
import ProductPage from '@/Components/Product/ProductPage';
import ProductsCatalog from '@/Components/Product/FilterProducts';
import '../../css/Home.css';
import LikeProductsCom from '@/Components/Product/ProductPage';

export default function Home({ auth, categoryData, showModal, homeSlides = [] }) {
    const {
        mysqlNftsData,
        search = '',
        filters = {},
        facets = {},
        total = 0,
        LikeProducts,
    } = usePage().props;

    return (
        <MainLayout auth={auth} categories={categoryData} showModal={showModal}>
            <Head title="Home" />

            {search ? (
                <div className="nfts_block">
                    <ProductsCatalog
                        dataProduct={mysqlNftsData}
                        category={{ name: 'Результаты поиска', id: null }}
                        filters={{ ...filters, search }}
                        facets={facets}
                        total={total}
                        isHomePage={true}
                    />
                    {LikeProducts && LikeProducts.length > 0 && LikeProductsCom && (
                        <>
                            <h2 className="info_dop">Возможно, вам понравится</h2>
                            <LikeProductsCom products={LikeProducts} />
                        </>
                    )}
                </div>
            ) : (
                <>
                    <Slider slides={homeSlides} />
                    <ProductPage products={mysqlNftsData} />
                </>
            )}
        </MainLayout>
    );
}
