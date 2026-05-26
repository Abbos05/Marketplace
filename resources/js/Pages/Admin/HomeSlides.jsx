import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/admin/dashboard.css';

const MAX_SLIDE_IMAGE_BYTES = 5 * 1024 * 1024;

const DEFAULT_SLIDE_LIMITS = {
    title: 80,
    description: 400,
    button_text: 32,
    link_target: 500,
};

const POSITION_OPTIONS = [
    { value: 'first', label: 'Показать первым (в начале)' },
    { value: 'last', label: 'Показать последним (в конце)' },
    { value: 'after', label: 'Показать после слайда…' },
    { value: 'before', label: 'Показать перед слайдом…' },
];

function truncateField(value, max) {
    const s = String(value ?? '');
    return s.length > max ? s.slice(0, max) : s;
}

function sortSlidesList(slides) {
    return [...slides].sort(
        (a, b) => (Number(a.sort_order) - Number(b.sort_order)) || (a.id - b.id)
    );
}

function slideLabel(s) {
    const title = (s.title || '').trim();
    return title ? `${title} (#${s.id})` : `Слайд #${s.id}`;
}

function derivePositionFromSlide(slide, allSlides) {
    const ordered = sortSlidesList(allSlides);
    const idx = ordered.findIndex((s) => s.id === slide.id);
    if (idx <= 0) {
        return { display_position: 'first', relative_slide_id: '' };
    }
    if (idx >= ordered.length - 1) {
        return { display_position: 'last', relative_slide_id: '' };
    }
    return {
        display_position: 'after',
        relative_slide_id: String(ordered[idx - 1].id),
    };
}

function validateSlideFields(data, slides, limits, excludeId = null) {
    const errors = {};
    const title = String(data.title ?? '');
    const description = String(data.description ?? '');
    const buttonText = String(data.button_text ?? '');
    const linkType = data.link_type ?? 'none';
    const linkTarget = String(data.link_target ?? '').trim();
    const position = data.display_position ?? 'last';
    const relativeId = String(data.relative_slide_id ?? '').trim();
    const others = slides.filter((s) => s.id !== excludeId);

    if (title.length > limits.title) {
        errors.title = `Заголовок — не длиннее ${limits.title} символов.`;
    }
    if (description.length > limits.description) {
        errors.description = `Описание — не длиннее ${limits.description} символов.`;
    }
    if (buttonText.length > limits.button_text) {
        errors.button_text = `Текст кнопки — не длиннее ${limits.button_text} символов.`;
    }

    if ((position === 'after' || position === 'before') && others.length === 0) {
        errors.display_position = 'Нет других слайдов — выберите «первым» или «последним».';
    } else if ((position === 'after' || position === 'before') && !relativeId) {
        errors.display_position = 'Выберите слайд, относительно которого поставить этот.';
    } else if (
        (position === 'after' || position === 'before')
        && !others.some((s) => String(s.id) === relativeId)
    ) {
        errors.display_position = 'Выбранный слайд не найден.';
    }

    if (linkType !== 'none' && !linkTarget) {
        errors.link_target = 'Укажите цель ссылки для выбранного типа.';
    }
    if (linkTarget.length > limits.link_target) {
        errors.link_target = `Ссылка — не длиннее ${limits.link_target} символов.`;
    }

    return { ok: Object.keys(errors).length === 0, errors };
}

function CharCounter({ value, max }) {
    const len = String(value ?? '').length;
    const over = len > max;
    const near = !over && len > max * 0.85;
    return (
        <span style={{ fontSize: 12, color: over ? '#b91c1c' : near ? '#b45309' : '#6b7280' }}>
            {len} / {max}
        </span>
    );
}

function FieldError({ message }) {
    if (!message) return null;
    return <span style={{ color: '#b91c1c', fontSize: 13 }}>{message}</span>;
}

function SlidePositionPicker({
    data,
    setData,
    slides,
    excludeId = null,
    errors = {},
    serverErrors = {},
}) {
    const others = sortSlidesList(slides.filter((s) => s.id !== excludeId));
    const position = data.display_position ?? 'last';
    const needsReference = position === 'after' || position === 'before';
    const positionError = errors.display_position || serverErrors.display_position
        || errors.relative_slide_id || serverErrors.relative_slide_id;

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                <span style={{ fontWeight: 600 }}>Положение в карусели</span>
                <select
                    className="admin-search-input"
                    value={position}
                    onChange={(e) => {
                        const next = e.target.value;
                        if (next === 'after' || next === 'before') {
                            setData({
                                display_position: next,
                                relative_slide_id: data.relative_slide_id
                                    || (others[0] ? String(others[0].id) : ''),
                            });
                        } else {
                            setData({
                                display_position: next,
                                relative_slide_id: '',
                            });
                        }
                    }}
                >
                    {POSITION_OPTIONS.map((opt) => {
                        if ((opt.value === 'after' || opt.value === 'before') && others.length === 0) {
                            return null;
                        }
                        return (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        );
                    })}
                </select>
            </label>
            {needsReference && others.length > 0 && (
                <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                    <span>{position === 'after' ? 'После какого слайда' : 'Перед каким слайдом'}</span>
                    <select
                        className="admin-search-input"
                        value={data.relative_slide_id ?? ''}
                        onChange={(e) => setData('relative_slide_id', e.target.value)}
                    >
                        <option value="">— выберите слайд —</option>
                        {others.map((s) => (
                            <option key={s.id} value={String(s.id)}>
                                {slideLabel(s)}
                            </option>
                        ))}
                    </select>
                </label>
            )}
            {others.length > 0 && (
                <span className="adm-stat-label" style={{ fontSize: 12, lineHeight: 1.45 }}>
                    Сейчас в карусели: {others.map((s, i) => `${i + 1}. ${slideLabel(s)}`).join(' → ')}
                    {excludeId ? ` → … (редактируемый)` : ''}
                </span>
            )}
            <FieldError message={positionError} />
        </div>
    );
}

function SlideTextFields({
    data,
    setData,
    limits,
    errors = {},
    serverErrors = {},
}) {
    return (
        <>
            <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                <span style={{ display: 'flex', justifyContent: 'space-between', gap: 8, alignItems: 'baseline' }}>
                    <span>Заголовок</span>
                    <CharCounter value={data.title} max={limits.title} />
                </span>
                <input
                    type="text"
                    value={data.title}
                    maxLength={limits.title}
                    onChange={(e) => setData('title', truncateField(e.target.value, limits.title))}
                    className="admin-search-input"
                    placeholder="Короткий заголовок баннера"
                />
                <FieldError message={errors.title || serverErrors.title} />
            </label>
            <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                <span style={{ display: 'flex', justifyContent: 'space-between', gap: 8, alignItems: 'baseline' }}>
                    <span>Описание</span>
                    <CharCounter value={data.description} max={limits.description} />
                </span>
                <textarea
                    value={data.description}
                    maxLength={limits.description}
                    onChange={(e) => setData('description', truncateField(e.target.value, limits.description))}
                    className="admin-search-input"
                    rows={3}
                    placeholder="Краткий текст под заголовком"
                />
                <FieldError message={errors.description || serverErrors.description} />
            </label>
            <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                <span style={{ display: 'flex', justifyContent: 'space-between', gap: 8, alignItems: 'baseline' }}>
                    <span>Текст кнопки</span>
                    <CharCounter value={data.button_text} max={limits.button_text} />
                </span>
                <input
                    type="text"
                    value={data.button_text}
                    maxLength={limits.button_text}
                    onChange={(e) => setData('button_text', truncateField(e.target.value, limits.button_text))}
                    className="admin-search-input"
                    placeholder="Например: Смотреть каталог"
                />
                <FieldError message={errors.button_text || serverErrors.button_text} />
            </label>
        </>
    );
}

function validateSlideBannerFileSync(file) {
    if (!file) return null;
    if (!file.type?.startsWith('image/')) {
        return 'Выберите файл изображения (JPEG, PNG, WebP и т.д.).';
    }
    if (file.size > MAX_SLIDE_IMAGE_BYTES) {
        const mb = (file.size / (1024 * 1024)).toFixed(1);
        return `Файл слишком большой (${mb} МБ). Максимум — 5 МБ.`;
    }
    return null;
}

/** Баннер: только «альбом»; те же правила проверяются на сервере. */
function validateSlideBannerFile(file) {
    const syncErr = validateSlideBannerFileSync(file);
    if (syncErr) {
        return Promise.resolve(syncErr);
    }
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(url);
            const w = img.naturalWidth;
            const h = img.naturalHeight;
            if (w < 640) {
                resolve(`Минимальная ширина — 640 px (сейчас ${w} px).`);
                return;
            }
            if (h < 200) {
                resolve('Минимальная высота — 200 px.');
                return;
            }
            if (w <= h) {
                resolve('Нужна горизонтальная картинка: ширина должна быть больше высоты (портретные не подходят).');
                return;
            }
            if (w / h < 1.25) {
                resolve('Слишком «высокий» кадр. Соотношение ширины к высоте не меньше 1,25:1 (например 5:4 или шире).');
                return;
            }
            resolve(null);
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            resolve('Не удалось прочитать файл как изображение.');
        };
        img.src = url;
    });
}

const defaultCreate = {
    title: '',
    description: '',
    button_text: '',
    display_position: 'last',
    relative_slide_id: '',
    is_active: 1,
    link_type: 'none',
    link_target: '',
    image: null,
};

export default function AdminHomeSlides({
    auth,
    slides = [],
    categories = [],
    routeOptions = [],
    linkTypes = [],
    slideFieldLimits: slideFieldLimitsProp = null,
}) {
    const limits = slideFieldLimitsProp ?? DEFAULT_SLIDE_LIMITS;
    const createForm = useForm({ ...defaultCreate });
    const [createImageClientError, setCreateImageClientError] = useState(null);
    const [editImageClientError, setEditImageClientError] = useState(null);
    const [createImageValidating, setCreateImageValidating] = useState(false);
    const [editImageValidating, setEditImageValidating] = useState(false);
    const [createFieldErrors, setCreateFieldErrors] = useState({});
    const [editFieldErrors, setEditFieldErrors] = useState({});

    const handleSlideImageChange = (file, input, { setError, setValidating, setFile }) => {
        setError(null);
        setValidating(false);
        setFile(null);
        if (!file) return;

        const syncErr = validateSlideBannerFileSync(file);
        if (syncErr) {
            setError(syncErr);
            if (input) input.value = '';
            return;
        }

        setValidating(true);
        setFile(file);
        void validateSlideBannerFile(file).then((err) => {
            setValidating(false);
            if (err) {
                setError(err);
                setFile(null);
                if (input) input.value = '';
            }
        });
    };

    const submitCreate = (e) => {
        e.preventDefault();
        if (createImageClientError || createImageValidating) return;
        if (!createForm.data.image || !(createForm.data.image instanceof File)) {
            setCreateImageClientError('Выберите изображение баннера.');
            return;
        }
        const validation = validateSlideFields(createForm.data, slides, limits);
        if (!validation.ok) {
            setCreateFieldErrors(validation.errors);
            return;
        }
        setCreateFieldErrors({});
        createForm.post(route('admin.home-slides.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                createForm.setData({ ...defaultCreate });
                setCreateImageClientError(null);
                setCreateFieldErrors({});
            },
        });
    };

    const [editingId, setEditingId] = useState(null);
    const editForm = useForm({
        title: '',
        description: '',
        button_text: '',
        display_position: 'last',
        relative_slide_id: '',
        is_active: 1,
        link_type: 'none',
        link_target: '',
        image: null,
    });

    const startEdit = (s) => {
        setEditingId(s.id);
        setEditImageClientError(null);
        const position = derivePositionFromSlide(s, slides);
        editForm.setData({
            title: s.title ?? '',
            description: s.description ?? '',
            button_text: s.button_text ?? '',
            display_position: position.display_position,
            relative_slide_id: position.relative_slide_id,
            is_active: s.is_active ? 1 : 0,
            link_type: s.link_type ?? 'none',
            link_target: s.link_target != null ? String(s.link_target) : '',
            image: null,
        });
        editForm.clearErrors();
        setEditFieldErrors({});
    };

    const saveEdit = (id) => {
        if (editImageClientError || editImageValidating) return;
        const validation = validateSlideFields(editForm.data, slides, limits, id);
        if (!validation.ok) {
            setEditFieldErrors(validation.errors);
            return;
        }
        setEditFieldErrors({});
        const fd = new FormData();
        fd.append('title', editForm.data.title ?? '');
        fd.append('description', editForm.data.description ?? '');
        fd.append('button_text', editForm.data.button_text ?? '');
        fd.append('display_position', editForm.data.display_position ?? 'last');
        if (editForm.data.relative_slide_id) {
            fd.append('relative_slide_id', editForm.data.relative_slide_id);
        }
        fd.append('is_active', String(editForm.data.is_active ?? 1));
        fd.append('link_type', editForm.data.link_type ?? 'none');
        if (editForm.data.link_target) {
            fd.append('link_target', editForm.data.link_target);
        }
        if (editForm.data.image instanceof File) {
            fd.append('image', editForm.data.image);
        }
        fd.append('_method', 'PATCH');

        router.post(route('admin.home-slides.update', id), fd, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingId(null);
                setEditImageClientError(null);
                setEditFieldErrors({});
            },
            onError: () => setEditFieldErrors({}),
        });
    };

    const removeSlide = (id) => {
        if (!confirm('Удалить слайд? Изображение из хранилища сайта будет удалено.')) return;
        router.delete(route('admin.home-slides.destroy', id), { preserveScroll: true });
    };

    const linkTargetHint = (type) => {
        if (type === 'category') return 'Выберите категорию ниже или укажите ID вручную.';
        if (type === 'product') return 'ID товара (число).';
        if (type === 'route') return 'Системная страница из списка.';
        if (type === 'url') return 'Например /about или https://…';
        return '—';
    };

    const orderedSlides = sortSlidesList(slides);
    const carouselIndexById = Object.fromEntries(
        orderedSlides.map((s, i) => [s.id, i + 1])
    );

    return (
        <MainLayout auth={auth}>
            <Head title="Слайдер главной · Админ" />

            <div className="adm-detail-page">
                <div className="adm-detail-nav">
                    <a href="/admin/dashboard" className="adm-back-link">← Панель администратора</a>
                    <span style={{ margin: '0 8px', color: '#94a3b8' }}>|</span>
                    <a href="/admin/products" className="adm-back-link">Все товары</a>
                    <span style={{ margin: '0 8px', color: '#94a3b8' }}>|</span>
                    <a href="/admin/pickup-points" className="adm-back-link">Пункты выдачи</a>
                </div>

                <h1 className="adm-title">Слайдер главной и каталога</h1>
                <p className="adm-stat-label" style={{ marginTop: -8, marginBottom: 20, maxWidth: 720 }}>
                    Рекомендуется 3 и более активных слайда. Картинка — обложка; текст и кнопка настраиваются отдельно.
                    <br />
                    <strong>Фото баннера:</strong> до 5 МБ; горизонтальное (ширина больше высоты), соотношение не уже 5:4 (1,25:1), минимум 640×200 px.
                    <br />
                    <strong>Тексты:</strong> заголовок до {limits.title} симв., описание до {limits.description}, кнопка до {limits.button_text}.
                    Положение в карусели задаётся выбором: первым, последним, после или перед другим слайдом.
                </p>

                <div className="adm-detail-card" style={{ marginBottom: 24, padding: 20 }}>
                    <h2 className="adm-stat-label" style={{ marginTop: 0, marginBottom: 16, fontSize: 16, fontWeight: 700 }}>
                        Новый слайд
                    </h2>
                    <form onSubmit={submitCreate} style={{ display: 'grid', gap: 12, maxWidth: 720 }}>
                        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                            Изображение (обязательно)
                            <input
                                type="file"
                                accept="image/*"
                                onChange={(e) => {
                                    const input = e.target;
                                    const f = input.files?.[0] ?? null;
                                    handleSlideImageChange(f, input, {
                                        setError: setCreateImageClientError,
                                        setValidating: setCreateImageValidating,
                                        setFile: (file) => createForm.setData('image', file),
                                    });
                                }}
                                className="admin-search-input"
                                required
                            />
                            {(createImageClientError || createForm.errors.image) && (
                                <span style={{ color: '#b91c1c', fontSize: 13 }}>
                                    {createImageClientError || createForm.errors.image}
                                </span>
                            )}
                        </label>
                        <SlideTextFields
                            data={createForm.data}
                            setData={createForm.setData}
                            limits={limits}
                            errors={createFieldErrors}
                            serverErrors={createForm.errors}
                        />
                        <SlidePositionPicker
                            data={createForm.data}
                            setData={createForm.setData}
                            slides={slides}
                            errors={createFieldErrors}
                            serverErrors={createForm.errors}
                        />
                        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                            Активен
                            <select
                                value={createForm.data.is_active}
                                onChange={(e) => createForm.setData('is_active', Number(e.target.value))}
                                className="admin-search-input"
                            >
                                <option value={1}>Да</option>
                                <option value={0}>Нет</option>
                            </select>
                        </label>
                        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                            Тип ссылки
                            <select
                                value={createForm.data.link_type}
                                onChange={(e) => createForm.setData('link_type', e.target.value)}
                                className="admin-search-input"
                            >
                                {linkTypes.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="adm-stat-label" style={{ fontSize: 13 }}>{linkTargetHint(createForm.data.link_type)}</div>
                        {createForm.data.link_type === 'category' && (
                            <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                                Категория
                                <select
                                    value={createForm.data.link_target}
                                    onChange={(e) => createForm.setData('link_target', e.target.value)}
                                    className="admin-search-input"
                                >
                                    <option value="">— выберите —</option>
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name} (id {c.id})
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                        {createForm.data.link_type === 'route' && (
                            <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                                Страница
                                <select
                                    value={createForm.data.link_target}
                                    onChange={(e) => createForm.setData('link_target', e.target.value)}
                                    className="admin-search-input"
                                >
                                    <option value="">— выберите —</option>
                                    {routeOptions.map((r) => (
                                        <option key={r.value} value={r.value}>
                                            {r.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                        {(createForm.data.link_type === 'product' || createForm.data.link_type === 'url') && (
                            <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                                {createForm.data.link_type === 'product' ? 'ID товара' : 'URL или путь'}
                                <input
                                    type="text"
                                    value={createForm.data.link_target}
                                    maxLength={limits.link_target}
                                    onChange={(e) => createForm.setData('link_target', truncateField(e.target.value, limits.link_target))}
                                    className="admin-search-input"
                                />
                            </label>
                        )}
                        <FieldError message={createFieldErrors.link_target || createForm.errors.link_target} />
                        <div style={{ alignSelf: 'end' }}>
                            <button
                                type="submit"
                                className="adm-action-btn adm-btn-view"
                                disabled={
                                    createForm.processing
                                    || createImageValidating
                                    || !!createImageClientError
                                    || !createForm.data.image
                                }
                            >
                                {createImageValidating ? 'Проверяем фото…' : 'Добавить'}
                            </button>
                        </div>
                    </form>
                </div>

                <div className="adm-table-wrap">
                    <table className="adm-table">
                        <thead>
                            <tr>
                                <th>Превью</th>
                                <th>Заголовок</th>
                                <th>Ссылка</th>
                                <th>№ в карусели</th>
                                <th>Активен</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {slides.map((s) =>
                                editingId === s.id ? (
                                    <tr key={s.id}>
                                        <td colSpan={6}>
                                            <div style={{ display: 'grid', gap: 12, padding: 12, maxWidth: 900 }}>
                                                <div style={{width: '100%', height: 'max-content'}}>
                                                    <img
                                                        src={s.image_path}
                                                        alt=""
                                                        style={{ width: '100%', height: '120px', objectFit: 'cover', borderRadius: 8 }}
                                                    />
                                                    <label style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 6 }}>
                                                        Новое изображение (необязательно)
                                                        <input
                                                            type="file"
                                                            accept="image/*"
                                                            onChange={(e) => {
                                                                const input = e.target;
                                                                const f = input.files?.[0] ?? null;
                                                                handleSlideImageChange(f, input, {
                                                                    setError: setEditImageClientError,
                                                                    setValidating: setEditImageValidating,
                                                                    setFile: (file) => editForm.setData('image', file),
                                                                });
                                                            }}
                                                            className="admin-search-input"
                                                        />
                                                        {(editImageClientError || editForm.errors.image) && (
                                                            <span style={{ color: '#b91c1c', fontSize: 12, display: 'block', marginTop: 6 }}>
                                                                {editImageClientError || editForm.errors.image}
                                                            </span>
                                                        )}
                                                    </label>
                                                </div>
                                                
                                                <SlideTextFields
                                                    data={editForm.data}
                                                    setData={editForm.setData}
                                                    limits={limits}
                                                    errors={editFieldErrors}
                                                    serverErrors={editForm.errors}
                                                />
                                                <SlidePositionPicker
                                                    data={editForm.data}
                                                    setData={editForm.setData}
                                                    slides={slides}
                                                    excludeId={s.id}
                                                    errors={editFieldErrors}
                                                    serverErrors={editForm.errors}
                                                />
                                                <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
                                                    <select
                                                        className="admin-search-input"
                                                        style={{ width: 140 }}
                                                        value={editForm.data.is_active}
                                                        onChange={(e) => editForm.setData('is_active', Number(e.target.value))}
                                                    >
                                                        <option value={1}>Активен</option>
                                                        <option value={0}>Выкл</option>
                                                    </select>
                                                    <select
                                                        className="admin-search-input"
                                                        style={{ minWidth: 200 }}
                                                        value={editForm.data.link_type}
                                                        onChange={(e) => editForm.setData('link_type', e.target.value)}
                                                    >
                                                        {linkTypes.map((t) => (
                                                            <option key={t.value} value={t.value}>
                                                                {t.label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                                {editForm.data.link_type === 'category' && (
                                                    <select
                                                        className="admin-search-input"
                                                        value={editForm.data.link_target}
                                                        onChange={(e) => editForm.setData('link_target', e.target.value)}
                                                    >
                                                        <option value="">— категория —</option>
                                                        {categories.map((c) => (
                                                            <option key={c.id} value={c.id}>
                                                                {c.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                )}
                                                {editForm.data.link_type === 'route' && (
                                                    <select
                                                        className="admin-search-input"
                                                        value={editForm.data.link_target}
                                                        onChange={(e) => editForm.setData('link_target', e.target.value)}
                                                    >
                                                        <option value="">— страница —</option>
                                                        {routeOptions.map((r) => (
                                                            <option key={r.value} value={r.value}>
                                                                {r.label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                )}
                                                {(editForm.data.link_type === 'product' || editForm.data.link_type === 'url') && (
                                                    <input
                                                        className="admin-search-input"
                                                        value={editForm.data.link_target}
                                                        maxLength={limits.link_target}
                                                        onChange={(e) => editForm.setData('link_target', truncateField(e.target.value, limits.link_target))}
                                                        placeholder={editForm.data.link_type === 'product' ? 'ID товара' : 'URL'}
                                                    />
                                                )}
                                                <FieldError message={editFieldErrors.link_target || editForm.errors.link_target} />
                                                <div style={{ display: 'flex', gap: 8 }}>
                                                    <button
                                                        type="button"
                                                        className="adm-action-btn adm-btn-view"
                                                        disabled={editImageValidating || !!editImageClientError}
                                                        onClick={() => saveEdit(s.id)}
                                                    >
                                                        {editImageValidating ? 'Проверяем фото…' : 'Сохранить'}
                                                    </button>
                                                    <button type="button" className="adm-action-btn" onClick={() => setEditingId(null)}>
                                                        Отмена
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    <tr key={s.id} style={!s.is_active ? { opacity: 0.55 } : undefined}>
                                        <td>
                                            <img
                                                src={s.image_path}
                                                alt=""
                                                style={{ width: 100, height: 56, objectFit: 'cover', borderRadius: 6 }}
                                            />
                                        </td>
                                        <td>{s.title || '—'}</td>
                                        <td style={{ fontSize: 13, maxWidth: 220, wordBreak: 'break-all' }}>
                                            {s.resolved_href || '—'}
                                        </td>
                                        <td>{carouselIndexById[s.id] ?? '—'}</td>
                                        <td>{s.is_active ? 'да' : 'нет'}</td>
                                        <td>
                                            <button type="button" className="adm-action-btn adm-btn-view" onClick={() => startEdit(s)}>
                                                Изменить
                                            </button>{' '}
                                            <button type="button" className="adm-action-btn" onClick={() => removeSlide(s.id)}>
                                                Удалить
                                            </button>
                                        </td>
                                    </tr>
                                )
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </MainLayout>
    );
}
