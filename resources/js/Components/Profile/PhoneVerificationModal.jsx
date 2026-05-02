import React, { useState } from 'react';
import { useForm, router } from '@inertiajs/react';

export default function PhoneVerificationModal({ isOpen, onClose, auth }) {
  const [rawPhone, setRawPhone] = useState('');

  const { data, setData, post, processing, errors } = useForm({
    phone: '',
  });

  const formatPhone = (value) => {
    const digits = value.replace(/\D/g, '').slice(0, 11);
    if (!digits) return '';
    let result = '+7 ';
    if (digits.length > 1) result += digits.substring(1, 4);
    if (digits.length > 4) result += ' ' + digits.substring(4, 7);
    if (digits.length > 7) result += ' ' + digits.substring(7, 9);
    if (digits.length > 9) result += ' ' + digits.substring(9, 11);
    return result;
  };

  const handleChange = (e) => {
    let input = e.target.value.replace(/\D/g, '');
    if (input.startsWith('8')) input = '7' + input.slice(1);
    if (!input.startsWith('7')) input = '7' + input;
    input = input.slice(0, 11);
  
    setRawPhone(input);
    setData('phone', input); // ← ЭТО РАБОТАЕТ!
  };
  const handleSubmit = (e) => {
    e.preventDefault();
    if (rawPhone.length < 11) return;

    post('/profile/update-phone', {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        onClose();
        router.reload({ only: ['auth'] });
      },
    });
  };

  if (!isOpen) return null;

  return (
    <div className="phone-modal-overlay" onClick={onClose}>
      <div className="phone-modal-content" onClick={e => e.stopPropagation()}>
        <button onClick={onClose} className="modal-close-btn">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path fill="currentColor" d="M18.3 5.71a.996.996 0 0 0-1.41 0L12 10.59 7.11 5.7a.996.996 0 1 0-1.41 1.41L10.59 12l-4.89 4.89a.996.996 0 1 0 1.41 1.41L12 13.41l4.89 4.89a.996.996 0 1 0 1.41-1.41L13.41 12l4.89-4.89c.38-.38.38-1.02 0-1.4z" />
          </svg>
        </button>

        <h3>Верификация телефона</h3>
        <p>Введите номер для подтверждения личности.</p>

        <form onSubmit={handleSubmit}>
          <input
            type="tel"
            value={formatPhone(rawPhone)}
            onChange={handleChange}
            placeholder="+7 999 999 99 99"
            className="phone-modal-input"
            required
          />
          {errors.phone && <p className="modal-error">{errors.phone}</p>}

          <div className="phone-modal-buttons">
            <button type="button" onClick={onClose} className="phone-modal-btn phone-modal-btn--cancel">
              Отмена
            </button>
            <button
              type="submit"
              disabled={processing || rawPhone.length < 11}
              className="phone-modal-btn phone-modal-btn--submit"
            >
              {processing ? 'Отправка...' : 'Отправить'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}