import React, { useEffect, useRef, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/pickup/cooperate.css';

const DRAFT_KEY = 'pickup_apply_draft';

const ORG_TYPES = [
    { value: 'ip', label: 'ИП' },
    { value: 'ooo', label: 'ООО' },
    { value: 'self', label: 'Самозанятый' },
];

function defaultFormData(prefill = {}) {
    return {
        consent: false,
        contact_name: prefill.contact_name || '',
        contact_phone: prefill.contact_phone || '',
        inn: '',
        org_type: 'ip',
        legal_name: '',
        proposed_title: '',
        proposed_address: '',
        proposed_region_id: '',
        premises_info: '',
        application_comment: '',
    };
}

function loadDraft(prefill) {
    try {
        const raw = sessionStorage.getItem(DRAFT_KEY);
        if (!raw) return defaultFormData(prefill);
        const parsed = JSON.parse(raw);
        return { ...defaultFormData(prefill), ...parsed, consent: !!parsed.consent };
    } catch {
        return defaultFormData(prefill);
    }
}

export default function Apply({ auth, regions = [], prefill = {} }) {
    const { errors } = usePage().props;
    const [termsOpen, setTermsOpen] = useState(false);
    const saveTimer = useRef(null);

    const { data, setData, post, processing } = useForm(loadDraft(prefill));

    useEffect(() => {
        if (saveTimer.current) clearTimeout(saveTimer.current);
        saveTimer.current = setTimeout(() => {
            try {
                sessionStorage.setItem(DRAFT_KEY, JSON.stringify(data));
            } catch {
                /* ignore */
            }
        }, 300);
        return () => {
            if (saveTimer.current) clearTimeout(saveTimer.current);
        };
    }, [data]);

    const submit = (e) => {
        e.preventDefault();
        post('/pickup/apply', {
            preserveScroll: true,
            onSuccess: () => {
                try {
                    sessionStorage.removeItem(DRAFT_KEY);
                } catch {
                    /* ignore */
                }
            },
        });
    };

    return (
        <MainLayout auth={auth}>
            <Head title="Заявка на пункт выдачи" />

            <div className="pickup-cooperate pickup-apply">
                <Link href="/pickup/partner" className="pickup-apply__back">← К описанию партнёрства</Link>
                <h1>Анкета партнёра ПВЗ</h1>
                <p className="pickup-cooperate__lead">
                    Все поля обязательны для рассмотрения. После проверки администратор откроет пункт на платформе.
                </p>

                <form className="pickup-cooperate__card pickup-apply__form" onSubmit={submit}>
                    <h2 className="pickup-apply__section">Контактное лицо</h2>
                    <label className="pickup-cooperate__field">
                        <span>ФИО ответственного *</span>
                        <input type="text" value={data.contact_name} onChange={(e) => setData('contact_name', e.target.value)} required />
                        {errors.contact_name && <span className="pickup-cooperate__error">{errors.contact_name}</span>}
                    </label>
                    <label className="pickup-cooperate__field">
                        <span>Телефон *</span>
                        <input type="tel" value={data.contact_phone} onChange={(e) => setData('contact_phone', e.target.value)} required />
                        {errors.contact_phone && <span className="pickup-cooperate__error">{errors.contact_phone}</span>}
                    </label>

                    <h2 className="pickup-apply__section">Организация</h2>
                    <label className="pickup-cooperate__field">
                        <span>Форма *</span>
                        <select value={data.org_type} onChange={(e) => setData('org_type', e.target.value)}>
                            {ORG_TYPES.map((t) => (
                                <option key={t.value} value={t.value}>{t.label}</option>
                            ))}
                        </select>
                    </label>
                    <label className="pickup-cooperate__field">
                        <span>ИНН *</span>
                        <input type="text" value={data.inn} onChange={(e) => setData('inn', e.target.value)} maxLength={12} required />
                        {errors.inn && <span className="pickup-cooperate__error">{errors.inn}</span>}
                    </label>
                    <label className="pickup-cooperate__field">
                        <span>Юридическое наименование *</span>
                        <input type="text" value={data.legal_name} onChange={(e) => setData('legal_name', e.target.value)} required />
                        {errors.legal_name && <span className="pickup-cooperate__error">{errors.legal_name}</span>}
                    </label>

                    <h2 className="pickup-apply__section">Пункт выдачи</h2>
                    <label className="pickup-cooperate__field">
                        <span>Название на карте *</span>
                        <input type="text" value={data.proposed_title} onChange={(e) => setData('proposed_title', e.target.value)} required />
                        {errors.proposed_title && <span className="pickup-cooperate__error">{errors.proposed_title}</span>}
                    </label>
                    <label className="pickup-cooperate__field">
                        <span>Адрес *</span>
                        <input type="text" value={data.proposed_address} onChange={(e) => setData('proposed_address', e.target.value)} required />
                        {errors.proposed_address && <span className="pickup-cooperate__error">{errors.proposed_address}</span>}
                    </label>
                    <label className="pickup-cooperate__field">
                        <span>Регион</span>
                        <select value={data.proposed_region_id} onChange={(e) => setData('proposed_region_id', e.target.value)}>
                            <option value="">Выберите регион</option>
                            {regions.map((r) => (
                                <option key={r.id} value={r.id}>{r.name}</option>
                            ))}
                        </select>
                        {errors.proposed_region_id && <span className="pickup-cooperate__error">{errors.proposed_region_id}</span>}
                    </label>
                    <label className="pickup-cooperate__field">
                        <span>Помещение (этаж, площадь, вход)</span>
                        <input type="text" value={data.premises_info} onChange={(e) => setData('premises_info', e.target.value)} />
                    </label>
                    <label className="pickup-cooperate__field">
                        <span>Комментарий к заявке</span>
                        <textarea
                            rows={3}
                            value={data.application_comment}
                            onChange={(e) => setData('application_comment', e.target.value)}
                        />
                    </label>

                    <label className="pickup-cooperate__consent">
                        <input type="checkbox" checked={data.consent} onChange={(e) => setData('consent', e.target.checked)} />
                        <span>
                            Принимаю{' '}
                            <button type="button" className="pickup-cooperate__link-btn" onClick={() => setTermsOpen(true)}>
                                условия платформы
                            </button>
                            {' '}и даю согласие на проверку указанных данных
                        </span>
                    </label>
                    {errors.consent && <span className="pickup-cooperate__error">{errors.consent}</span>}
                    {errors.form && <span className="pickup-cooperate__error">{errors.form}</span>}

                    <button type="submit" className="pickup-cooperate__btn" disabled={processing}>
                        {processing ? 'Отправка…' : 'Отправить на проверку'}
                    </button>
                </form>
            </div>

            {termsOpen && (
                <div className="pickup-terms-modal" role="dialog" aria-modal="true" aria-labelledby="pickup-terms-title">
                    <div className="pickup-terms-modal__backdrop" onClick={() => setTermsOpen(false)} />
                    <div className="pickup-terms-modal__box">
                        <h3 id="pickup-terms-title">Условия платформы</h3>
                        <p>
                            Ознакомьтесь с правилами маркетплейса ALVORA: оформление заказов, выдача товаров,
                            ответственность оператора пункта выдачи. Полный текст — на отдельной странице.
                        </p>
                        <div className="pickup-terms-modal__actions">
                            <a href="/terms" target="_blank" rel="noopener noreferrer" className="pickup-cooperate__btn pickup-cooperate__btn--outline">
                                Прочитать полностью
                            </a>
                            <button type="button" className="pickup-cooperate__btn" onClick={() => setTermsOpen(false)}>
                                Закрыть
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </MainLayout>
    );
}
