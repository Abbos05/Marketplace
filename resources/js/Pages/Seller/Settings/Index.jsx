import React, { useState, useRef, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import SellerLayout from '@/Layouts/SellerLayout';
import '../../../../css/seller/settings.css';

// ── Day labels ──────────────────────────────────────────────────────
const DAY_LABELS = {
    mon: 'Понедельник',
    tue: 'Вторник',
    wed: 'Среда',
    thu: 'Четверг',
    fri: 'Пятница',
    sat: 'Суббота',
    sun: 'Воскресенье',
};
const DAY_ORDER = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

// ── Password rule helper ────────────────────────────────────────────
function PasswordRule({ met, label }) {
    return (
        <div className={`set-password-rule ${met ? 'set-password-rule--met' : ''}`}>
            {label}
        </div>
    );
}

// ── Tab: Shop ───────────────────────────────────────────────────────
function ShopTab({ sellerProfile, errors, submitting, setSubmitting }) {
    const [form, setForm] = useState({
        shop_name:      sellerProfile?.shop_name      ?? '',
        description:    sellerProfile?.description    ?? '',
        inn:            sellerProfile?.inn             ?? '',
        legal_address:  sellerProfile?.legal_address  ?? '',
        pickup_address: sellerProfile?.pickup_address ?? '',
        working_hours:  sellerProfile?.working_hours  ?? buildDefaultHours(),
    });

    function buildDefaultHours() {
        const result = {};
        DAY_ORDER.forEach(d => {
            const isWeekend = d === 'sat' || d === 'sun';
            result[d] = { open: !isWeekend, from: isWeekend ? '' : '09:00', to: isWeekend ? '' : '18:00' };
        });
        return result;
    }

    const setWH = (day, field, value) => {
        setForm(prev => ({
            ...prev,
            working_hours: {
                ...prev.working_hours,
                [day]: { ...prev.working_hours[day], [field]: value },
            },
        }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        router.post(route('seller.settings.shop'), form, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
        });
    };

    const innSet = !!sellerProfile?.inn;

    return (
        <form onSubmit={handleSubmit} className="set-form">
            {/* Shop name */}
            <div className="set-field-group">
                <label className="set-label">Название магазина *</label>
                <input
                    type="text"
                    className={`set-input ${errors.shop_name ? 'has-error' : ''}`}
                    value={form.shop_name}
                    onChange={e => setForm(p => ({ ...p, shop_name: e.target.value }))}
                    maxLength={120}
                    required
                />
                {errors.shop_name && <span className="set-error">{errors.shop_name}</span>}
            </div>

            {/* Description */}
            <div className="set-field-group">
                <label className="set-label set-label--optional">Описание магазина</label>
                <textarea
                    className={`set-textarea ${errors.description ? 'has-error' : ''}`}
                    value={form.description}
                    onChange={e => setForm(p => ({ ...p, description: e.target.value }))}
                    maxLength={2000}
                    rows={4}
                    placeholder="Расскажите покупателям о вашем магазине..."
                />
                {errors.description && <span className="set-error">{errors.description}</span>}
            </div>

            {/* INN */}
            <div className="set-field-group">
                <label className="set-label set-label--optional">ИНН</label>
                <input
                    type="text"
                    className={`set-input ${errors.inn ? 'has-error' : ''}`}
                    value={form.inn}
                    onChange={e => setForm(p => ({ ...p, inn: e.target.value }))}
                    maxLength={12}
                    readOnly={innSet}
                    placeholder="10 или 12 цифр"
                />
                {innSet && <span className="set-hint">ИНН нельзя изменить после сохранения.</span>}
                {errors.inn && <span className="set-error">{errors.inn}</span>}
            </div>

            {/* Addresses */}
            <div className="set-row-2">
                <div className="set-field-group">
                    <label className="set-label">Адрес самовывоза *</label>
                    <input
                        type="text"
                        className={`set-input ${errors.pickup_address ? 'has-error' : ''}`}
                        value={form.pickup_address}
                        onChange={e => setForm(p => ({ ...p, pickup_address: e.target.value }))}
                        maxLength={300}
                        required
                    />
                    {errors.pickup_address && <span className="set-error">{errors.pickup_address}</span>}
                </div>
                <div className="set-field-group">
                    <label className="set-label set-label--optional">Юридический адрес</label>
                    <input
                        type="text"
                        className={`set-input ${errors.legal_address ? 'has-error' : ''}`}
                        value={form.legal_address}
                        onChange={e => setForm(p => ({ ...p, legal_address: e.target.value }))}
                        maxLength={300}
                    />
                    {errors.legal_address && <span className="set-error">{errors.legal_address}</span>}
                </div>
            </div>

            {/* Working hours */}
            <div className="set-field-group">
                <label className="set-label set-label--optional">Режим работы</label>
                <div className="set-working-hours">
                    {DAY_ORDER.map(day => {
                        const wh     = form.working_hours[day] ?? { open: false, from: '', to: '' };
                        const isOpen = !!wh.open;
                        return (
                            <div key={day} className={`set-wh-row ${!isOpen ? 'set-wh-row--closed' : ''}`}>
                                <span className={`set-wh-day ${!isOpen ? 'set-wh-day--closed' : ''}`}>
                                    {DAY_LABELS[day]}
                                </span>
                                <input
                                    type="checkbox"
                                    className="set-wh-check"
                                    checked={isOpen}
                                    onChange={e => setWH(day, 'open', e.target.checked)}
                                />
                                <input
                                    type="time"
                                    className="set-wh-time"
                                    value={wh.from}
                                    disabled={!isOpen}
                                    onChange={e => setWH(day, 'from', e.target.value)}
                                />
                                <input
                                    type="time"
                                    className="set-wh-time"
                                    value={wh.to}
                                    disabled={!isOpen}
                                    onChange={e => setWH(day, 'to', e.target.value)}
                                />
                            </div>
                        );
                    })}
                </div>
            </div>

            <button type="submit" className="set-save-btn" disabled={submitting}>
                {submitting ? 'Сохранение...' : 'Сохранить магазин'}
            </button>
        </form>
    );
}

// ── Tab: Account ────────────────────────────────────────────────────
function AccountTab({ user, errors, submitting, setSubmitting }) {
    const [form, setForm] = useState({
        name:   user.name  ?? '',
        email:  user.email ?? '',
        phone:  user.phone ?? '',
        avatar: null,
    });
    const [preview, setPreview] = useState(user.avatar ?? null);
    const fileRef = useRef(null);

    const handleFile = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        setForm(p => ({ ...p, avatar: file }));
        setPreview(URL.createObjectURL(file));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);

        const fd = new FormData();
        fd.append('name',  form.name);
        fd.append('email', form.email);
        if (form.phone) fd.append('phone', form.phone);
        if (form.avatar) fd.append('avatar', form.avatar);
        fd.append('_method', 'POST');

        router.post(route('seller.settings.account'), fd, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
        });
    };

    const initials = (user.name ?? 'U').charAt(0).toUpperCase();

    return (
        <form onSubmit={handleSubmit} className="set-form">
            {/* Avatar */}
            <div className="set-field-group">
                <label className="set-label set-label--optional">Фото профиля</label>
                <div className="set-avatar-block">
                    {preview ? (
                        <img src={preview} alt="avatar" className="set-avatar-img" />
                    ) : (
                        <div className="set-avatar-placeholder">{initials}</div>
                    )}
                    <div className="set-avatar-info">
                        <h4>Аватар продавца</h4>
                        <p>JPG, PNG или WEBP, не более 2 МБ</p>
                        <button type="button" className="set-avatar-btn" onClick={() => fileRef.current?.click()}>
                            Загрузить фото
                        </button>
                    </div>
                    <input
                        ref={fileRef}
                        type="file"
                        accept="image/jpeg,image/jpg,image/png,image/webp"
                        style={{ display: 'none' }}
                        onChange={handleFile}
                    />
                </div>
                {errors.avatar && <span className="set-error">{errors.avatar}</span>}
            </div>

            {/* Name */}
            <div className="set-field-group">
                <label className="set-label">Имя *</label>
                <input
                    type="text"
                    className={`set-input ${errors.name ? 'has-error' : ''}`}
                    value={form.name}
                    onChange={e => setForm(p => ({ ...p, name: e.target.value }))}
                    maxLength={80}
                    required
                />
                {errors.name && <span className="set-error">{errors.name}</span>}
            </div>

            {/* Email + Phone */}
            <div className="set-row-2">
                <div className="set-field-group">
                    <label className="set-label">Email *</label>
                    <input
                        type="email"
                        className={`set-input ${errors.email ? 'has-error' : ''}`}
                        value={form.email}
                        onChange={e => setForm(p => ({ ...p, email: e.target.value }))}
                        required
                    />
                    {errors.email && <span className="set-error">{errors.email}</span>}
                </div>
                <div className="set-field-group">
                    <label className="set-label set-label--optional">Телефон</label>
                    <input
                        type="tel"
                        className={`set-input ${errors.phone ? 'has-error' : ''}`}
                        value={form.phone}
                        onChange={e => setForm(p => ({ ...p, phone: e.target.value }))}
                        placeholder="+7 999 000-00-00"
                        maxLength={20}
                    />
                    {errors.phone && <span className="set-error">{errors.phone}</span>}
                </div>
            </div>

            <button type="submit" className="set-save-btn" disabled={submitting}>
                {submitting ? 'Сохранение...' : 'Сохранить профиль'}
            </button>
        </form>
    );
}

// ── Tab: Security ───────────────────────────────────────────────────
function SecurityTab({ errors, submitting, setSubmitting }) {
    const [form, setForm] = useState({
        current_password:      '',
        password:              '',
        password_confirmation: '',
    });

    const pw = form.password;
    const rules = [
        { met: pw.length >= 8,              label: 'Не менее 8 символов' },
        { met: /[A-Z]/.test(pw),            label: 'Хотя бы одна заглавная буква' },
        { met: /[0-9]/.test(pw),            label: 'Хотя бы одна цифра' },
        { met: pw === form.password_confirmation && pw.length > 0, label: 'Пароли совпадают' },
    ];

    const handleSubmit = (e) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        router.post(route('seller.settings.password'), form, {
            preserveScroll: true,
            onFinish: () => {
                setSubmitting(false);
                setForm({ current_password: '', password: '', password_confirmation: '' });
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="set-form">
            <div className="set-field-group">
                <label className="set-label">Текущий пароль *</label>
                <input
                    type="password"
                    className={`set-input ${errors.current_password ? 'has-error' : ''}`}
                    value={form.current_password}
                    onChange={e => setForm(p => ({ ...p, current_password: e.target.value }))}
                    autoComplete="current-password"
                    required
                />
                {errors.current_password && <span className="set-error">{errors.current_password}</span>}
            </div>

            <div className="set-field-group">
                <label className="set-label">Новый пароль *</label>
                <input
                    type="password"
                    className={`set-input ${errors.password ? 'has-error' : ''}`}
                    value={form.password}
                    onChange={e => setForm(p => ({ ...p, password: e.target.value }))}
                    autoComplete="new-password"
                    required
                />
                {errors.password && <span className="set-error">{errors.password}</span>}
            </div>

            <div className="set-field-group">
                <label className="set-label">Подтверждение пароля *</label>
                <input
                    type="password"
                    className={`set-input ${errors.password_confirmation ? 'has-error' : ''}`}
                    value={form.password_confirmation}
                    onChange={e => setForm(p => ({ ...p, password_confirmation: e.target.value }))}
                    autoComplete="new-password"
                    required
                />
                {errors.password_confirmation && <span className="set-error">{errors.password_confirmation}</span>}
            </div>

            {/* Password rules */}
            {form.password.length > 0 && (
                <div className="set-password-rules">
                    {rules.map((r, i) => <PasswordRule key={i} met={r.met} label={r.label} />)}
                </div>
            )}

            <button type="submit" className="set-save-btn" disabled={submitting}>
                {submitting ? 'Сохранение...' : 'Изменить пароль'}
            </button>
        </form>
    );
}

// ── Main page ───────────────────────────────────────────────────────
const TABS = [
    { key: 'shop',     label: 'Магазин' },
    { key: 'account',  label: 'Профиль' },
    { key: 'security', label: 'Безопасность' },
];

export default function Index({ user, sellerProfile }) {
    const { props } = usePage();
    const flash  = props.flash  ?? {};
    const errors = props.errors ?? {};

    // Restore the active tab from flash (after redirect-back)
    const [activeTab, setActiveTab]   = useState(flash.tab ?? 'shop');
    const [submitting, setSubmitting] = useState(false);

    // Switch tab when flash.tab changes (after form submit)
    useEffect(() => {
        if (flash.tab) setActiveTab(flash.tab);
    }, [flash.tab]);

    return (
        <SellerLayout title="Настройки">
            <Head title="Настройки" />

            {flash.success && <div className="set-flash set-flash--success">{flash.success}</div>}
            {flash.error   && <div className="set-flash set-flash--error">{flash.error}</div>}

            {/* Tab bar */}
            <div className="set-tabs">
                {TABS.map(t => (
                    <button
                        key={t.key}
                        type="button"
                        className={`set-tab ${activeTab === t.key ? 'set-tab--active' : ''}`}
                        onClick={() => setActiveTab(t.key)}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            <div className="set-layout">
                {/* Main form column */}
                <div className="set-card">
                    <div className="set-card__header">
                        <p className="set-card__title">
                            {activeTab === 'shop'     && 'Данные магазина'}
                            {activeTab === 'account'  && 'Личные данные'}
                            {activeTab === 'security' && 'Смена пароля'}
                        </p>
                        <p className="set-card__sub">
                            {activeTab === 'shop'     && 'Публичная информация о вашем магазине'}
                            {activeTab === 'account'  && 'Контактные данные и фото профиля'}
                            {activeTab === 'security' && 'Регулярно меняйте пароль для безопасности'}
                        </p>
                    </div>
                    <div className="set-card__body">
                        {activeTab === 'shop' && (
                            <ShopTab
                                sellerProfile={sellerProfile}
                                errors={errors}
                                submitting={submitting}
                                setSubmitting={setSubmitting}
                            />
                        )}
                        {activeTab === 'account' && (
                            <AccountTab
                                user={user}
                                errors={errors}
                                submitting={submitting}
                                setSubmitting={setSubmitting}
                            />
                        )}
                        {activeTab === 'security' && (
                            <SecurityTab
                                errors={errors}
                                submitting={submitting}
                                setSubmitting={setSubmitting}
                            />
                        )}
                    </div>
                </div>

                {/* Right info panel */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                    <div className="set-info-card">
                        <div className="set-info-row">
                            <span className="set-info-row__label">Аккаунт</span>
                            <span className="set-info-row__value">{user.name}</span>
                        </div>
                        <div className="set-info-divider" />
                        <div className="set-info-row">
                            <span className="set-info-row__label">Email</span>
                            <span className="set-info-row__value">{user.email}</span>
                        </div>
                        {user.phone && (
                            <>
                                <div className="set-info-divider" />
                                <div className="set-info-row">
                                    <span className="set-info-row__label">Телефон</span>
                                    <span className="set-info-row__value">{user.phone}</span>
                                </div>
                            </>
                        )}
                        {sellerProfile?.shop_name && (
                            <>
                                <div className="set-info-divider" />
                                <div className="set-info-row">
                                    <span className="set-info-row__label">Магазин</span>
                                    <span className="set-info-row__value">{sellerProfile.shop_name}</span>
                                </div>
                            </>
                        )}
                        {sellerProfile?.inn && (
                            <>
                                <div className="set-info-divider" />
                                <div className="set-info-row">
                                    <span className="set-info-row__label">ИНН</span>
                                    <span className="set-info-row__value">{sellerProfile.inn}</span>
                                </div>
                            </>
                        )}
                    </div>

                    {/* Tips */}
                    <div className="set-card" style={{ padding: '18px 20px' }}>
                        <p style={{ fontSize: 13, fontWeight: 700, color: '#1e293b', marginBottom: 10 }}>Советы</p>
                        <ul style={{ fontSize: 13, color: '#64748b', lineHeight: 1.6, paddingLeft: 16, margin: 0 }}>
                            <li>Заполните описание магазина — это повышает доверие покупателей.</li>
                            <li>Укажите актуальный адрес самовывоза.</li>
                            <li>Используйте качественную фотографию профиля.</li>
                            <li>Меняйте пароль минимум раз в 6 месяцев.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </SellerLayout>
    );
}
