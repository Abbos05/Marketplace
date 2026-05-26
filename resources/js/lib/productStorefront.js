/** Подпись видимости товара в каталоге для админки. */
export function storefrontVisibility(product) {
    if (!product) {
        return { label: '—', className: 'adm-storefront--unknown' };
    }

    if (product.seller_can_publish === false || product.storefront_block_reason === 'seller_inactive') {
        return { label: 'Компания закрыта', className: 'adm-storefront--off' };
    }

    if (product.status !== 'approved') {
        return { label: 'Не на витрине', className: 'adm-storefront--off' };
    }

    if (product.is_on_action === false || product.is_on_action === 0) {
        return { label: 'Снят с витрины', className: 'adm-storefront--off' };
    }

    if (product.catalog_visible === false) {
        return { label: 'Снят с витрины', className: 'adm-storefront--off' };
    }

    return { label: 'На витрине', className: 'adm-storefront--on' };
}

export function productAdminHref(productId) {
    return `/product/${productId}`;
}
