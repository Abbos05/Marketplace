import React, { useState, useEffect } from 'react';
import { Head, router, Link, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import Slider from '@/Components/Slider/Slider';
import ProductPage from '@/Components/Product/ProductPage';
import ProductsCatalog from '@/Components/Product/FilterProducts';
import '../../css/Home.css';
import LikeProductsCom from '@/Components/Product/ProductPage';

export default function Home({ auth, categoryData, showModal }) {
  const { mysqlNftsData, search = '', sort = 'price_desc', filters = {}, LikeProducts } = usePage().props;
  const [sortBy, setSortBy] = useState(sort);

  const { category } = usePage().props;

  useEffect(() => {
    setSortBy(sort);
  }, [sort]);

  const handleSortChange = (e) => {
    const newSort = e.target.value;
    setSortBy(newSort);

    router.get('/',
      {
        search: search || undefined,
        sort: newSort
      },
      {
        preserveState: true,
        preserveScroll: true,
        only: ['mysqlNftsData', 'search', 'sort', 'filters']
      }
    );
  };

  return (
    <MainLayout auth={auth} categories={categoryData} showModal={showModal}>
      <Head title="Home" />

      {search ? (
        <div className="nfts_block">

          <ProductsCatalog
            dataProduct={mysqlNftsData}
            category={{ name: 'Результаты поиска', id: null }}
            filters={{ ...filters, search: search, sort: sortBy }}
            isHomePage={true}
          />
            {LikeProducts && LikeProducts.length > 0 && LikeProductsCom && (
                <>
                    <h2 className='info_dop'>Возможно, вам понравится</h2>
                    <LikeProductsCom products={LikeProducts} />
                </>
            )}

        </div>
      ) : (
        <>
          <Slider />
          <ProductPage key={mysqlNftsData.id} products={mysqlNftsData} />
        </>
      )}
    </MainLayout>
  );
}