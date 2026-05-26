import React, { useState } from 'react';
import Recommendations from '@/Components/Product/ProductPage';
import '../../../css/product/ShopPage.css';

export default function ProductRecommendationsSection({
    products = [],
    title = 'Возможно, вам понравится',
    initialCount = 5,
    step = 10,
    maxCount,
    headingClassName = 'category-header',
    titleClassName = '',
    wrapperClassName = '',
}) {
    const [displayCount, setDisplayCount] = useState(initialCount);

    if (!products?.length) {
        return null;
    }

    const cap = maxCount != null ? Math.min(maxCount, products.length) : products.length;
    const visible = products.slice(0, Math.min(displayCount, cap));
    const canShowMore = visible.length < cap;

    const showMore = () => {
        setDisplayCount((prev) => Math.min(prev + step, cap));
    };

    const heading = title ? (
        titleClassName ? (
            <h2 className={titleClassName}>{title}</h2>
        ) : (
            <section className={headingClassName}>
                <h2>{title}</h2>
            </section>
        )
    ) : null;

    return (
        <div className={wrapperClassName || undefined}>
            {heading}
            <Recommendations products={visible} />
            {canShowMore && (
                <button type="button" className="showMore__btn" onClick={showMore}>
                    Показать еще
                </button>
            )}
        </div>
    );
}
