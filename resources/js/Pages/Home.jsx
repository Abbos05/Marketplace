import React, { useState, useEffect } from 'react';
import { Head, router, Link, usePage } from '@inertiajs/react';

import MainLayout from '@/Layouts/MainLayout';
import Slider from '@/Components/Slider/Slider';
import TopNFTs from '@/components/Product/TopNFTs';
import ProductsCatalog from '@/Components/Product/ProductsCatalog';

import '../../css/Home.css';
import ProductCard from '@/Components/Product/ProductCard';

export default function Home({
  auth,
  mysqlNftsData,
  bestNftsData,
  categoryData,
  showModal,
  search = '',
  sort = 'price_desc'
}) {
  const [sortBy, setSortBy] = useState(sort);

  const handleSortChange = (e) => {
    const newSort = e.target.value;
    setSortBy(newSort);
    router.visit('/', {
      data: { search: search || undefined, sort: newSort },
      preserveState: true,
      preserveScroll: true,
    });
  };

  const { category, filters } = usePage().props;
  
  return (
    <MainLayout auth={auth} categories={categoryData} showModal={showModal}>
      <Head title="Home" />
      {search && (
        <section className="search-results-header">
          <div className="container">
            <div className="search-header">
              <h2>Результаты по запросу: <span className="search-query">"{search}"</span></h2>
              <p>Найдено: <strong>{mysqlNftsData.length}</strong> товаров</p>

              <div className="search-header-content">
                <Link href="/" className="clear-search">Очистить поиск</Link>
                <div className="sort-filter">
                  <select value={sortBy} onChange={handleSortChange} className="sort-select">
                    <option value="price_desc">Дорогие сверху</option>
                    <option value="price_asc">Дешёвые сверху</option>
                    <option value="date_desc">Сначала новые</option>
                    <option value="date_asc">Сначала старые</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </section>
      )}

      {/* === ПОИСК === */}
      {search ? (
        <div className="nfts_block container">
          {mysqlNftsData.length > 0 ? (
                    <ProductsCatalog
                    dataProduct={mysqlNftsData || []}
                    category={category}
                    filters={filters || {}}
                />
          ) : (
            <p className="no-results">
              Ничего не найдено по запросу: <strong>"{search}"</strong>
              <br />
            </p>
          )}
        </div>
      ) : (
        <>
          <Slider />
          {/*   <section className="section-info">
            <div className="container">
              <h2 className="section-info-title">
                Находите, собирайте и продавайте <span>необычные NFT</span>
              </h2>
              <p className="section-info-description">AltChain — первая и крупнейшая в мире торговая площадка NFT</p>
              <div className="section-info-btn">
                <a  href='#howsell'>Подробнее</a>
                <Link href={route('nft.create')}>Добавить</Link>
              </div>
            </div>
      </section> */ }

          <TopNFTs nftsData={mysqlNftsData} />
          {    /*       <BestNFTs nftsData={bestNftsData} />  */}

        </>
      )}

      {/* <HowSellNFT /> */}
      {/* <Category categories={categoryData} /> */}
    </MainLayout>
  );
}