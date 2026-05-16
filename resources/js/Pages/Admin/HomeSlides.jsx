import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/admin/dashboard.css';

/** Баннер: только «альбом»; те же правила проверяются на сервере. */
function validateSlideBannerFile(file) {
    if (!file?.type?.startsWith('image/')) {
        return Promise.resolve(null);
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
    sort_order: 0,
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
}) {
    const createForm = useForm({ ...defaultCreate });
    const [createImageClientError, setCreateImageClientError] = useState(null);
    const [editImageClientError, setEditImageClientError] = useState(null);

    const submitCreate = (e) => {
        e.preventDefault();
        if (createImageClientError) return;
        createForm.post(route('admin.home-slides.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                createForm.setData({ ...defaultCreate });
                setCreateImageClientError(null);
            },
        });
    };

    const [editingId, setEditingId] = useState(null);
    const editForm = useForm({
        title: '',
        description: '',
        button_text: '',
        sort_order: 0,
        is_active: 1,
        link_type: 'none',
        link_target: '',
        image: null,
    });

    const startEdit = (s) => {
        setEditingId(s.id);
        setEditImageClientError(null);
        editForm.setData({
            title: s.title ?? '',
            description: s.description ?? '',
            button_text: s.button_text ?? '',
            sort_order: s.sort_order ?? 0,
            is_active: s.is_active ? 1 : 0,
            link_type: s.link_type ?? 'none',
            link_target: s.link_target != null ? String(s.link_target) : '',
            image: null,
        });
        editForm.clearErrors();
    };

    const saveEdit = (id) => {
        if (editImageClientError) return;
        const fd = new FormData();
        fd.append('title', editForm.data.title ?? '');
        fd.append('description', editForm.data.description ?? '');
        fd.append('button_text', editForm.data.button_text ?? '');
        fd.append('sort_order', String(editForm.data.sort_order ?? 0));
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
            },
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
                    <strong>Фото баннера:</strong> только горизонтальное (ширина больше высоты), соотношение сторон не уже чем примерно 5:4 (1,25:1), минимум 640×200 px. Вертикальные и квадратные обложки не принимаются.
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
                                    setCreateImageClientError(null);
                                    createForm.setData('image', f);
                                    if (!f) return;
                                    void validateSlideBannerFile(f).then((err) => {
                                        if (err) {
                                            setCreateImageClientError(err);
                                            createForm.setData('image', null);
                                            input.value = '';
                                        }
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
                        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                            Заголовок
                            <input
                                type="text"
                                value={createForm.data.title}
                                onChange={(e) => createForm.setData('title', e.target.value)}
                                className="admin-search-input"
                            />
                        </label>
                        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                            Описание
                            <textarea
                                value={createForm.data.description}
                                onChange={(e) => createForm.setData('description', e.target.value)}
                                className="admin-search-input"
                                rows={3}
                            />
                        </label>
                        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                            Текст кнопки
                            <input
                                type="text"
                                value={createForm.data.button_text}
                                onChange={(e) => createForm.setData('button_text', e.target.value)}
                                className="admin-search-input"
                            />
                        </label>
                        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                            Порядок
                            <input
                                type="number"
                                min={0}
                                value={createForm.data.sort_order}
                                onChange={(e) => createForm.setData('sort_order', Number(e.target.value))}
                                className="admin-search-input"
                            />
                        </label>
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
                                    onChange={(e) => createForm.setData('link_target', e.target.value)}
                                    className="admin-search-input"
                                />
                            </label>
                        )}
                        <div style={{ alignSelf: 'end' }}>
                            <button type="submit" className="adm-action-btn adm-btn-view" disabled={createForm.processing}>
                                Добавить
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
                                <th>Порядок</th>
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
                                                <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
                                                    <img
                                                        src={s.image_path}
                                                        alt=""
                                                        style={{ width: 120, height: 68, objectFit: 'cover', borderRadius: 8 }}
                                                    />
                                                    <label style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 6 }}>
                                                        Новое изображение (необязательно)
                                                        <input
                                                            type="file"
                                                            accept="image/*"
                                                            onChange={(e) => {
                                                                const input = e.target;
                                                                const f = input.files?.[0] ?? null;
                                                                setEditImageClientError(null);
                                                                editForm.setData('image', f);
                                                                if (!f) return;
                                                                void validateSlideBannerFile(f).then((err) => {
                                                                    if (err) {
                                                                        setEditImageClientError(err);
                                                                        editForm.setData('image', null);
                                                                        input.value = '';
                                                                    }
                                                                });
                                                            }}
                                                            className="admin-search-input"
                                                        />
                                                        {editImageClientError && (
                                                            <span style={{ color: '#b91c1c', fontSize: 12, display: 'block', marginTop: 6 }}>
                                                                {editImageClientError}
                                                            </span>
                                                        )}
                                                    </label>
                                                </div>
                                                <input
                                                    className="admin-search-input"
                                                    placeholder="Заголовок"
                                                    value={editForm.data.title}
                                                    onChange={(e) => editForm.setData('title', e.target.value)}
                                                />
                                                <textarea
                                                    className="admin-search-input"
                                                    placeholder="Описание"
                                                    rows={2}
                                                    value={editForm.data.description}
                                                    onChange={(e) => editForm.setData('description', e.target.value)}
                                                />
                                                <input
                                                    className="admin-search-input"
                                                    placeholder="Текст кнопки"
                                                    value={editForm.data.button_text}
                                                    onChange={(e) => editForm.setData('button_text', e.target.value)}
                                                />
                                                <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
                                                    <input
                                                        type="number"
                                                        className="admin-search-input"
                                                        style={{ width: 120 }}
                                                        placeholder="Порядок"
                                                        value={editForm.data.sort_order}
                                                        onChange={(e) => editForm.setData('sort_order', Number(e.target.value))}
                                                    />
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
                                                        onChange={(e) => editForm.setData('link_target', e.target.value)}
                                                        placeholder={editForm.data.link_type === 'product' ? 'ID товара' : 'URL'}
                                                    />
                                                )}
                                                <div style={{ display: 'flex', gap: 8 }}>
                                                    <button type="button" className="adm-action-btn adm-btn-view" onClick={() => saveEdit(s.id)}>
                                                        Сохранить
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
                                        <td>{s.sort_order}</td>
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
