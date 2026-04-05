import React, { useState, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import ProductCard from '@/Components/Product/ProductCard'; // 👈 ЭТО СТРОКА БЫЛА ПРОПУЩЕНА



export default function ProductsCatalog({
    dataProduct,
    category,        
    filters = {}     
}) {
    // Не используйте usePage().props внутри ProductsCatalog!
    // Передавайте всё через пропсы из родителя
    
    const initialProducts = dataProduct || [];
    
    // Используем переданный category или fallback
    const categoryName = category?.name || 'категории';
    const categoryId = category?.id;
    
    const [sort, setSort] = useState(filters?.sort || 'new');
    const [priceFrom, setPriceFrom] = useState(filters?.price_from || '');
    const [priceTo, setPriceTo] = useState(filters?.price_to || '');
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');

    const timeoutRef = useRef(null);

    // Применение фильтров с debounce
    const applyFilters = () => {
        if (!categoryId) return; // Защита от отсутствия категории
        
        const params = {};

        if (searchTerm) params.search = searchTerm;
        if (sort && sort !== 'new') params.sort = sort;
        if (priceFrom && priceFrom !== '') params.price_from = priceFrom;
        if (priceTo && priceTo !== '') params.price_to = priceTo;

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
    };

    // Debounce для фильтров
    useEffect(() => {
        if (timeoutRef.current) clearTimeout(timeoutRef.current);

        timeoutRef.current = setTimeout(() => {
            applyFilters();
        }, 500);

        return () => clearTimeout(timeoutRef.current);
    }, [sort, priceFrom, priceTo, searchTerm]);

    // Очистка всех фильтров
    const clearAllFilters = () => {
        setSort('new');
        setPriceFrom('');
        setPriceTo('');
        setSearchTerm('');

        if (categoryId) {
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
    };

    const clearSearch = () => {
        setSearchTerm('');
    };
    
    return (
        <div className="category-page container">
         

            <div className="shop-layout">
                <div className="container shop-layout__inner">
                    <aside className="shop-sidebar">
                        <div className="shop-sidebar__content">
                            <div className="shop-sidebar__header">
                                <h2 className="shop-sidebar__title">Фильтры</h2>
                                {(sort !== 'new' || priceFrom || priceTo || searchTerm) && (
                                    <button onClick={clearAllFilters} className="shop-sidebar__reset">
                                        Сбросить все
                                    </button>
                                )}
                            </div>

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
                                    <button onClick={clearSearch} className="shop-sidebar__clear-btn">
                                        Очистить
                                    </button>
                                )}
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
                                    Пока нет товаров в категории <strong>{categoryName}</strong>
                                    {searchTerm && ` по запросу "${searchTerm}"`}
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