import React, { useState, useEffect, useMemo } from 'react';
import { Head, useForm } from '@inertiajs/react';
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

export default function Create({ categories }) {
    const [step, setStep] = useState(1);
    const [selectedParent, setSelectedParent] = useState(null);
    const [selectedCategory, setSelectedCategory] = useState(null);
    const [mainPreview, setMainPreview] = useState(null);
    const [galleryPreview, setGalleryPreview] = useState([]);
    const [errors, setErrors] = useState({});
    const [shake, setShake] = useState(false);
    const [showError, setShowError] = useState(false);

    const builderTitleText = selectedCategory?.name;

    const { data, setData, post, processing, transform } = useForm({
        title: '',
        category_id: '',
        short_description: '',
        description: '',
        attributes: {},
        variants: [
            {
                options: {},
                price: '',
                stock: '',
                image: null,
                imagePreview: null,
            },
        ],
        main_image: null,
        images: [],
    });

    /* Multipart: variants/attributes уходят JSON-строками. transform регистрируем один раз при монтировании. */
    useEffect(() => {
        transform((d) => {
            const payload = {
                title: d.title,
                category_id: d.category_id === '' || d.category_id == null ? '' : String(d.category_id),
                short_description: d.short_description,
                description: d.description,
                // image / imagePreview не входят в JSON — они идут как отдельные файлы
                variants_json: JSON.stringify(
                    d.variants.map(({ image, imagePreview, ...v }) => v),
                ),
                attributes_json: JSON.stringify(d.attributes ?? {}),
                main_image: d.main_image,
                images: d.images ?? [],
            };
            // Фото вариантов — отдельные поля variant_image_0, variant_image_1, ...
            d.variants.forEach((v, i) => {
                if (v.image) {
                    payload[`variant_image_${i}`] = v.image;
                }
            });
            return payload;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const productAttrs = useMemo(
        () => (selectedCategory?.attributes ?? []).filter((a) => attrScope(a) !== 'variant'),
        [selectedCategory],
    );

    const variantAttrs = useMemo(
        () => (selectedCategory?.attributes ?? []).filter((a) => attrScope(a) === 'variant'),
        [selectedCategory],
    );

    // Категория поддерживает несколько вариантов (есть атрибуты-варианты — цвет, размер и т.д.)
    const hasVariants = variantAttrs.length > 0;

    const validateStep = () => {
        const newErrors = {};

        if (step === 1) {
            if (!selectedCategory) {
                newErrors.category = 'Выберите категорию';
            }
        }

        if (step === 2) {
            if (!data.title.trim()) {
                newErrors.title = 'Введите название товара';
            }
            if (!data.short_description.trim()) {
                newErrors.short_description = 'Введите краткое описание';
            } else if (data.short_description.length > 500) {
                newErrors.short_description = 'Не более 500 символов';
            }
            if (!data.description.trim()) {
                newErrors.description = 'Введите полное описание';
            }

            productAttrs.forEach((attr) => {
                if (!attr.required) return;
                const v = data.attributes[attr.id];
                const empty =
                    v === undefined ||
                    v === null ||
                    (typeof v === 'string' && v.trim() === '');
                if (empty) {
                    newErrors[`attr_${attr.id}`] = `Заполните поле «${attr.name}»`;
                }
            });
        }

        if (step === 3) {
            data.variants.forEach((variant, idx) => {
                const price = parseFloat(variant.price);
                if (!variant.price || Number.isNaN(price) || price <= 0) {
                    newErrors[`variant_${idx}_price`] = 'Укажите цену больше 0';
                }
                const stock = parseInt(variant.stock, 10);
                if (variant.stock === '' || Number.isNaN(stock) || stock < 0) {
                    newErrors[`variant_${idx}_stock`] = 'Укажите остаток (целое число)';
                }
                variantAttrs.forEach((attr) => {
                    if (!attr.required) return;
                    const val = variant.options?.[attr.name];
                    const empty =
                        val === undefined ||
                        val === null ||
                        (typeof val === 'string' && val.trim() === '');
                    if (empty) {
                        newErrors[`variant_${idx}_attr_${attr.id}`] =
                            `Выберите «${attr.name}» для варианта ${idx + 1}`;
                    }
                });
            });
        }

        if (step === 4) {
            if (!data.main_image) {
                newErrors.main_image = 'Загрузите главное фото';
            }
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const goNext = () => {
        if (validateStep()) {
            setStep((s) => Math.min(4, s + 1));
        } else {
            const first = document.querySelector('.builder-card .error, .builder-card .error-message');
            first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    const startStep = () => {
        setStep(1);
        setSelectedParent(null);
        setSelectedCategory(null);
        setData('category_id', '');
    };

    const backStep = (targetStep) => {
        if (selectedCategory || targetStep === 1) {
            setStep(targetStep);
            return;
        }
        setShake(true);
        setShowError(true);
        setTimeout(() => setShake(false), 800);
        setTimeout(() => setShowError(false), 4000);
    };

    const chooseParent = (category) => {
        setSelectedParent(category);
        setErrors((e) => ({ ...e, category: undefined }));
    };

    const chooseCategory = (category) => {
        setSelectedCategory(category);
        setData('category_id', String(category.id));
        setData('attributes', {});
        setData('variants', [
            {
                options: {},
                price: '',
                stock: '',
                image: null,
                imagePreview: null,
            },
        ]);
        setErrors((e) => ({ ...e, category: undefined }));
    };

    const updateAttribute = (id, value) => {
        setData('attributes', {
            ...data.attributes,
            [id]: value,
        });
        setErrors((prev) => {
            const next = { ...prev };
            delete next[`attr_${id}`];
            return next;
        });
    };

    const addVariant = () => {
        setData('variants', [
            ...data.variants,
            {
                options: {},
                price: '',
                stock: '',
                image: null,
                imagePreview: null,
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

    const updateVariant = (index, field, value) => {
        const variants = data.variants.map((v, i) => {
            if (i !== index) return v;
            if (field === 'image') {
                return {
                    ...v,
                    image: value,
                    imagePreview: value ? URL.createObjectURL(value) : null,
                };
            }
            return { ...v, [field]: value };
        });
        setData('variants', variants);
        setErrors((prev) => {
            const next = { ...prev };
            delete next[`variant_${index}_${field}`];
            return next;
        });
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

    const setMainImage = (file) => {
        if (!file) return;
        setData('main_image', file);
        setMainPreview(URL.createObjectURL(file));
        setErrors((prev) => {
            const next = { ...prev };
            delete next.main_image;
            return next;
        });
    };

    const setGalleryImages = (files) => {
        const list = Array.from(files || []).slice(0, 10);
        setData('images', list);
        setGalleryPreview(list.map((image) => URL.createObjectURL(image)));
    };

    const flattenServerErrors = (err) => {
        const flat = {};
        if (!err || typeof err !== 'object') return flat;
        Object.keys(err).forEach((k) => {
            const v = err[k];
            flat[k] = Array.isArray(v) ? v[0] : v;
        });
        return flat;
    };

    const submit = (e) => {
        e.preventDefault();
        if (!validateStep()) {
            const first = document.querySelector('.builder-card .error, .builder-card .error-message');
            first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        post(route('seller.products.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {},
            onError: (err) => {
                setErrors(flattenServerErrors(err));
            },
        });
    };

    const hasBannerErrors =
        Object.keys(errors).length > 0 &&
        step === 4 &&
        Object.entries(errors).some(([k, v]) => {
            const msg = Array.isArray(v) ? v[0] : v;
            return msg && String(msg).trim() !== '';
        });

    const [buttonLoading, setButtonLoading] = useState(false);

    const handleNextStep = async () => {
        setButtonLoading(true);
        await new Promise((resolve) => setTimeout(resolve, 150));
        goNext();
        setButtonLoading(false);
    };

    return (
        <SellerLayout title="Добавление товара">
            <Head title="Добавление товара" />

            <div className="builder-layout">
                <div className="builder-sidebar">
                    <div className={step === 1 ? 'active' : ''} onClick={() => startStep()}>
                        Категория
                    </div>
                    <div className={step === 2 ? 'active' : ''} onClick={() => backStep(2)}>
                        Характеристики
                    </div>
                    <div className={step === 3 ? 'active' : ''} onClick={() => backStep(3)}>
                        Варианты
                    </div>
                    <div className={step === 4 ? 'active' : ''} onClick={() => backStep(4)}>
                        Фото
                    </div>
                </div>

                <div className="builder-content">
                    {step === 1 && (
                        <div className={'builder-card ' + (shake ? 'shake' : '')}>
                            {!selectedParent ? (
                                <>
                                    <h2 id="builder-title">
                                        Выберите раздел
                                        <div
                                            className="error-banner"
                                            style={{ display: showError ? 'block' : 'none' }}
                                        >
                                            Сначала выберите категорию!
                                        </div>
                                    </h2>
                                    <div className="category-grid">
                                        {categories.map((category) => (
                                            <div
                                                key={category.id}
                                                className="category-card"
                                                onClick={() => chooseParent(category)}
                                            >
                                                <div className="category-icon">📦</div>
                                                <div>{category.name}</div>
                                            </div>
                                        ))}
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="step-actions">
                                        <button
                                            type="button"
                                            className="back-step-btn"
                                            onClick={() => setSelectedParent(null)}
                                        >
                                            ← Назад
                                        </button>
                                        <h2 id="builder-title">
                                            {builderTitleText
                                                ? `Выбрана категория: ${builderTitleText}`
                                                : 'Категория не выбрана'}
                                        </h2>
                                    </div>
                                    <h2>{selectedParent.name}</h2>
                                    <div className="category-grid">
                                        {selectedParent.children.map((category) => (
                                            <div
                                                key={category.id}
                                                className={
                                                    'category-card' +
                                                    (selectedCategory?.id === category.id
                                                        ? ' category-card--selected'
                                                        : '')
                                                }
                                                onClick={() => chooseCategory(category)}
                                            >
                                                <div className="category-icon">🏷️</div>
                                                <div>{category.name}</div>
                                            </div>
                                        ))}
                                    </div>
                                    {errors.category && (
                                        <div className="error-message builder-error-top">
                                            {errors.category}
                                        </div>
                                    )}
                                    {selectedCategory && (
                                        <p className="builder-hint category-picked">
                                            <strong>Выбрано:</strong> {selectedParent.name} →{' '}
                                            {selectedCategory.name}
                                        </p>
                                    )}
                                    <button
                                        type="button"
                                        className="next-btn"
                                        onClick={goNext}
                                        disabled={!selectedCategory}
                                    >
                                        Далее: описание →
                                    </button>
                                </>
                            )}
                        </div>
                    )}

                    {step === 2 && selectedCategory && (
                        <div className="builder-card">
                            <div className="step-actions">
                                <button type="button" className="back-step-btn" onClick={() => setStep(1)}>
                                    ← Назад
                                </button>
                                <h2 id="builder-title">
                                    {builderTitleText
                                        ? `Категория: ${builderTitleText}`
                                        : 'Категория не выбрана'}
                                </h2>
                            </div>
                            <h2>Основная информация</h2>

                            <div className="form-group">
                                <label>
                                    Название <span className="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.title}
                                    className={errors.title ? 'error' : ''}
                                    onChange={(e) => {
                                        setData('title', e.target.value);
                                        setErrors((p) => {
                                            const n = { ...p };
                                            delete n.title;
                                            return n;
                                        });
                                    }}
                                    placeholder="Введите название товара"
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
                                    placeholder="Коротко о товаре"
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
                                    rows={8}
                                    value={data.description}
                                    className={errors.description ? 'error' : ''}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Подробное описание товара..."
                                />
                                {errors.description && (
                                    <div className="error-message">{errors.description}</div>
                                )}
                            </div>

                            {productAttrs.length > 0 && <h3>Характеристики</h3>}
                            <div className="attributes-grid">
                                {productAttrs.map((attr) => {
                                    const options = getOptions(attr);
                                    const errorKey = `attr_${attr.id}`;
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
                                                    className={errors[errorKey] ? 'error' : ''}
                                                    onChange={(e) => updateAttribute(attr.id, e.target.value)}
                                                    placeholder={`Введите ${attr.name.toLowerCase()}`}
                                                />
                                            )}

                                            {attr.type === 'number' && (
                                                <input
                                                    type="number"
                                                    value={val ?? ''}
                                                    className={errors[errorKey] ? 'error' : ''}
                                                    onChange={(e) => updateAttribute(attr.id, e.target.value)}
                                                    placeholder={`Введите ${attr.name.toLowerCase()}`}
                                                />
                                            )}

                                            {attr.type === 'boolean' && (
                                                <label className="checkbox-row">
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            val === true ||
                                                            val === '1' ||
                                                            val === 1
                                                        }
                                                        onChange={(e) =>
                                                            updateAttribute(
                                                                attr.id,
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
                                                        updateAttribute(attr.id, e.target.value)
                                                    }
                                                >
                                                    <option value="">Выберите {attr.name}</option>
                                                    {options.map((option) => (
                                                        <option key={option} value={option}>
                                                            {option}
                                                        </option>
                                                    ))}
                                                </select>
                                            )}

                                            {errors[errorKey] && (
                                                <div className="error-message">{errors[errorKey]}</div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                            <button
                                type="button"
                                className={`next-btn ${buttonLoading ? 'loading' : ''}`}
                                onClick={handleNextStep}
                                disabled={buttonLoading}
                            >
                                {buttonLoading ? (
                                    <span className="btn-loading">
                                        <span className="btn-spinner"></span>
                                        Загрузка...
                                    </span>
                                ) : (
                                    'Далее →'
                                )}
                            </button>
                        </div>
                    )}

                    {step === 3 && selectedCategory && (
                        <div className="builder-card">
                            <div className="step-actions">
                                <button type="button" className="back-step-btn" onClick={() => setStep(2)}>
                                    ← Назад
                                </button>
                                <h2 id="builder-title">
                                    {builderTitleText
                                        ? `Категория: ${builderTitleText}`
                                        : 'Категория не выбрана'}
                                </h2>
                            </div>
                            <h2>
                                {hasVariants ? 'Варианты товара' : 'Цена и остаток'}
                            </h2>
                            {hasVariants && (
                                <p className="builder-hint">
                                    Каждый вариант — отдельный цвет, размер или комплектация. У каждого своё фото, цена и остаток.
                                </p>
                            )}

                            {data.variants.map((variant, index) => (
                                <div key={index} className="variant-card">
                                    <div className="variant-card-head">
                                        <h3>{hasVariants ? `Вариант ${index + 1}` : 'Основной вариант'}</h3>
                                        {hasVariants && data.variants.length > 1 && (
                                            <button
                                                type="button"
                                                className="variant-remove"
                                                onClick={() => removeVariant(index)}
                                            >
                                                Удалить
                                            </button>
                                        )}
                                    </div>

                                    {/* Атрибуты варианта (цвет, размер и т.д.) */}
                                    {variantAttrs.map((attr) => {
                                        const options = getOptions(attr);
                                        const errKey = `variant_${index}_attr_${attr.id}`;
                                        const selVal = variant.options?.[attr.name];

                                        return (
                                            <div key={attr.id} className="form-group">
                                                <label>
                                                    {attr.name}
                                                    {attr.required && (
                                                        <span className="required">*</span>
                                                    )}
                                                </label>
                                                <select
                                                    value={selVal ?? ''}
                                                    className={errors[errKey] ? 'error' : ''}
                                                    onChange={(e) =>
                                                        updateVariantOption(
                                                            index,
                                                            attr.name,
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">Выберите {attr.name}</option>
                                                    {options.map((option) => (
                                                        <option key={option} value={option}>
                                                            {option}
                                                        </option>
                                                    ))}
                                                </select>
                                                {errors[errKey] && (
                                                    <div className="error-message">{errors[errKey]}</div>
                                                )}
                                            </div>
                                        );
                                    })}

                                    {/* Фото варианта — только если у категории есть варианты */}
                                    {hasVariants && (
                                        <div className="form-group">
                                            <label>Фото этого варианта</label>
                                            <input
                                                type="file"
                                                accept="image/*"
                                                onChange={(e) =>
                                                    updateVariant(index, 'image', e.target.files?.[0] ?? null)
                                                }
                                            />
                                            {variant.imagePreview && (
                                                <img
                                                    src={variant.imagePreview}
                                                    className="variant-img-preview"
                                                    alt=""
                                                />
                                            )}
                                        </div>
                                    )}

                                    <div className="variant-pricing-grid">
                                        <div className="form-group">
                                            <label>
                                                Цена <span className="required">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={variant.price}
                                                placeholder="Цена в ₽"
                                                className={
                                                    errors[`variant_${index}_price`] ? 'error' : ''
                                                }
                                                onChange={(e) =>
                                                    updateVariant(index, 'price', e.target.value)
                                                }
                                            />
                                            {errors[`variant_${index}_price`] && (
                                                <div className="error-message">
                                                    {errors[`variant_${index}_price`]}
                                                </div>
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
                                                placeholder="Количество на складе"
                                                className={
                                                    errors[`variant_${index}_stock`] ? 'error' : ''
                                                }
                                                onChange={(e) =>
                                                    updateVariant(index, 'stock', e.target.value)
                                                }
                                            />
                                            {errors[`variant_${index}_stock`] && (
                                                <div className="error-message">
                                                    {errors[`variant_${index}_stock`]}
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
                            <button
                                type="button"
                                className={`next-btn ${buttonLoading ? 'loading' : ''}`}
                                onClick={handleNextStep}
                                disabled={buttonLoading}
                            >
                                {buttonLoading ? (
                                    <span className="btn-loading">
                                        <span className="btn-spinner"></span>
                                        Загрузка...
                                    </span>
                                ) : (
                                    'Далее →'
                                )}
                            </button>
                        </div>
                    )}

                    {step === 4 && (
                        <form onSubmit={submit} className="builder-card">
                            <div className="step-actions">
                                <button
                                    type="button"
                                    className="back-step-btn"
                                    onClick={() => setStep(3)}
                                >
                                    ← Назад
                                </button>
                                <h2 id="builder-title">
                                    {builderTitleText
                                        ? `Категория: ${builderTitleText}`
                                        : 'Категория не выбрана'}
                                </h2>
                            </div>
                            <h2>Фото товара</h2>

                            {hasBannerErrors && (
                                <div className="alert-banner alert-banner--error" role="alert">
                                    <strong>Не удалось сохранить. Проверьте поля:</strong>
                                    <ul className="seller-edit-errors-list">
                                        {Object.entries(errors).map(([key, val]) => {
                                            const msg = Array.isArray(val) ? val[0] : val;
                                            if (!msg) return null;
                                            return (
                                                <li key={key}>
                                                    <strong>{key}:</strong> {msg}
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>
                            )}

                            <div className="image-box">
                                <label>
                                    Главное фото <span className="required">*</span>
                                </label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => setMainImage(e.target.files?.[0])}
                                />
                                {errors.main_image && (
                                    <div className="error-message">{errors.main_image}</div>
                                )}
                                {mainPreview && (
                                    <img src={mainPreview} className="main-preview" alt="" />
                                )}
                            </div>

                            <div className="image-box">
                                <label>Дополнительные фото (до 10 шт)</label>
                                <input
                                    type="file"
                                    multiple
                                    accept="image/*"
                                    onChange={(e) => setGalleryImages(e.target.files)}
                                />
                                <div className="gallery-preview">
                                    {galleryPreview.map((src, i) => (
                                        <img key={src} src={src} alt={`Превью ${i + 1}`} />
                                    ))}
                                </div>
                            </div>

                            <button type="submit" className="submit-btn" disabled={processing}>
                                {processing ? 'Создание...' : 'Создать товар'}
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </SellerLayout>
    );
}
