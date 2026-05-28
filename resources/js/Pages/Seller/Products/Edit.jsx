import React, { useEffect, useMemo } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';
import '../../../../css/seller/create-product.css';

function getOptions(attr) {
    if (!attr.options) return [];
    if (Array.isArray(attr.options)) return attr.options;
    if (typeof attr.options === 'string') {
        try {
            const parsed = JSON.parse(attr.options);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    }
    return [];
}

function attrScope(attr) {
    return attr.applies_to ?? 'product';
}

const NEW_PRODUCT_DAYS = 30;

function variantsHaveDiscount(variants, initialVariants) {
    return variants.some((v, i) => {
        const price = parseFloat(v.price);
        if (Number.isNaN(price) || price <= 0) return false;
        const init = initialVariants[i];
        const oldFromInit = init?.old_price ? parseFloat(init.old_price) : null;
        if (oldFromInit && oldFromInit > price) return true;
        const initPrice = init?.price ? parseFloat(init.price) : null;
        if (initPrice && initPrice > price) return true;
        return false;
    });
}

function isNewProduct(createdAtIso) {
    if (!createdAtIso) return false;
    const created = new Date(createdAtIso);
    const limit = new Date();
    limit.setDate(limit.getDate() - NEW_PRODUCT_DAYS);
    return created >= limit;
}

export default function Edit({ product, leafCategory, parentCategory, initial }) {
    const { data, setData, post, processing, errors, transform } = useForm({
        title: initial.title,
        short_description: initial.short_description,
        description: initial.description,
        attributes: { ...initial.attributes },
        promotion: {
            enabled: initial.promotion?.enabled ?? false,
            badge_key: initial.promotion?.badge_key ?? '',
            ends_at: initial.promotion?.ends_at ?? '',
        },
        variants: initial.variants.map((v) => ({
            id: v.id,
            options: { ...(v.options || {}) },
            price: v.price,
            stock: v.stock,
            existingImages: (v.images ?? []).map((img) => ({
                ...img,
                key: `existing:${img.id}`,
            })),
            newImages: [],
            newImagePreviews: [],
            remove_image_ids: [],
            main_image_key: (() => {
                const main = (v.images ?? []).find((img) => img.is_main) ?? (v.images ?? [])[0];
                return main ? `existing:${main.id}` : 'new:0';
            })(),
        })),
    });

    /* Multipart + вложенные объекты: JSON-строки; маршрут — POST (см. web.php). */
    useEffect(() => {
        transform((d) => {
            const payload = {
                title: d.title,
                short_description: d.short_description,
                description: d.description,
                variants_json: JSON.stringify(
                    d.variants.map(
                        ({
                            newImages,
                            newImagePreviews,
                            existingImages,
                            ...v
                        }) => v,
                    ),
                ),
                attributes_json: JSON.stringify(d.attributes ?? {}),
                promotion_json: JSON.stringify(d.promotion ?? {}),
            };
            d.variants.forEach((v, i) => {
                (v.newImages ?? []).forEach((img, imgIndex) => {
                    payload[`variant_gallery_${i}_${imgIndex}`] = img;
                });
            });
            return payload;
        });
    }, [transform]);

    const productAttrs = useMemo(
        () => (leafCategory?.attributes ?? []).filter((a) => attrScope(a) !== 'variant'),
        [leafCategory],
    );

    const variantAttrs = useMemo(
        () => (leafCategory?.attributes ?? []).filter((a) => attrScope(a) === 'variant'),
        [leafCategory],
    );

    const hasVariants = variantAttrs.length > 0;

    const eligibleBadges = useMemo(() => {
        const list = [];
        if (variantsHaveDiscount(data.variants, initial.variants)) {
            list.push({ key: 'sale', label: 'Распродажа' });
        }
        if (isNewProduct(product?.created_at)) {
            list.push({ key: 'new', label: 'Новинка' });
        }
        return list;
    }, [data.variants, initial.variants, product?.created_at]);

    useEffect(() => {
        if (!data.promotion.enabled) return;
        const keys = eligibleBadges.map((b) => b.key);
        if (data.promotion.badge_key && !keys.includes(data.promotion.badge_key)) {
            setData('promotion', {
                ...data.promotion,
                badge_key: keys[0] ?? '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [eligibleBadges, data.promotion.enabled]);

    const updatePromotion = (field, value) => {
        setData('promotion', { ...data.promotion, [field]: value });
    };

    const handleAddPromotion = () => {
        setData('promotion', {
            enabled: true,
            badge_key: data.promotion.badge_key || eligibleBadges[0]?.key || '',
            ends_at: data.promotion.ends_at || '',
        });
    };

    const handleRemovePromotion = () => {
        setData('promotion', {
            enabled: false,
            badge_key: '',
            ends_at: '',
        });
    };

    const selectedPromoLabel =
        eligibleBadges.find((b) => b.key === data.promotion.badge_key)?.label ?? null;

    const priceChangeEnablesSale = useMemo(
        () => variantsHaveDiscount(data.variants, initial.variants),
        [data.variants, initial.variants],
    );

    const updateAttribute = (id, value) => {
        setData('attributes', {
            ...data.attributes,
            [id]: value,
        });
    };

    const addVariant = () => {
        setData('variants', [
            ...data.variants,
            {
                id: null,
                options: {},
                price: '',
                stock: '',
                existingImages: [],
                newImages: [],
                newImagePreviews: [],
                remove_image_ids: [],
                main_image_key: 'new:0',
            },
        ]);
    };

    const removeVariant = (index) => {
        if (data.variants.length <= 1) return;
        setData(
            'variants',
            data.variants.filter((_, i) => i !== index),
        );
    };

    const updateVariantField = (index, field, value) => {
        const variants = data.variants.map((v, i) => {
            if (i !== index) return v;
            if (field === 'newImages') {
                const files = Array.from(value || []).slice(0, 10);
                return {
                    ...v,
                    newImages: files,
                    newImagePreviews: files.map((file) => URL.createObjectURL(file)),
                };
            }
            return { ...v, [field]: value };
        });
        setData('variants', variants);
    };

    const updateVariantOption = (index, attrName, value) => {
        const variants = data.variants.map((v, i) => {
            if (i !== index) return v;
            return {
                ...v,
                options: { ...(v.options || {}), [attrName]: value },
            };
        });
        setData('variants', variants);
    };

    const toggleRemoveVariantImage = (variantIndex, imageId) => {
        const variants = data.variants.map((v, i) => {
            if (i !== variantIndex) return v;
            const removeIds = v.remove_image_ids.includes(imageId)
                ? v.remove_image_ids.filter((x) => x !== imageId)
                : [...v.remove_image_ids, imageId];
            const removedMain = v.main_image_key === `existing:${imageId}` && removeIds.includes(imageId);
            return {
                ...v,
                remove_image_ids: removeIds,
                main_image_key: removedMain ? (v.newImages?.length ? 'new:0' : v.main_image_key) : v.main_image_key,
            };
        });
        setData('variants', variants);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('seller.products.update', product.id), {
            forceFormData: true,
        });
    };

    const errorEntries = Object.entries(errors ?? {}).filter(
        ([k]) => k !== 'error',
    );

    const renderProductAttr = (attr) => {
        const options = getOptions(attr);
        const attrErr =
            errors[`attributes.${attr.id}`] ||
            (errors.attributes && errors.attributes[attr.id]);
        const val = data.attributes[attr.id];

        return (
            <div key={attr.id} className="form-group">
                <label>
                    {attr.name}
                    {attr.required && <span className="required">*</span>}
                </label>

                {attr.type === 'text' && (
                    <input
                        type="text"
                        value={val ?? ''}
                        className={attrErr ? 'error' : ''}
                        onChange={(e) => updateAttribute(attr.id, e.target.value)}
                    />
                )}

                {attr.type === 'number' && (
                    <input
                        type="number"
                        value={val ?? ''}
                        className={attrErr ? 'error' : ''}
                        onChange={(e) => updateAttribute(attr.id, e.target.value)}
                    />
                )}

                {attr.type === 'boolean' && (
                    <label className="checkbox-row">
                        <input
                            type="checkbox"
                            checked={val === true || val === '1' || val === 1}
                            onChange={(e) =>
                                updateAttribute(attr.id, e.target.checked ? '1' : '0')
                            }
                        />
                        <span>Да</span>
                    </label>
                )}

                {attr.type === 'select' && (
                    <select
                        value={val ?? ''}
                        className={attrErr ? 'error' : ''}
                        onChange={(e) => updateAttribute(attr.id, e.target.value)}
                    >
                        <option value="">Выберите</option>
                        {options.map((option) => (
                            <option key={option} value={option}>
                                {option}
                            </option>
                        ))}
                    </select>
                )}

                {attrErr && <div className="error-message">{attrErr}</div>}
            </div>
        );
    };

    const renderVariantAttr = (attr, variantIndex) => {
        const options = getOptions(attr);
        const errorKey = `variants.${variantIndex}.options.${attr.name}`;
        const val = data.variants[variantIndex]?.options?.[attr.name];

        return (
            <div key={attr.id} className="form-group">
                <label>
                    {attr.name}
                    {attr.required && <span className="required">*</span>}
                </label>

                {attr.type === 'text' && (
                    <input
                        type="text"
                        value={val ?? ''}
                        className={errors[errorKey] ? 'error' : ''}
                        onChange={(e) =>
                            updateVariantOption(variantIndex, attr.name, e.target.value)
                        }
                    />
                )}

                {attr.type === 'number' && (
                    <input
                        type="number"
                        value={val ?? ''}
                        className={errors[errorKey] ? 'error' : ''}
                        onChange={(e) =>
                            updateVariantOption(variantIndex, attr.name, e.target.value)
                        }
                    />
                )}

                {attr.type === 'boolean' && (
                    <label className="checkbox-row">
                        <input
                            type="checkbox"
                            checked={val === true || val === '1'}
                            onChange={(e) =>
                                updateVariantOption(
                                    variantIndex,
                                    attr.name,
                                    e.target.checked ? '1' : '0',
                                )
                            }
                        />
                        <span>Да</span>
                    </label>
                )}

                {attr.type === 'select' && (
                    <select
                        value={val ?? ''}
                        className={errors[errorKey] ? 'error' : ''}
                        onChange={(e) =>
                            updateVariantOption(variantIndex, attr.name, e.target.value)
                        }
                    >
                        <option value="">Выберите</option>
                        {options.map((option) => (
                            <option key={option} value={option}>
                                {option}
                            </option>
                        ))}
                    </select>
                )}

                {errors[errorKey] && <div className="error-message">{errors[errorKey]}</div>}
            </div>
        );
    };

    const statusLabels = {
        moderation: 'На модерации',
        approved: 'Опубликован',
        rejected: 'Отклонён',
    };

    return (
        <SellerLayout title="Редактирование товара">
            <Head title={`Редактирование: ${initial.title}`} />

            <div className="seller-edit-toolbar">
                <Link href={route('seller.products')} className="back-step-btn">
                    ← К моим товарам
                </Link>
                <span className="seller-edit-status">
                    Статус:{' '}
                    <strong>{statusLabels[product.status] || product.status}</strong>
                </span>
            </div>

            {product.moderation_comment && (
                <div className="seller-moderation-banner" role="alert">
                    <strong>Комментарий модератора</strong>
                    <p>{product.moderation_comment}</p>
                </div>
            )}

            <p className="builder-hint seller-edit-category">
                Категория:{' '}
                <strong>
                    {parentCategory?.name ? `${parentCategory.name} → ` : ''}
                    {leafCategory?.name}
                </strong>{' '}
                (категорию менять нельзя — так сохраняются справочники характеристик)
            </p>

            <form onSubmit={submit} className="builder-card seller-edit-form">
                {errorEntries.length > 0 && (
                    <div className="alert-banner alert-banner--error" role="alert">
                        <strong>Исправьте ошибки и сохраните снова:</strong>
                        <ul className="seller-edit-errors-list">
                            {errorEntries.map(([key, msg]) => (
                                <li key={key}>
                                    {Array.isArray(msg) ? msg[0] : msg}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <h2 className="builder-section-title">Описание</h2>

                <div className="form-group">
                    <label>
                        Название <span className="required">*</span>
                    </label>
                    <input
                        type="text"
                        value={data.title}
                        className={errors.title ? 'error' : ''}
                        onChange={(e) => setData('title', e.target.value)}
                        maxLength={200}
                    />
                    {errors.title && <div className="error-message">{errors.title}</div>}
                </div>

                <div className="form-group">
                    <label>
                        Краткое описание <span className="required">*</span>
                    </label>
                    <textarea
                        rows={3}
                        value={data.short_description}
                        className={errors.short_description ? 'error' : ''}
                        onChange={(e) => setData('short_description', e.target.value)}
                        maxLength={500}
                    />
                    <div className="field-counter">{data.short_description.length}/500</div>
                    {errors.short_description && (
                        <div className="error-message">{errors.short_description}</div>
                    )}
                </div>

                <div className="form-group">
                    <label>
                        Полное описание <span className="required">*</span>
                    </label>
                    <textarea
                        rows={10}
                        value={data.description}
                        className={errors.description ? 'error' : ''}
                        onChange={(e) => setData('description', e.target.value)}
                    />
                    {errors.description && (
                        <div className="error-message">{errors.description}</div>
                    )}
                </div>

                {productAttrs.length > 0 && (
                    <>
                        <h3 className="builder-subtitle">Характеристики</h3>
                        <div className="attributes-grid">{productAttrs.map(renderProductAttr)}</div>
                    </>
                )}

                <h2 className="builder-section-title seller-edit-mt">
                    {hasVariants ? 'Варианты товара' : 'Цена и остаток'}
                </h2>
                <p className="builder-hint">
                    {hasVariants
                        ? 'Каждый вариант — отдельный цвет, размер или комплектация. Если изменить цену — прежняя станет «старой» автоматически.'
                        : 'Если изменить цену и сохранить — прежняя цена автоматически станет «старой» (перечёркнутой).'}
                </p>

                {data.variants.map((variant, index) => (
                    <div key={variant.id ?? `new-${index}`} className="variant-card">
                        <div className="variant-card-head">
                            <h3>{hasVariants ? `Вариант ${index + 1}` : 'Основной вариант'}</h3>
                            {hasVariants && data.variants.length > 1 && (
                                <button
                                    type="button"
                                    className="variant-remove"
                                    onClick={() => removeVariant(index)}
                                >
                                    Убрать из карточки
                                </button>
                            )}
                        </div>

                        {/* Атрибуты варианта (цвет, размер и т.д.) */}
                        {variantAttrs.length > 0 && (
                            <div className="variant-attrs-grid">
                                {variantAttrs.map((attr) => renderVariantAttr(attr, index))}
                            </div>
                        )}

                        <div className="form-group">
                            <label>
                                Фото варианта (до 10) <span className="required">*</span>
                            </label>
                            {variant.existingImages?.length > 0 && (
                                <div className="seller-existing-images">
                                    {variant.existingImages.map((img) => {
                                        const isRemoving = variant.remove_image_ids.includes(img.id);
                                        const key = `existing:${img.id}`;
                                        const isMain = variant.main_image_key === key;
                                        return (
                                            <div
                                                key={img.id}
                                                className={`seller-img-tile ${isRemoving ? 'seller-img-tile--drop' : ''}`}
                                                style={{
                                                    outline: isMain ? '3px solid #4f46e5' : '1px solid #e5e7eb',

                                                }}
                                            >
                                                <img
                                                    src={img.url}
                                                    alt=""
                                                    style={{
                                                        cursor: 'pointer',
                                                        outline: isMain ? '3px solid #4f46e5' : '1px solid #e5e7eb',
                                                        outlineOffset: '2px',
                                                        borderRadius: '10px',
                                                        boxShadow: isMain
                                                            ? '0 0 0 4px rgba(79,70,229,0.18)'
                                                            : '0 2px 8px rgba(15,23,42,0.08)',
                                                    }}
                                                    onClick={() =>
                                                        updateVariantField(index, 'main_image_key', key)
                                                    }
                                                />
                                                <div className="seller-img-actions">
                                                    <label className="seller-img-remove">
                                                        <input
                                                            type="checkbox"
                                                            checked={isRemoving}
                                                            onChange={() =>
                                                                toggleRemoveVariantImage(index, img.id)
                                                            }
                                                        />
                                                        Удалить
                                                    </label>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                            <input
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                onChange={(e) =>
                                    updateVariantField(index, 'newImages', e.target.files ?? [])
                                }
                            />
                            <p className="builder-hint">Клик по фото выбирает главное.</p>
                            {(variant.newImagePreviews ?? []).length > 0 && (
                                <div className="gallery-preview">
                                    {(variant.newImagePreviews ?? []).map((src, i) => (
                                        <div key={src} className="gallery-preview-item">
                                            <img
                                                src={src}
                                                alt=""
                                                style={{
                                                    cursor: 'pointer',
                                                    outline:
                                                        variant.main_image_key === `new:${i}`
                                                            ? '3px solid #4f46e5'
                                                            : '1px solid #e5e7eb',
                                                    outlineOffset: '2px',
                                                    borderRadius: '10px',
                                                    boxShadow:
                                                        variant.main_image_key === `new:${i}`
                                                            ? '0 0 0 4px rgba(79,70,229,0.18)'
                                                            : '0 2px 8px rgba(15,23,42,0.08)',
                                                }}
                                                onClick={() =>
                                                    updateVariantField(index, 'main_image_key', `new:${i}`)
                                                }
                                            />
                                            <button
                                                type="button"
                                                className="gallery-preview-remove"
                                                onClick={() =>
                                                    updateVariantField(
                                                        index,
                                                        'newImages',
                                                        (variant.newImages ?? []).filter(
                                                            (_, fileIndex) => fileIndex !== i,
                                                        ),
                                                    )
                                                }
                                                title="Убрать"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="variant-pricing-grid">
                            <div className="form-group">
                                <label>
                                    Цена, ₽ <span className="required">*</span>
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={variant.price}
                                    className={errors[`variants.${index}.price`] ? 'error' : ''}
                                    onChange={(e) =>
                                        updateVariantField(index, 'price', e.target.value)
                                    }
                                />
                                {errors[`variants.${index}.price`] && (
                                    <div className="error-message">
                                        {errors[`variants.${index}.price`]}
                                    </div>
                                )}
                                {initial.variants[index]?.old_price && (
                                    <p className="builder-hint" style={{ marginTop: '4px' }}>
                                        Старая цена: <s>{initial.variants[index].old_price} ₽</s>
                                    </p>
                                )}
                                {initial.variants[index]?.price &&
                                    parseFloat(variant.price) > 0 &&
                                    parseFloat(variant.price) < parseFloat(initial.variants[index].price) && (
                                    <p className="builder-hint builder-hint--promo">
                                        При сохранении появится скидка — ниже можно добавить акцию «Распродажа».
                                    </p>
                                )}
                            </div>
                            <div className="form-group">
                                <label>
                                    Остаток <span className="required">*</span>
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    value={variant.stock}
                                    className={errors[`variants.${index}.stock`] ? 'error' : ''}
                                    onChange={(e) =>
                                        updateVariantField(index, 'stock', e.target.value)
                                    }
                                />
                                {errors[`variants.${index}.stock`] && (
                                    <div className="error-message">
                                        {errors[`variants.${index}.stock`]}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                ))}

                {hasVariants && (
                    <button type="button" className="add-btn" onClick={addVariant}>
                        + Добавить вариант
                    </button>
                )}

                <section className="product-promo-section">
                    <h3 className="product-promo-section__title">Акции</h3>
                    <p className="product-promo-section__lead">
                        Дополнительная метка на карточке в каталоге. Плашка «Скидка» появляется автоматически при
                        снижении цены.
                    </p>

                    {eligibleBadges.length === 0 ? (
                        <p className="builder-hint builder-hint--warn">
                            Сейчас нет доступных акций. Снизьте цену варианта (появится скидка) или добавьте товар
                            недавно — тогда откроется акция «Новинка».
                        </p>
                    ) : !data.promotion.enabled ? (
                        <div className="product-promo-section__idle">
                            <p className="product-promo-section__available">
                                У вас доступны акции:{' '}
                                <strong>{eligibleBadges.map((b) => b.label).join(', ')}</strong>
                            </p>
                            {priceChangeEnablesSale && (
                                <p className="builder-hint builder-hint--promo">
                                    После сохранения скидки можно выбрать акцию «Распродажа».
                                </p>
                            )}
                            <button
                                type="button"
                                className="product-promo-add-btn"
                                onClick={handleAddPromotion}
                            >
                                + Добавить акцию
                            </button>
                        </div>
                    ) : (
                        <div className="product-promo-section__fields">
                            {selectedPromoLabel && (
                                <p className="product-promo-section__active">
                                    Акция: <strong>{selectedPromoLabel}</strong>
                                </p>
                            )}
                            <div className="form-group">
                                <label>Тип акции</label>
                                <select
                                    value={data.promotion.badge_key}
                                    onChange={(e) => updatePromotion('badge_key', e.target.value)}
                                    required
                                >
                                    <option value="">Выберите акцию…</option>
                                    {eligibleBadges.map((b) => (
                                        <option key={b.key} value={b.key}>
                                            {b.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="form-group">
                                <label>Акция действует до</label>
                                <input
                                    type="datetime-local"
                                    value={data.promotion.ends_at}
                                    onChange={(e) => updatePromotion('ends_at', e.target.value)}
                                    required
                                />
                                <p className="builder-hint">
                                    Начало — с момента сохранения. Укажите, когда акция должна исчезнуть с карточки.
                                </p>
                            </div>
                            <button
                                type="button"
                                className="product-promo-remove-btn"
                                onClick={handleRemovePromotion}
                            >
                                Убрать акцию
                            </button>
                        </div>
                    )}
                </section>

                {errors.error && (
                    <div className="alert-banner alert-banner--error">{errors.error}</div>
                )}

                <button type="submit" className="submit-btn" disabled={processing}>
                    {processing ? 'Сохранение…' : 'Сохранить изменения'}
                </button>
            </form>
        </SellerLayout>
    );
}
