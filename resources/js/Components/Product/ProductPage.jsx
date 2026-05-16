import React, { useMemo } from 'react';
import ProductCard from '@/Components/Product/ProductCard';
import { expandCatalogProductRows } from '@/lib/catalogListing';
import '../../../css/product/product.css';

const ProductPage = ({ products = [] }) => {
    const rows = useMemo(() => expandCatalogProductRows(products), [products]);

    if (rows.length === 0) {
        return (
            <section className="top_nfts">
                <div className="container" style={{ color: 'black', fontSize: 24 }}>
                    Пока нечего нету
                </div>
            </section>
        );
    }

    return (
        <section className="products">
            <div className="products__grid">
                {rows.map((product) => (
                    <ProductCard key={product.listing_key} product={product} hideFooter />
                ))}
            </div>
        </section>
    );
};

export default ProductPage;
