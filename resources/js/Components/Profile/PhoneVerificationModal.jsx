import React, { useEffect, useState } from 'react';
import { useForm, router } from '@inertiajs/react';

/**
 * Модал верификации профиля: имя, email и обязательная привязка телефона.
 * Телефон сохраняется только после подтверждения кода.
 */
export default function PhoneVerificationModal({ isOpen, onClose, auth, onSuccess }) {
  const needsPhone = !auth?.user?.phone;
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [phoneStep, setPhoneStep] = useState('phone');
  const [phoneError, setPhoneError] = useState('');
  const [phoneInfo, setPhoneInfo] = useState('');
  const [phoneProcessing, setPhoneProcessing] = useState(false);

  const { data, setData, post, processing, errors, reset } = useForm({
    name:      auth?.user?.name      || '',
    last_name: auth?.user?.last_name || '',
    email:     auth?.user?.email     || '',
  });

  useEffect(() => {
    if (!isOpen) return;

    setData({
      name: auth?.user?.name || '',
      last_name: auth?.user?.last_name || '',
      email: auth?.user?.email || '',
    });
    setPhone('');
    setCode('');
    setPhoneStep('phone');
    setPhoneError('');
    setPhoneInfo('');
  }, [isOpen, auth?.user?.name, auth?.user?.last_name, auth?.user?.email]);

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

  const normalizePhone = (value) => {
    let digits = (value || '').replace(/\D/g, '');
    if (digits.startsWith('8')) digits = '7' + digits.slice(1);
    if (digits && !digits.startsWith('7')) digits = '7' + digits;
    return digits.slice(0, 11);
  };

  const formatPhone = (value) => {
    const digits = normalizePhone(value);
    if (!digits) return '';
    let formatted = '+7';
    if (digits.length > 1) formatted += ' ' + digits.substring(1, 4);
    if (digits.length > 4) formatted += ' ' + digits.substring(4, 7);
    if (digits.length > 7) formatted += ' ' + digits.substring(7, 9);
    if (digits.length > 9) formatted += ' ' + digits.substring(9, 11);
    return formatted;
  };

  const apiPost = async (url, body) => {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        Accept: 'application/json',
      },
      body: JSON.stringify(body),
    });
    const payload = await response.json();

    if (!response.ok) {
      throw payload;
    }

    return payload;
  };

  const saveProfile = () => {
    const formData = new FormData();
    formData.append('name',      data.name.trim());
    formData.append('last_name', data.last_name.trim());
    formData.append('email',     data.email.trim());

    post('/profile/update', {
      data: formData,
      forceFormData: true,
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        onClose();
        router.reload({ only: ['auth', 'flash'] });
        onSuccess?.('Профиль заполнен!');
      },
    });
  };

  const sendPhoneCode = async () => {
    const normalized = normalizePhone(phone);
    if (!/^7\d{10}$/.test(normalized)) {
      setPhoneError('Введите полный номер телефона в формате +7 XXX XXX XX XX');
      return false;
    }

    setPhoneProcessing(true);
    setPhoneError('');
    setPhoneInfo('');

    try {
      await apiPost('/profile/phone/send-code', { phone: normalized });
      setPhoneStep('code');
      setPhoneInfo('Код отправлен. Для теста используйте 000000.');
      return true;
    } catch (error) {
      setPhoneError(error?.errors?.phone?.[0] || error?.message || 'Не удалось отправить код.');
      return false;
    } finally {
      setPhoneProcessing(false);
    }
  };

  const verifyPhoneCode = async () => {
    if (code.trim().length !== 6) {
      setPhoneError('Введите 6 цифр кода подтверждения.');
      return false;
    }

    setPhoneProcessing(true);
    setPhoneError('');

    try {
      await apiPost('/profile/phone/verify-code', { code: code.trim() });
      return true;
    } catch (error) {
      setPhoneError(error?.errors?.code?.[0] || error?.message || 'Неверный код подтверждения.');
      return false;
    } finally {
      setPhoneProcessing(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (needsPhone && phoneStep === 'phone') {
      await sendPhoneCode();
      return;
    }

    if (needsPhone) {
      const verified = await verifyPhoneCode();
      if (!verified) return;
    }

    saveProfile();
  };

  if (!isOpen) return null;

  return (
    <div className="phone-modal-overlay" onClick={onClose}>
      <div className="phone-modal-content verification-modal" onClick={(e) => e.stopPropagation()}>
        <button onClick={onClose} className="modal-close-btn">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20">
            <path fill="currentColor" d="M18.3 5.71a.996.996 0 0 0-1.41 0L12 10.59 7.11 5.7a.996.996 0 1 0-1.41 1.41L10.59 12l-4.89 4.89a.996.996 0 1 0 1.41 1.41L12 13.41l4.89 4.89a.996.996 0 1 0 1.41-1.41L13.41 12l4.89-4.89c.38-.38.38-1.02 0-1.4z" />
          </svg>
        </button>

        <div className="verification-modal-icon">✅</div>
        <h3 className="verification-modal-title">Заполните профиль</h3>
        <p className="verification-modal-subtitle">
          Укажите контактные данные и подтвердите телефон, чтобы оформлять заказы
        </p>

        <form onSubmit={handleSubmit} className="verification-modal-form">
          <div className="verification-field">
            <label className="verification-label">Имя <span className="required">*</span></label>
            <input
              type="text"
              value={data.name}
              onChange={(e) => setData('name', e.target.value.slice(0, 40))}
              placeholder="Ваше имя"
              className="phone-modal-input"
              maxLength={40}
              autoFocus
            />
            {errors.name && <p className="modal-error">{errors.name}</p>}
          </div>

          <div className="verification-field">
            <label className="verification-label">Фамилия</label>
            <input
              type="text"
              value={data.last_name}
              onChange={(e) => setData('last_name', e.target.value.slice(0, 40))}
              placeholder="Ваша фамилия"
              className="phone-modal-input"
              maxLength={40}
            />
            {errors.last_name && <p className="modal-error">{errors.last_name}</p>}
          </div>

          <div className="verification-field">
            <label className="verification-label">Email <span className="required">*</span></label>
            <input
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              placeholder="example@mail.com"
              className="phone-modal-input"
            />
            {errors.email && <p className="modal-error">{errors.email}</p>}
          </div>

          {needsPhone ? (
            <>
              <div className="verification-field">
                <label className="verification-label">Телефон <span className="required">*</span></label>
                <input
                  type="tel"
                  value={formatPhone(phone)}
                  onChange={(e) => {
                    setPhone(normalizePhone(e.target.value));
                    setPhoneError('');
                  }}
                  placeholder="+7 999 123 45 67"
                  className="phone-modal-input"
                  disabled={phoneStep === 'code'}
                />
              </div>

              {phoneStep === 'code' && (
                <div className="verification-field">
                  <label className="verification-label">Код подтверждения <span className="required">*</span></label>
                  <input
                    type="text"
                    inputMode="numeric"
                    value={code}
                    onChange={(e) => {
                      setCode(e.target.value.replace(/\D/g, '').slice(0, 6));
                      setPhoneError('');
                    }}
                    placeholder="000000"
                    className="phone-modal-input phone-auth-code-input"
                    maxLength={6}
                  />
                  <button type="button" className="phone-auth-resend" onClick={sendPhoneCode} disabled={phoneProcessing}>
                    Отправить код ещё раз
                  </button>
                </div>
              )}

              {phoneInfo && <p className="modal-success">{phoneInfo}</p>}
              {phoneError && <p className="modal-error">{phoneError}</p>}
            </>
          ) : (
            <p className="settings-hint-text">Телефон подтверждён: {formatPhone(auth.user.phone)}</p>
          )}

          <div className="phone-modal-buttons">
            <button type="button" onClick={onClose} className="phone-modal-btn phone-modal-btn--cancel">
              Позже
            </button>
            <button
              type="submit"
              disabled={processing || phoneProcessing || !data.name.trim() || !data.email.trim()}
              className="phone-modal-btn phone-modal-btn--submit"
            >
              {phoneProcessing || processing
                ? 'Сохранение...'
                : needsPhone && phoneStep === 'phone'
                  ? 'Отправить код'
                  : 'Сохранить'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
