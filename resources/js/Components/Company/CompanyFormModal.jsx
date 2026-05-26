// Components/Company/CompanyFormModal.jsx
import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import '../../../css/profile/style.css';

export default function CompanyFormModal({ isOpen, onClose, onSuccess }) {
  const { data, setData, post, processing, errors } = useForm({
    inn: '',
    shop_name: '',
    legal_address: '',
    pickup_address: '',
    description: '',
    working_hours: null,
  });

  const [step, setStep] = useState(1);

  const handleSubmit = (e) => {
    e.preventDefault();
    post(route('seller-profile.store'), {
      onSuccess: () => {
        onSuccess?.('Компания успешно добавлена!');
        onClose();
        setStep(1);
        reset();
      },
    });
  };

  const reset = () => {
    setData({
      inn: '',
      shop_name: '',
      legal_address: '',
      pickup_address: '',
      description: '',
      working_hours: null,
    });
  };

  if (!isOpen) return null;

  return (
    <div className="company-modal-overlay" onClick={onClose}>
      <div className="company-modal" onClick={(e) => e.stopPropagation()}>
        <div className="company-modal-header">
          <h2>Добавьте компанию</h2>
          <button className="close-btn" onClick={onClose}>✕</button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="company-modal-body">
            {/* Шаг 1: ИНН */}
            {step === 1 && (
              <div className="form-step">
                <div className="inn-input-group">
                  <label>ИНН компании *</label>
                  <input
                    type="text"
                    value={data.inn}
                    onChange={(e) => setData('inn', e.target.value)}
                    placeholder="Введите 10 или 12 цифр"
                    maxLength={12}
                    className={errors.inn ? 'error' : ''}
                  />
                  {errors.inn && <span className="error-text">{errors.inn}</span>}
                  <p className="inn-hint">
                    Укажите ИНН компании, которая будет оплачивать заказы
                  </p>
                </div>

                <button
                  type="button"
                  className="primary-btn"
                  onClick={() => setStep(2)}
                  disabled={!data.inn || data.inn.length < 10}
                >
                  Продолжить
                </button>
              </div>
            )}

            {/* Шаг 2: Данные компании */}
            {step === 2 && (
              <div className="form-step">
                <h3>Данные компании</h3>
                
                <div className="form-group">
                  <label>Название магазина *</label>
                  <input
                    type="text"
                    value={data.shop_name}
                    onChange={(e) => setData('shop_name', e.target.value)}
                    placeholder="ООО 'Ромашка'"
                    required
                  />
                  {errors.shop_name && <span className="error-text">{errors.shop_name}</span>}
                </div>

                <div className="form-group">
                  <label>Юридический адрес *</label>
                  <input
                    type="text"
                    value={data.legal_address}
                    onChange={(e) => setData('legal_address', e.target.value)}
                    placeholder="г. Москва, ул. Примерная, д. 1"
                  />
                  {errors.legal_address && <span className="error-text">{errors.legal_address}</span>}
                </div>

                <div className="form-group">
                  <label>Адрес самовывоза *</label>
                  <input
                    type="text"
                    value={data.pickup_address}
                    onChange={(e) => setData('pickup_address', e.target.value)}
                    placeholder="г. Москва, ул. Складская, д. 5"
                  />
                  {errors.pickup_address && <span className="error-text">{errors.pickup_address}</span>}
                  <p className="field-hint">Адрес, где покупатели могут забрать товар</p>
                </div>

                <div className="form-group">
                  <label>Описание магазина</label>
                  <textarea
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    placeholder="Расскажите о вашей компании..."
                    rows={3}
                  />
                </div>

                <div className="form-group">
                  <label>Часы работы (JSON)</label>
                  <input
                    type="text"
                    value={data.working_hours || ''}
                    onChange={(e) => setData('working_hours', e.target.value)}
                    placeholder='{"mon-fri": "9:00-18:00", "sat": "10:00-15:00"}'
                  />
                  <p className="field-hint">Формат JSON, например: {"{\"mon-fri\": \"9:00-18:00\"}"}</p>
                </div>

                <div className="modal-actions">
                  <button type="button" className="secondary-btn" onClick={() => setStep(1)}>
                    Назад
                  </button>
                  <button type="submit" className="primary-btn" disabled={processing}>
                    {processing ? 'Сохранение...' : 'Добавить компанию'}
                  </button>
                </div>
              </div>
            )}
          </div>
        </form>

        <div className="company-benefits-mini">
          <div className="benefit-item">
            <span>✓</span>
            <span>Регистрация за минуту</span>
          </div>
          <div className="benefit-item">
            <span>💳</span>
            <span>Оплата по счёту</span>
          </div>
          <div className="benefit-item">
            <span>📄</span>
            <span>Электронные документы</span>
          </div>
        </div>
      </div>
    </div>
  );
}