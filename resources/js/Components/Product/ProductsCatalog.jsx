import React, { useState, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import ProductCard from '@/Components/Product/ProductCard';
import '../../../css/product/ShopPage.css';

export default function ProductsCatalog({
    dataProduct,
    category,
    seller,
    filters = {},
    isHomePage = false,
    isCategoryPage = false,
    isSellerProfile = false
}) {
    const initialProducts = dataProduct || [];
    const categoryName = category?.name || 'категории';
    const categoryId = category?.id;
    const sellerId = seller[0]?.user_id;
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const [sort, setSort] = useState(filters?.sort || 'new');
    const [priceFrom, setPriceFrom] = useState(filters?.price_from || '');
    const [priceTo, setPriceTo] = useState(filters?.price_to || '');

    const timeoutRef = useRef(null);

    // Синхронизация с пропсами
    useEffect(() => {
        setSearchTerm(filters?.search || '');
        setSort(filters?.sort || 'new');
        setPriceFrom(filters?.price_from || '');
        setPriceTo(filters?.price_to || '');
    }, [filters]);

    //  Исправленная функция applyFilters
    const applyFilters = () => {
        const params = {};

        if (searchTerm) params.search = searchTerm;
        if (sort && sort !== 'new') params.sort = sort;
        if (priceFrom && priceFrom !== '') params.price_from = priceFrom;
        if (priceTo && priceTo !== '') params.price_to = priceTo;

        //  Определяем маршрут в зависимости от страницы
        console.log("главная:" + isHomePage);
        console.log("isCategoryPage:" + isCategoryPage);
        console.log("isSellerProfile:" + isSellerProfile);
        if (isHomePage) {
            console.log("катайди: " + categoryId);
            // Главная страница
            router.get(
                '/',
                params,
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['mysqlNftsData', 'search', 'sort', 'filters']
                }
            );
        } else if (isCategoryPage) {
            // Страница категории
            router.get(
                route('category.show', categoryId),
                params,
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['products', 'filters'],
                }
            );
        }
        else if (isSellerProfile) {
            // Страница Продавца
            console.log(1112121212);
            router.get(
                route('seller.index', sellerId),
                params,
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,

                    only: ['products', 'filters'],
                }
            );
        }
    };

    const clearSearch = () => {
        setSearchTerm('');
    };

    // Debounce для фильтров
    useEffect(() => {
        if (timeoutRef.current) clearTimeout(timeoutRef.current);

        timeoutRef.current = setTimeout(() => {
            applyFilters();
        }, 500);

        return () => clearTimeout(timeoutRef.current);
    }, [sort, priceFrom, priceTo, searchTerm]);

    const clearAllFilters = () => {
        setSort('new');
        setPriceFrom('');
        setPriceTo('');
        setSearchTerm('');

        if (isHomePage) {
            router.get(
                '/',
                {},
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                }
            );
        } else if (isCategoryPage && !categoryId) {
            router.get(
                route('category.show', categoryId),
                {},
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                }
            );
        }
        else if (isSellerProfile && !sellerId) {
            // Страница Продавца
            router.get(
                route('sellerProfile', sellerId),
                params,
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                }
            );
        }
    };

    return (
        <div className="category-page container">
            <div className="shop-layout">
                <div className="container shop-layout__inner">
                    <aside className="shop-sidebar">
                        <div className="shop-sidebar__content">
                            <div className="shop-sidebar__header">
                                <h2 className="shop-sidebar__title">Фильтры</h2>
                            </div>

                            <div className="shop-sidebar__filter-group">
                                <label className="shop-sidebar__filter-label">Сортировка</label>
                                <select
                                    value={sort}
                                    onChange={(e) => setSort(e.target.value)}
                                    className="shop-sidebar__select"
                                >
                                    <option value="new">Новинки</option>
                                    <option value="cheap">Сначала дешевле</option>
                                    <option value="expensive">Сначала дороже</option>
                                </select>
                            </div>

                            <div className="shop-sidebar__filter-group">
                                <label className="shop-sidebar__filter-label">Цена</label>
                                <div className="shop-sidebar__price-range">
                                    <input
                                        type="number"
                                        placeholder="От"
                                        value={priceFrom}
                                        onChange={(e) => setPriceFrom(e.target.value)}
                                        min="0"
                                        className="shop-sidebar__price-input"
                                    />
                                    <input
                                        type="number"
                                        placeholder="До"
                                        value={priceTo}
                                        onChange={(e) => setPriceTo(e.target.value)}
                                        min="0"
                                        className="shop-sidebar__price-input"
                                    />
                                </div>
                            </div>
                        </div>
                    </aside>

                    <main className="shop-products">
                        {searchTerm && (
                            <div className="shop-products__search-info">
                                <span>
                                    Результат поиска: <strong>"{searchTerm}"</strong>
                                    {initialProducts.length > 0 && ` — найдено ${initialProducts.length} товаров`}
                                </span>
                                <button onClick={clearSearch} className="shop-products__search-clear">
                                    Очистить поиск
                                </button>
                            </div>
                        )}

                        {initialProducts.length > 0 ? (
                            <section className="Category_product">
                                <div className="container">
                                    <div className="products__grid">
                                        {initialProducts.map(product => (
                                            <ProductCard key={product.id} product={product} />
                                        ))}
                                    </div>
                                </div>
                            </section>
                        ) : (
                            <div className="shop-products__empty">
                                <p>
                                    {searchTerm
                                        ? `Товаров по запросу "${searchTerm}" не найдено`
                                        : `Пока нет товаров в категории ${categoryName}`
                                    }
                                </p>
                                {(searchTerm || sort !== 'new' || priceFrom || priceTo) && (
                                    <button onClick={clearAllFilters} className="shop-products__reset-btn">
                                        Сбросить фильтры
                                    </button>
                                )}
                            </div>
                        )}
                    </main>
                </div>
            </div>
        </div>
    );
}