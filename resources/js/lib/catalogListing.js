/**
 * Разворачивает товары каталога в отдельные строки по активным вариантам
 * (одна карточка = один вариант: своя цена, картинка, ссылка ?variant=).
 */
export function expandCatalogProductRows(products) {
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

        return visibleVariants.map((v) => ({
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
                    ? `${p.title || 'Товар'} · ${v.label}`
                    : p.title || 'Товар',
        }));
    });
}
