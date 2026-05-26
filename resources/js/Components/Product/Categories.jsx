// src/components/Category.jsx
import React from 'react';
import '../../../css/product/categories.css';
import { router } from '@inertiajs/react';

const Category = ({ categories = [] }) => {
    if (categories.length === 0) {
        return (
            <section className="category">
                <div className="container">Нет данных о категориях</div>
            </section>
        );
    }

    return (
        <section className="category">
                <div className="category_header" id="category-block">
                    <h2>
                        Просмотр по <span>каталогам</span>
                    </h2>
                </div>
                <div className="category_block">
                    {categories.map((category) => (
                        <div key={category.id} className="category_item" onClick={() => router.visit(`/category/${category.id}`)}>
                            <img
                                src={category.icon ?? '/img/products/default.png'}
                                alt={category.name}
                                className="category_image"
                            />
                            <p>{category.name}</p>
                        </div>
                    ))}
                </div>
        </section>
    );
};

export default Category;