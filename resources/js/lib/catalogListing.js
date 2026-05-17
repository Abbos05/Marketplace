function pickDisplayVariant(variants, productId) {
    if (!variants?.length) {
        return null;
    }
    if (variants.length === 1) {
        return variants[0];
    }
    const seed = Math.abs(Number(productId) || 0) * 2654435761;
    return variants[seed % variants.length];
}

/**
 * Разворачивает товары каталога в отдельные строки по активным вариантам
 * (одна карточка = один вариант: своя цена, картинка, ссылка ?variant=).
 *
 * @param {object[]} products
 * @param {{ oneVariantPerProduct?: boolean }} [options]
 */
export function expandCatalogProductRows(products, options = {}) {
    const { oneVariantPerProduct = false } = options;

    if (!Array.isArray(products)) {
        return [];
    }

    return products.flatMap((p) => {
        const cat = Array.isArray(p.variants_catalog) ? p.variants_catalog : [];
        if (cat.length === 0) {
            return [
                {
                    ...p,
                    listing_key: String(p.id),
                    listing_variant_id: null,
                },
            ];
        }

        const favoriteVariantIds = Array.isArray(p.favorite_variant_ids)
            ? p.favorite_variant_ids.map((id) => Number(id))
            : [];
        const useProductFavoriteForVariants =
            p.favorite_only && favoriteVariantIds.length === 0 && !!p.is_favorite;
        const visibleVariants = p.favorite_only && favoriteVariantIds.length > 0
            ? cat.filter((v) => favoriteVariantIds.includes(Number(v.id)))
            : cat;

        const variantsToShow = oneVariantPerProduct
            ? [pickDisplayVariant(visibleVariants, p.id)].filter(Boolean)
            : visibleVariants;

        return variantsToShow.map((v) => ({
            ...p,
            listing_key: `${p.id}-${v.id}`,
            listing_variant_id: v.id,
            image: v.image ?? p.image,
            price: v.price,
            old_price: v.old_price,
            discount_percent: v.discount_percent,
            is_favorite: favoriteVariantIds.includes(Number(v.id)) || useProductFavoriteForVariants,
            title:
                cat.length > 1 && v.label
                    ? `${p.title || 'Товар'} ${v.label}`
                    : p.title || 'Товар',
        }));
    });
}
