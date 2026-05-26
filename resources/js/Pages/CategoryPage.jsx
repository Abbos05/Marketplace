import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../css/product/ShopPage.css';
import '../../css/product/categories.css';
import ProductsCatalog from '@/Components/Product/FilterProducts';
import ProductRecommendationsSection from '@/Components/Product/ProductRecommendationsSection';

export default function CategoryPage({ auth }) {
  const {
    category,
    products,
    filters,
    facets = {},
    total = 0,
    pagination = null,
    LikeProducts,
    showSubcategories = false,
    subcategories = [],
    breadcrumbs = [],
  } = usePage().props;

  return (
    <MainLayout auth={auth}>
      <Head title={`${category?.name || 'Категория'} - Маркетплейс`} />
      <section className="category-header">
        <div className="category-header__actions">
          {breadcrumbs.length > 0
            ? breadcrumbs.map((crumb, i) => (
              <React.Fragment key={`${crumb.label}-${i}`}>
                {i > 0 && (
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    className="gd6_11"
                    aria-hidden
                  >
                    <path fill="currentColor" d="M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8" />
                  </svg>
                )}
                {crumb.href ? (
                  <Link href={crumb.href} className="category-header__nav-btn">
                    {crumb.label}
                  </Link>
                ) : (
                  <span className="category-header__nav-btn category-header__nav-btn--current">
                    {crumb.label}
                  </span>
                )}
              </React.Fragment>
            ))
            : null}
        </div>
      </section>

      {showSubcategories ? (
        <section className="category category--nested">
            <div className="category_header">
              <h2>
                Подкатегории <span>{category?.name}</span>
              </h2>
              <p className="category_header__hint">Выберите раздел, чтобы перейти к товарам</p>
            </div>
            <div className="category_block">
              {subcategories.map((sub) => (
                <div
                  key={sub.id}
                  className="category_item"
                  role="button"
                  tabIndex={0}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                      e.preventDefault();
                      router.visit(`/category/${sub.id}`);
                    }
                  }}
                  onClick={() => router.visit(`/category/${sub.id}`)}
                >
                  <img
                    src={sub.icon}
                    alt=""
                    className="category_image"
                  />
                  <p>{sub.name}</p>
                </div>
              ))}
            </div>
        </section>
      ) : (
        <ProductsCatalog
          dataProduct={products || []}
          category={category}
          filters={filters || {}}
          facets={facets}
          total={total}
          pagination={pagination}
          isCategoryPage={true}
        />
      )}

      <ProductRecommendationsSection products={LikeProducts} />
    </MainLayout>
  );
}
