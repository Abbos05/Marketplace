import React, { useState, useEffect, useRef } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../css/product/ShopPage.css';
import ProductCard from '@/Components/Product/ProductCard'; // Переименовал в ProductCard
import Slider from '@/Components/Slider/Slider';
import LikeProductsCom from '@/components/Product/TopNFTs';


export default function CategoryPage({ auth }) {
    // Достаём props от Laravel
    const { category, products, filters, LikeProducts } = usePage().props;

    // Если products не пришёл, используем пустой массив
    const initialProducts = products || [];
    // Локальные состояния
    const [sort, setSort] = useState(filters?.sort || 'new');
    const [priceFrom, setPriceFrom] = useState(filters?.price_from || '');
    const [priceTo, setPriceTo] = useState(filters?.price_to || '');
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');

    const timeoutRef = useRef(null);

    // Применение фильтров с debounce
    const applyFilters = () => {
        // Формируем параметры фильтрации
        const params = {};

        if (searchTerm) params.search = searchTerm;
        if (sort && sort !== 'new') params.sort = sort;
        if (priceFrom && priceFrom !== '') params.price_from = priceFrom;
        if (priceTo && priceTo !== '') params.price_to = priceTo;

        router.get(
            route('category.show', category.id),
            params,
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['products', 'filters'], // Обновляем только нужные данные
            }
        );
    };

    // Debounce для фильтров
    useEffect(() => {
        if (timeoutRef.current) clearTimeout(timeoutRef.current);

        timeoutRef.current = setTimeout(() => {
            applyFilters();
        }, 500); // 500ms debounce

        return () => clearTimeout(timeoutRef.current);
    }, [sort, priceFrom, priceTo, searchTerm]);

    // Очистка всех фильтров
    const clearAllFilters = () => {
        setSort('new');
        setPriceFrom('');
        setPriceTo('');
        setSearchTerm('');

        // Опционально: сразу применить очистку
        router.get(
            route('category.show', category.id),
            {},
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            }
        );
    };

    // Сброс поиска
    const clearSearch = () => {
        setSearchTerm('');
    };

    return (
        <MainLayout auth={auth}>
            <Head title={`${category.name} - Маркетплейс`} />
            <div className="category-page container">
                {/* Верхняя секция с информацией о категории 
                    <section className="category-header">
                        <div className="category-header__content">
                            <div className="category-header__image-wrapper">
                                <img 
                                    width={120} 
                                    height={120} 
                                    className="category-header__avatar" 
                                    src={category.img || '/img/profiles/default-avatar.png'} 
                                    alt={category.name} 
                                />
                                <div className="category-header__actions">
                                    <button
                                        className="category-header__nav-btn"
                                        onClick={() => {
                                            router.visit('/', {
                                                preserveState: true,
                                                preserveScroll: false,
                                                onSuccess: () => {
                                                    setTimeout(() => {
                                                        document.getElementById('category-block')?.scrollIntoView({ behavior: 'smooth' });
                                                    }, 50);
                                                },
                                            });
                                        }}
                                    >
                                        <svg width="23" height="16" viewBox="0 0 23 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M0.292892 8.70711C-0.0976315 8.31658 -0.0976315 7.68342 0.292892 7.29289L6.65685 0.928932C7.04738 0.538408 7.68054 0.538408 8.07107 0.928932C8.46159 1.31946 8.46159 1.95262 8.07107 2.34315L2.41421 8L8.07107 13.6569C8.46159 14.0474 8.46159 14.6805 8.07107 15.0711C7.68054 15.4616 7.04738 15.4616 6.65685 15.0711L0.292892 8.70711ZM23 8V9H1V8V7H23V8Z" fill="white"/>
                                        </svg>
                                    </button>
                                    <button 
                                        className="category-header__share-btn"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            navigator.clipboard.writeText(window.location.href);
                                            alert('Ссылка скопирована!');
                                        }}
                                    >
                                        <svg width="16" height="22" viewBox="0 0 16 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 4L10.58 5.42L8.99 3.83V15H7.01V3.83L5.42 5.42L4 4L8 0L12 4ZM16 9V20C16 21.1 15.1 22 14 22H2C0.89 22 0 21.1 0 20V9C0 7.89 0.89 7 2 7H5V9H2V20H14V9H11V7H14C15.1 7 16 7.89 16 9Z" fill="white"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <h1 className="category-header__title">{category.name}</h1>
                            <p className="category-header__description">
                                {category.description ?? `Товары из категории ${category.name}`}
                            </p>
                        </div>
                    </section>
                    */}
                <section className="category-header">
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
                <Slider />

                {/* Основной блок с фильтром и товарами */}
                <div className="shop-layout">
                    <div className="container shop-layout__inner">

                        {/* Левый сайдбар с фильтрами */}
                        <aside className="shop-sidebar">
                            <div className="shop-sidebar__content">
                                <div className="shop-sidebar__header">
                                    <h2 className="shop-sidebar__title">Фильтры</h2>
                                    {(sort !== 'new' || priceFrom || priceTo || searchTerm) && (
                                        <button
                                            onClick={clearAllFilters}
                                            className="shop-sidebar__reset"
                                        >
                                            Сбросить все
                                        </button>
                                    )}
                                </div>

                                {/* Поиск */}
                                <div className="shop-sidebar__filter-group">
                                    <label className="shop-sidebar__filter-label">Поиск</label>
                                    <input
                                        type="text"
                                        placeholder="Поиск товаров..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="shop-sidebar__search-input"
                                    />
                                    {searchTerm && (
                                        <button
                                            onClick={clearSearch}
                                            className="shop-sidebar__clear-btn"
                                        >
                                            Очистить
                                        </button>
                                    )}
                                </div>

                                {/* Сортировка */}
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

                                {/* Ценовой диапазон */}
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

                        {/* Правая часть с товарами */}
                        <main className="shop-products">
                            {/* Информация о поиске */}
                            {searchTerm && (
                                <div className="shop-products__search-info">
                                    <span>
                                        Результат поиска: <strong>"{searchTerm}"</strong>
                                        {initialProducts.length > 0 && ` — найдено ${initialProducts.length} товаров`}
                                    </span>
                                    <button
                                        onClick={clearSearch}
                                        className="shop-products__search-clear"
                                    >
                                        Очистить поиск
                                    </button>
                                </div>
                            )}

                            {/* Список товаров */}
                            {initialProducts.length > 0 ? (

                                <>
                                    <section className="Category_product">
                                        <div className="container">
                                            <div className="products__grid">
                                                {initialProducts.map(dataProduct => (
                                                    <ProductCard key={dataProduct.id} product={dataProduct} />
                                                ))}
                                            </div>
                                        </div>
                                    </section>
                                </>
                            ) : (
                                <div className="shop-products__empty">
                                    <p>
                                        Пока нет товаров в категории <strong>{category.name}</strong>
                                        {searchTerm && ` по запросу "${searchTerm}"`}
                                    </p>
                                    {(searchTerm || sort !== 'new' || priceFrom || priceTo) && (
                                        <button
                                            onClick={clearAllFilters}
                                            className="shop-products__reset-btn"
                                        >
                                            Сбросить фильтры
                                        </button>
                                    )}
                                </div>
                            )}
                        </main>
                    </div>
                    <section className="category-header">
                        <h2>Возможно, вам понравиться</h2>
                    </section>
                    <LikeProductsCom key={LikeProducts.id} nftsData={LikeProducts} />
                </div>
            </div>

        </MainLayout >
    );
}