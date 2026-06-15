import React, { useEffect, useRef, useState, useMemo } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { IMaskInput } from 'react-imask';
import MainLayout from '@/Layouts/MainLayout';
import '../../../css/pickup/cooperate.css';

const DRAFT_KEY = 'pickup_apply_draft';
const ORG_TYPES = [
    { value: 'ip', label: 'ИП' },
    { value: 'ooo', label: 'ООО' },
    { value: 'self', label: 'Самозанятый' },
];

// ----- Вспомогательные валидации -----
const validatePhone = (phone) => /^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/.test(phone);

const validateINN = (inn, orgType) => {
    const cleaned = inn.replace(/\D/g, '');
    if (orgType === 'ip') return cleaned.length === 12;
    return cleaned.length === 10 || cleaned.length === 12;
};

const validateRequired = (val) => val?.trim().length > 0;
const validateMaxLength = (val, max) => val?.length <= max;
const validateMaxWords = (val, maxWords) => {
    if (!val) return true;
    const words = val.trim().split(/\s+/).filter(w => w.length > 0);
    return words.length <= maxWords;
};
const validateNoLongWords = (val, maxWordLen = 30) => {
    if (!val) return true;
    const words = val.split(/\s+/);
    return !words.some(word => word.length > maxWordLen);
};
const validateHasLetters = (val) => /[a-zA-Zа-яА-Я]/.test(val);
const validateNotOnlyDigits = (val) => !/^\d+$/.test(val.trim());

function defaultFormData(prefill = {}) {
    return {
        consent: false,
        contact_name: prefill.contact_name || '',
        contact_phone: prefill.contact_phone || '+7 (',
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
    const { errors: serverErrors } = usePage().props;
    const [termsOpen, setTermsOpen] = useState(false);
    const [touched, setTouched] = useState({});
    const saveTimer = useRef(null);

    const { data, setData, post, processing } = useForm(loadDraft(prefill));

    // --- Клиентские ошибки ---
    const errors = useMemo(() => {
        const err = {};

        if (touched.contact_name) {
            if (!validateRequired(data.contact_name))
                err.contact_name = 'Укажите ФИО ответственного';
            else if (!validateMaxLength(data.contact_name, 120))
                err.contact_name = 'ФИО не должно превышать 120 символов';
            else if (!validateMaxWords(data.contact_name, 5))
                err.contact_name = 'ФИО должно содержать не более 5 слов';
            else if (!validateHasLetters(data.contact_name))
                err.contact_name = 'ФИО должно содержать буквы';
            else if (!validateNotOnlyDigits(data.contact_name))
                err.contact_name = 'ФИО не может состоять только из цифр';
        }

        if (touched.contact_phone && !validatePhone(data.contact_phone))
            err.contact_phone = 'Номер должен быть в формате +7 (123) 456-78-90';

        if (touched.inn && !validateINN(data.inn, data.org_type))
            err.inn = data.org_type === 'ip' ? 'ИНН ИП — 12 цифр' : 'ИНН юрлица — 10 или 12 цифр';

        if (touched.legal_name) {
            if (!validateRequired(data.legal_name))
                err.legal_name = 'Введите юридическое наименование';
            else if (!validateMaxLength(data.legal_name, 200))
                err.legal_name = 'Наименование не должно превышать 200 символов';
            else if (!validateHasLetters(data.legal_name))
                err.legal_name = 'Наименование должно содержать буквы';
            else if (!validateNotOnlyDigits(data.legal_name))
                err.legal_name = 'Наименование не может состоять только из цифр';
        }

        if (touched.proposed_title) {
            if (!validateRequired(data.proposed_title))
                err.proposed_title = 'Введите название пункта';
            else if (!validateMaxLength(data.proposed_title, 120))
                err.proposed_title = 'Название не должно превышать 120 символов';
            else if (!validateMaxWords(data.proposed_title, 8))
                err.proposed_title = 'Название должно содержать не более 8 слов';
            else if (!validateNoLongWords(data.proposed_title, 30))
                err.proposed_title = 'Название содержит слишком длинное слово (максимум 30 символов)';
            else if (!validateHasLetters(data.proposed_title))
                err.proposed_title = 'Название должно содержать буквы';
            else if (!validateNotOnlyDigits(data.proposed_title))
                err.proposed_title = 'Название не может состоять только из цифр';
        }

        if (touched.proposed_address) {
            if (!validateRequired(data.proposed_address))
                err.proposed_address = 'Введите адрес';
            else if (!validateMaxLength(data.proposed_address, 500))
                err.proposed_address = 'Адрес не должен превышать 500 символов';
            else if (!validateNoLongWords(data.proposed_address, 40))
                err.proposed_address = 'Адрес содержит слишком длинное слово';
            else if (!validateHasLetters(data.proposed_address))
                err.proposed_address = 'Адрес должен содержать буквы';
        }

        if (touched.premises_info && data.premises_info) {
            if (!validateMaxLength(data.premises_info, 300))
                err.premises_info = 'Описание помещения не должно превышать 300 символов';
            if (!validateNoLongWords(data.premises_info, 40))
                err.premises_info = 'Описание содержит слишком длинное слово';
        }

        if (touched.application_comment && data.application_comment) {
            if (!validateMaxLength(data.application_comment, 2000))
                err.application_comment = 'Комментарий не должен превышать 2000 символов';
        }

        if (touched.consent && !data.consent)
            err.consent = 'Необходимо принять условия';

        return err;
    }, [data, touched, data.org_type]);

    // Форма валидна только при отсутствии ошибок и наличии всех обязательных полей
    const isFormValid = useMemo(() => {
        return (
            validateRequired(data.contact_name) &&
            validateMaxLength(data.contact_name, 120) &&
            validateMaxWords(data.contact_name, 5) &&
            validateHasLetters(data.contact_name) &&
            validateNotOnlyDigits(data.contact_name) &&
            validatePhone(data.contact_phone) &&
            validateINN(data.inn, data.org_type) &&
            validateRequired(data.legal_name) &&
            validateMaxLength(data.legal_name, 200) &&
            validateHasLetters(data.legal_name) &&
            validateRequired(data.proposed_title) &&
            validateMaxLength(data.proposed_title, 120) &&
            validateMaxWords(data.proposed_title, 8) &&
            validateNoLongWords(data.proposed_title, 30) &&
            validateHasLetters(data.proposed_title) &&
            validateRequired(data.proposed_address) &&
            validateMaxLength(data.proposed_address, 500) &&
            validateNoLongWords(data.proposed_address, 40) &&
            validateHasLetters(data.proposed_address) &&
            data.consent === true
        );
    }, [data]);

    // Черновик
    useEffect(() => {
        if (saveTimer.current) clearTimeout(saveTimer.current);
        saveTimer.current = setTimeout(() => {
            try {
                sessionStorage.setItem(DRAFT_KEY, JSON.stringify(data));
            } catch { }
        }, 300);
        return () => clearTimeout(saveTimer.current);
    }, [data]);

    const handleBlur = (field) => setTouched(prev => ({ ...prev, [field]: true }));

    // Добавить после useForm и перед другими хуками
    const scrollToFirstError = () => {
        const firstErrorField = document.querySelector('.pickup-cooperate__field .error, .pickup-cooperate__consent .error');
        if (firstErrorField) {
            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstErrorField.focus();
        }
    };

    const submit = (e) => {
        e.preventDefault();
        // Помечаем все поля touched
        const allTouched = Object.keys(data).reduce((acc, key) => ({ ...acc, [key]: true }), {});
        setTouched(allTouched);

        // Проверяем валидность
        if (!isFormValid) {
            // Собираем список ошибок для показа
            const errorMessages = Object.values(errors);
            if (errorMessages.length > 0) {
                alert(`Пожалуйста, исправьте ошибки:\n${errorMessages.join('\n')}`);
                scrollToFirstError();
            } else {
                // Если ошибок нет, но isFormValid = false (например, не все обязательные поля заполнены)
                alert('Заполните все обязательные поля (отмечены звёздочкой) корректно.');
            }
            return;
        }

        post('/pickup/apply', {
            preserveScroll: true,
            onSuccess: () => {
                try { sessionStorage.removeItem(DRAFT_KEY); } catch { }
            },
        });
    };
    const completionPercent = useMemo(() => {
        const fields = [
            validateRequired(data.contact_name) && validateMaxWords(data.contact_name, 5),
            validatePhone(data.contact_phone),
            validateINN(data.inn, data.org_type),
            validateRequired(data.legal_name) && validateMaxLength(data.legal_name, 200),
            validateRequired(data.proposed_title) && validateMaxWords(data.proposed_title, 8),
            validateRequired(data.proposed_address) && validateMaxLength(data.proposed_address, 500),
            data.consent,
        ];
        const filled = fields.filter(Boolean).length;
        return Math.round((filled / fields.length) * 100);
    }, [data]);

    return (
        <MainLayout auth={auth}>
            <Head title="Заявка на пункт выдачи" />

            <div className="pickup-cooperate pickup-apply">
                <Link href="/pickup/partner" className="pickup-apply__back">← К описанию партнёрства</Link>

                <div className="pickup-apply__header">
                    <h1>Станьте партнёром ALVORA</h1>
                    <p className="pickup-cooperate__lead">
                        Заполните анкету — после проверки администратор откроет пункт выдачи.
                        Поля со звёздочкой обязательны.
                    </p>
                </div>

                <div className="pickup-apply__progress">
                    <div className="pickup-apply__progress-bar" style={{ width: `${completionPercent}%` }} />
                    <span className="pickup-apply__progress-text">Заполнено {completionPercent}%</span>
                </div>

                <form className="pickup-cooperate__card pickup-apply__form" onSubmit={submit} noValidate>
                    <h2 className="pickup-apply__section">Контактное лицо</h2>

                    <div className="pickup-cooperate__field">
                        <span>ФИО ответственного *</span>
                        <input
                            type="text"
                            value={data.contact_name}
                            onChange={(e) => setData('contact_name', e.target.value)}
                            onBlur={() => handleBlur('contact_name')}
                            maxLength={120}
                            className={errors.contact_name ? 'error' : ''}
                            placeholder="Иванов Иван Иванович"
                        />
                        {errors.contact_name && <span className="pickup-cooperate__error">{errors.contact_name}</span>}
                        <span className="pickup-cooperate__hint">До 120 символов, не более 5 слов. Только буквы и пробелы.</span>
                    </div>

                    <div className="pickup-cooperate__field">
                        <span>Телефон *</span>
                        <IMaskInput
                            mask="+7 (000) 000-00-00"
                            value={data.contact_phone}
                            onAccept={(value) => setData('contact_phone', value)}
                            onBlur={() => handleBlur('contact_phone')}
                            className={errors.contact_phone ? 'error' : ''}
                            placeholder="+7 (123) 456-78-90"
                        />
                        {errors.contact_phone && <span className="pickup-cooperate__error">{errors.contact_phone}</span>}
                        <span className="pickup-cooperate__hint">На этот номер будут приходить уведомления</span>
                    </div>

                    <h2 className="pickup-apply__section">Организация</h2>

                    <div className="pickup-cooperate__field">
                        <span>Форма *</span>
                        <select value={data.org_type} onChange={(e) => setData('org_type', e.target.value)}>
                            {ORG_TYPES.map((t) => (
                                <option key={t.value} value={t.value}>{t.label}</option>
                            ))}
                        </select>
                    </div>

                    <div className="pickup-cooperate__field">
                        <span>ИНН *</span>
                        <IMaskInput
                            mask={data.org_type === 'ip' ? '000000000000' : '0000000000'}
                            value={data.inn}
                            onAccept={(value) => setData('inn', value)}
                            onBlur={() => handleBlur('inn')}
                            className={errors.inn ? 'error' : ''}
                            placeholder={data.org_type === 'ip' ? '12 цифр' : '10 или 12 цифр'}
                        />
                        {errors.inn && <span className="pickup-cooperate__error">{errors.inn}</span>}
                        <span className="pickup-cooperate__hint">Только цифры</span>
                    </div>

                    <div className="pickup-cooperate__field">
                        <span>Юридическое наименование *</span>
                        <input
                            type="text"
                            value={data.legal_name}
                            onChange={(e) => setData('legal_name', e.target.value)}
                            onBlur={() => handleBlur('legal_name')}
                            maxLength={100}
                            className={errors.legal_name ? 'error' : ''}
                            placeholder={data.org_type === 'ip' ? 'Иванов Иван Иванович' : 'ООО "Ромашка"'}
                        />
                        {errors.legal_name && <span className="pickup-cooperate__error">{errors.legal_name}</span>}
                        <span className="pickup-cooperate__hint">До 100 символов, должно содержать буквы</span>
                    </div>

                    <h2 className="pickup-apply__section">Пункт выдачи</h2>

                    <div className="pickup-cooperate__field">
                        <span>Название на карте *</span>
                        <input
                            type="text"
                            value={data.proposed_title}
                            onChange={(e) => setData('proposed_title', e.target.value)}
                            onBlur={() => handleBlur('proposed_title')}
                            maxLength={120}
                            className={errors.proposed_title ? 'error' : ''}
                            placeholder="ПВЗ на Ленина, 15"
                        />
                        {errors.proposed_title && <span className="pickup-cooperate__error">{errors.proposed_title}</span>}
                        <span className="pickup-cooperate__hint">
                            До 120 символов, не более 8 слов. Каждое слово не длиннее 30 символов. Только осмысленный текст.
                        </span>
                    </div>

                    <div className="pickup-cooperate__field">
                        <span>Регион</span>
                        <select value={data.proposed_region_id} onChange={(e) => setData('proposed_region_id', e.target.value)}>
                            <option value="">Выберите регион</option>
                            {regions.map((r) => (
                                <option key={r.id} value={r.id}>{r.name}</option>
                            ))}
                        </select>
                    </div>

                    <div className="pickup-cooperate__field">
                        <span>Адрес *</span>
                        <input
                            type="text"
                            value={data.proposed_address}
                            onChange={(e) => setData('proposed_address', e.target.value)}
                            onBlur={() => handleBlur('proposed_address')}
                            maxLength={500}
                            className={errors.proposed_address ? 'error' : ''}
                            placeholder="г. Москва, ул. Тверская, д. 12, подъезд 3"
                        />
                        {errors.proposed_address && <span className="pickup-cooperate__error">{errors.proposed_address}</span>}
                        <span className="pickup-cooperate__hint">До 500 символов, без слишком длинных слов</span>
                    </div>

                    <div className="pickup-cooperate__field">
                        <span>Помещение (этаж, площадь, вход)</span>
                        <input
                            type="text"
                            value={data.premises_info}
                            onChange={(e) => setData('premises_info', e.target.value)}
                            onBlur={() => handleBlur('premises_info')}
                            maxLength={200}
                            className={errors.premises_info ? 'error' : ''}
                            placeholder="1 этаж, 45 м², отдельный вход со двора"
                        />
                        {errors.premises_info && <span className="pickup-cooperate__error">{errors.premises_info}</span>}
                        <span className="pickup-cooperate__hint">Необязательно, до 200 символов</span>
                    </div>

                    <div className="pickup-cooperate__field">
                        <span>Комментарий к заявке</span>
                        <textarea
                            rows={3}
                            value={data.application_comment}
                            onChange={(e) => setData('application_comment', e.target.value)}
                            onBlur={() => handleBlur('application_comment')}
                            maxLength={1000}
                            placeholder="Дополнительная информация, часы работы, ссылки на соцсети и т.д."
                        />
                        {errors.application_comment && <span className="pickup-cooperate__error">{errors.application_comment}</span>}
                        <span className="pickup-cooperate__hint">Максимум 1000 символов</span>
                    </div>

                    <label className="pickup-cooperate__consent">
                        <input
                            type="checkbox"
                            checked={data.consent}
                            onChange={(e) => setData('consent', e.target.checked)}
                            onBlur={() => handleBlur('consent')}
                        />
                        <span>
                            Принимаю{' '}
                            <button type="button" className="pickup-cooperate__link-btn" onClick={() => setTermsOpen(true)}>
                                условия платформы
                            </button>
                            {' '}и даю согласие на проверку указанных данных
                        </span>
                    </label>
                    {errors.consent && <span className="pickup-cooperate__error">{errors.consent}</span>}
                    {serverErrors.form && <span className="pickup-cooperate__error">{serverErrors.form}</span>}

                    <button type="submit" className="pickup-cooperate__btn" disabled={processing}>
                        {processing ? <span className="pickup-apply__spinner" /> : 'Отправить на проверку'}
                    </button>
                </form>

                {termsOpen && (
                    <div className="pickup-terms-modal" role="dialog">
                        <div className="pickup-terms-modal__backdrop" onClick={() => setTermsOpen(false)} />
                        <div className="pickup-terms-modal__box">
                            <h3>Условия платформы</h3>
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
            </div>
        </MainLayout>
    );
}