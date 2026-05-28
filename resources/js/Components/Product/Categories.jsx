// src/components/Category.jsx
import React from 'react';
import '../../../css/product/categories.css';
import { router } from '@inertiajs/react';
import CategoryIcon from '@/Components/Category/CategoryIcon';

const Category = ({ categories = [] }) => {
    if (categories.length === 0) {
        return (
            <section className="category">
                <div className="container">Нет данных о категориях</div>
            </section>
        );
    }

    return (
        <section className="category category--roots">
            <div className="category_header" id="category-block">
                <h2>
                    Просмотр по <span>каталогам</span>
                </h2>
            </div>
            <div className="category_block category_block--text">
                {categories.map((category) => (
                    <div
                        key={category.id}
                        className="category_item category_item--text"
                        onClick={() => router.visit(`/category/${category.id}`)}
                    >
                        <span className="category_item__leading" aria-hidden>
                            <CategoryIcon slug={category.slug} className="category-item-icon-svg" />
                        </span>
                        <p className="category_item__name">{category.name}</p>
                        <span className="category_item__arrow" aria-hidden>→</span>
                    </div>
                ))}
            </div>
        </section>
    );
};

export default Category;