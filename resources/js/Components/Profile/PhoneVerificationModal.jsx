import React from 'react';
import { useForm, router } from '@inertiajs/react';

/**
 * Модал верификации профиля.
 * Вместо ввода телефона (телефон уже используется для входа)
 * пользователь заполняет: имя, фамилию и email.
 */
export default function PhoneVerificationModal({ isOpen, onClose, auth, onSuccess }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    name:      auth?.user?.name      || '',
    last_name: auth?.user?.last_name || '',
    email:     auth?.user?.email     || '',
  });

  const handleSubmit = (e) => {
    e.preventDefault();

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
        router.reload({ only: ['auth'] });
        onSuccess?.('Профиль заполнен!');
      },
    });
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
          Укажите имя и email — это поможет получить полный доступ к платформе
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

          <div className="phone-modal-buttons">
            <button type="button" onClick={onClose} className="phone-modal-btn phone-modal-btn--cancel">
              Позже
            </button>
            <button
              type="submit"
              disabled={processing || !data.name.trim() || !data.email.trim()}
              className="phone-modal-btn phone-modal-btn--submit"
            >
              {processing ? 'Сохранение...' : 'Сохранить'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
