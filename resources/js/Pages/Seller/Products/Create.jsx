import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';
import '../../../../css/seller/create-product.css';

export default function Create({ categories }) {
    const [step, setStep] = useState(1);
    const [selectedParent, setSelectedParent] = useState(null);
    const [selectedCategory, setSelectedCategory] = useState(null);
    const [mainPreview, setMainPreview] = useState(null);
    const [galleryPreview, setGalleryPreview] = useState([]);
    const [errors, setErrors] = useState({});
    const builderTitleText = selectedCategory != null && selectedCategory['name'];

    const { data, setData, post, processing } = useForm({
        title: '',
        category_id: '',
        short_description: '',
        description: '',
        attributes: {},
        variants: [
            {
                options: {},
                price: '',
                old_price: '',
                stock: '',
            }
        ],
        main_image: null,
        images: []
    });

    // Валидация текущего шага
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
            }
            if (!data.description.trim()) {
                newErrors.description = 'Введите полное описание';
            }

            // Проверка обязательных атрибутов
            if (selectedCategory?.attributes) {
                selectedCategory.attributes.forEach(attr => {
                    if (attr.required && !data.attributes[attr.id]) {
                        newErrors[`attr_${attr.id}`] = `Заполните поле "${attr.name}"`;
                    }
                });
            }
        }

        if (step === 3) {
            data.variants.forEach((variant, idx) => {
                if (!variant.price || variant.price <= 0) {
                    newErrors[`variant_${idx}_price`] = 'Укажите цену';
                }
                if (!variant.stock || variant.stock < 0) {
                    newErrors[`variant_${idx}_stock`] = 'Укажите остаток';
                }
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

    // Переход к следующему шагу с валидацией
    const nextStep = () => {
        if (validateStep()) {
            setStep(step + 1);
        } else {
            // Прокрутка к первой ошибке
            const firstError = document.querySelector('.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    };

    const startStep = () => {
        setStep(1)
        setSelectedParent(null)
    };
    // Добавь в компонент Create.jsx


    const [shake, setShake] = useState(false);
    const [showError, setShowError] = useState(false); // Отдельное состояние для ошибки

    const backStep = (targetStep) => {
        if (selectedCategory) {
            setStep(targetStep);
        } else {
            // Запускаем анимацию тряски
            setShake(true);
            // Показываем сообщение об ошибке
            setShowError(true);

            setTimeout(() => setShake(false), 800);

            // Скрываем ошибку через 2 с после появления
            setTimeout(() => setShowError(false), 4000);
        }
    };

    // Выбор родительской категории
    const chooseParent = (category) => {
        setSelectedParent(category);
        setErrors({});

    };

    // Выбор финальной категории
    // Выбор финальной категории
    const chooseCategory = (category) => {
        setSelectedCategory(category);
        setData('category_id', category.id);
    };

    // И используй useEffect для перехода после обновления состояния
    useEffect(() => {
        if (selectedCategory) {
            // Небольшая задержка для гарантии обновления
            setTimeout(() => {
                setStep(2);
            }, 100);
        }
    }, [selectedCategory]);

    // Обновление атрибута
    const updateAttribute = (id, value) => {
        setData('attributes', {
            ...data.attributes,
            [id]: value
        });
        // Убираем ошибку для этого поля
        if (errors[`attr_${id}`]) {
            const newErrors = { ...errors };
            delete newErrors[`attr_${id}`];
            setErrors(newErrors);
        }
    };

    // Добавление варианта
    const addVariant = () => {
        setData('variants', [
            ...data.variants,
            {
                options: {},
                price: '',
                old_price: '',
                stock: '',
            }
        ]);
    };

    // Обновление варианта
    const updateVariant = (index, field, value) => {
        const variants = [...data.variants];
        variants[index][field] = value;
        setData('variants', variants);

        // Убираем ошибку для этого поля
        const errorKey = `variant_${index}_${field}`;
        if (errors[errorKey]) {
            const newErrors = { ...errors };
            delete newErrors[errorKey];
            setErrors(newErrors);
        }
    };

    // Установка главного фото
    const setMainImage = (file) => {
        setData('main_image', file);
        setMainPreview(URL.createObjectURL(file));
        if (errors.main_image) {
            const newErrors = { ...errors };
            delete newErrors.main_image;
            setErrors(newErrors);
        }
    };

    // Установка галереи
    const setGalleryImages = (files) => {
        const images = Array.from(files).slice(0, 10);
        setData('images', images);
        setGalleryPreview(images.map(image => URL.createObjectURL(image)));
    };

    // Отправка формы
    const submit = (e) => {
        e.preventDefault();
        if (validateStep()) {
            post('/seller/products/store', {
                forceFormData: true,
                onSuccess: () => {
                    // Успешно создано
                },
                onError: (error) => {
                    setErrors(error);
                }
            });
        }
    };

    // Атрибуты для вариантов
    const variantAttributes = selectedCategory?.attributes.filter(
        attr => attr.name === 'Цвет' || attr.name === 'Размер' || attr.name === 'Память'
    ) || [];

    // Получение options для select
    const getOptions = (attr) => {
        let options = [];
        if (attr.options) {
            if (Array.isArray(attr.options)) {
                options = attr.options;
            } else if (typeof attr.options === 'string') {
                try {
                    const parsed = JSON.parse(attr.options);
                    options = Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    options = [];
                }
            }
        }
        return options;
    };
    const [buttonLoading, setButtonLoading] = useState(false);

    const handleNextStep = async () => {
        setButtonLoading(true);

        // Имитация проверки
        await new Promise(resolve => setTimeout(resolve, 600));

        if (validateStep()) {
            setStep(step + 1);
        }

        setButtonLoading(false);
    };
    return (
        <SellerLayout title="Добавление товара">
            <Head title="Добавление товара" />

            <div className="builder-layout">
                <div className="builder-sidebar">
                    <div className={step == 1 ? 'active' : ''} onClick={() => startStep()}>
                        Категория
                    </div>
                    <div className={step == 2 ? 'active' : ''} onClick={() => backStep(2)}>
                        Характеристики
                    </div>
                    <div className={step == 3 ? 'active' : ''} onClick={() => backStep(3)}>
                        Варианты
                    </div>
                    <div className={step == 4 ? 'active' : ''} onClick={() => backStep(4)}>
                        Фото
                    </div>
                </div>

                <div className="builder-content">
                    {/* STEP 1 */}
                    {step === 1 && (
                        <div className={"builder-card " + (shake ? "shake" : "")}>

                            {!selectedParent ? (
                                <>
                                    <h2 id='builder-title'>Выберите раздел<div
                                        id="step-error"
                                        className="error-banner"
                                        style={{ display: showError ? 'block' : 'none' }}
                                    >
                                        Сначала выберите категорию!
                                    </div>  </h2>
                                    <div className="category-grid">
                                        {categories.map(category => (
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
                                        <button className="back-step-btn" onClick={() => setSelectedParent(null)}>
                                            ← Назад
                                        </button>
                                        <h2 id='builder-title'>{builderTitleText ? ('Выбрано категория: ' + builderTitleText) : ('Категория не выбрано')} </h2>

                                    </div>
                                    <h2>{selectedParent.name}</h2>
                                    <div className="category-grid">
                                        {selectedParent.children.map(category => (
                                            <div
                                                key={category.id}
                                                className="category-card"
                                                onClick={() => chooseCategory(category)}
                                            >
                                                <div className="category-icon">📱</div>
                                                <div>{category.name}</div>
                                            </div>
                                        ))}
                                    </div>
                                </>
                            )}

                        </div>
                    )}

                    {/* STEP 2 */}
                    {step === 2 && (
                        <div className="builder-card">
                            <div className="step-actions">
                                <button className="back-step-btn" onClick={() => backStep(1)}>
                                    ← Назад
                                </button>
                                <h2 id='builder-title'>{builderTitleText ? ('Выбрано категория: ' + builderTitleText) : ('Категория не выбрано')} </h2>

                            </div>
                            <h2>📝 Основная информация</h2>

                            <div className="form-group">
                                <label>Название <span className="required">*</span></label>
                                <input
                                    type="text"
                                    value={data.title}
                                    className={errors.title ? 'error' : ''}
                                    onChange={(e) => {
                                        setData('title', e.target.value);
                                        if (errors.title) delete errors.title;
                                    }}
                                    placeholder="Введите название товара"
                                />
                                {errors.title && <div className="error-message">{errors.title}</div>}
                            </div>

                            <div className="form-group">
                                <label>Краткое описание <span className="required">*</span></label>
                                <textarea
                                    rows="3"
                                    value={data.short_description}
                                    className={errors.short_description ? 'error' : ''}
                                    onChange={(e) => setData('short_description', e.target.value)}
                                    placeholder="Коротко о товаре (до 200 символов)"
                                />
                                {errors.short_description && <div className="error-message">{errors.short_description}</div>}
                            </div>

                            <div className="form-group">
                                <label>Полное описание <span className="required">*</span></label>
                                <textarea
                                    rows="8"
                                    value={data.description}
                                    className={errors.description ? 'error' : ''}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Подробное описание товара..."
                                />
                                {errors.description && <div className="error-message">{errors.description}</div>}
                            </div>

                            {selectedCategory.attributes.length > 0 && <h3>Характеристики</h3>}
                            <div className="attributes-grid">
                                {selectedCategory.attributes.map(attr => {
                                    const options = getOptions(attr);
                                    const errorKey = `attr_${attr.id}`;

                                    return (
                                        <div key={attr.id} className="form-group">
                                            <label>
                                                {attr.name}
                                                {attr.required && <span className="required">*</span>}
                                            </label>

                                            {attr.type === 'text' && (
                                                <input
                                                    type="text"
                                                    className={errors[errorKey] ? 'error' : ''}
                                                    onChange={(e) => updateAttribute(attr.id, e.target.value)}
                                                    placeholder={`Введите ${attr.name.toLowerCase()}`}
                                                />
                                            )}

                                            {attr.type === 'number' && (
                                                <input
                                                    type="number"
                                                    className={errors[errorKey] ? 'error' : ''}
                                                    onChange={(e) => updateAttribute(attr.id, e.target.value)}
                                                    placeholder={`Введите ${attr.name.toLowerCase()}`}
                                                />
                                            )}

                                            {attr.type === 'select' && (
                                                <select
                                                    className={errors[errorKey] ? 'error' : ''}
                                                    onChange={(e) => updateAttribute(attr.id, e.target.value)}
                                                >
                                                    <option value="">Выберите {attr.name}</option>
                                                    {options.map(option => (
                                                        <option key={option} value={option}>{option}</option>
                                                    ))}
                                                </select>
                                            )}

                                            {errors[errorKey] && <div className="error-message">{errors[errorKey]}</div>}
                                        </div>
                                    );
                                })}
                            </div>
                            <button
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

                    {/* STEP 3 */}
                    {step === 3 && (
                        <div className="builder-card">
                            <div className="step-actions">
                                <button className="back-step-btn" onClick={() => backStep(2)}>
                                    ← Назад
                                </button>
                                <h2 id='builder-title'>{builderTitleText ? ('Выбрано категория: ' + builderTitleText) : ('Категория не выбрано')} </h2>


                            </div>
                            <h2>🔄 Варианты товара</h2>

                            {data.variants.map((variant, index) => (
                                <div key={index} className="variant-card">
                                    <h3>Вариант {index + 1}</h3>

                                    {variantAttributes.map(attr => {
                                        const options = getOptions(attr);
                                        return (
                                            <div key={attr.id}>
                                                <label>{attr.name}</label>
                                                <select
                                                    onChange={(e) => updateVariant(index, 'options', {
                                                        ...variant.options,
                                                        [attr.name]: e.target.value
                                                    })}
                                                >
                                                    <option value="">Выберите {attr.name}</option>
                                                    {options.map(option => (
                                                        <option key={option} value={option}>{option}</option>
                                                    ))}
                                                </select>
                                            </div>
                                        );
                                    })}

                                    <div>
                                        <label>Цена <span className="required">*</span></label>
                                        <input
                                            type="number"
                                            placeholder="Цена в ₽"
                                            className={errors[`variant_${index}_price`] ? 'error' : ''}
                                            onChange={(e) => updateVariant(index, 'price', e.target.value)}
                                        />
                                        {errors[`variant_${index}_price`] && <div className="error-message">{errors[`variant_${index}_price`]}</div>}
                                    </div>

                                    <div>
                                        <label>Старая цена</label>
                                        <input
                                            type="number"
                                            placeholder="Старая цена (опционально)"
                                            onChange={(e) => updateVariant(index, 'old_price', e.target.value)}
                                        />
                                    </div>

                                    <div>
                                        <label>Остаток <span className="required">*</span></label>
                                        <input
                                            type="number"
                                            placeholder="Количество на складе"
                                            className={errors[`variant_${index}_stock`] ? 'error' : ''}
                                            onChange={(e) => updateVariant(index, 'stock', e.target.value)}
                                        />
                                        {errors[`variant_${index}_stock`] && <div className="error-message">{errors[`variant_${index}_stock`]}</div>}
                                    </div>
                                </div>
                            ))}

                            <button type="button" className="add-btn" onClick={addVariant}>
                                + Добавить вариант
                            </button>
                            <button
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

                    {/* STEP 4 */}
                    {step === 4 && (
                        <form onSubmit={submit} className="builder-card">
                            <div className="step-actions">
                                <button
                                    type="button"
                                    className="back-step-btn"
                                    onClick={() => backStep(3)}
                                >
                                    ← Назад
                                </button>
                                <h2 id='builder-title'>{builderTitleText ? ('Выбрано категория: ' + builderTitleText) : ('Категория не выбрано')} </h2>
                            </div>
                            <h2>📸 Фото товара</h2>

                            <div className="image-box">
                                <label>Главное фото <span className="required">*</span></label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => setMainImage(e.target.files[0])}
                                />
                                {errors.main_image && <div className="error-message">{errors.main_image}</div>}
                                {mainPreview && (
                                    <img src={mainPreview} className="main-preview" alt="Preview" />
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
                                    {galleryPreview.map((image, index) => (
                                        <img key={index} src={image} alt={`Preview ${index + 1}`} />
                                    ))}
                                </div>
                            </div>

                            <button type="submit" className="submit-btn" disabled={processing}>
                                {processing ? 'Создание...' : '✨ Создать товар'}
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </SellerLayout>
    );
}