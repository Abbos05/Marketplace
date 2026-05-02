import React from 'react';
import useEffect from 'react';
import { useForm } from '@inertiajs/react';

export default function ProfileEditModal({ auth, isOpen, onClose }) {
  const [preview, setPreview] = React.useState(auth.user.avatar || '/img/profiles/profile.png');
  const [phoneTouched, setPhoneTouched] = React.useState(false);

  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    email: '',
    description: '',
    phone: null,
    avatar: null,
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  // Синхронизация с auth.user при любом изменении (включая после верификации!)
  React.useEffect(() => {
    setData({
      name: auth.user.name || '',
      email: auth.user.email || '',
      description: auth.user.description || '',
      phone: auth.user.phone || null,
      current_password: '',
      password: '',
      password_confirmation: '',
      avatar: null,
    });
    setPreview(auth.user.avatar || '/img/profiles/profile.png');
    setPhoneTouched(false);
  }, [auth.user, setData]);

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setData('avatar', file);
      setPreview(URL.createObjectURL(file));
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();

    const formData = new FormData();
    formData.append('name', data.name);
    formData.append('email', data.email);
    formData.append('description', data.description || '');

    // Отправляем телефон ТОЛЬКО если пользователь его трогал
    if (phoneTouched && data.phone) {
      formData.append('phone', data.phone.replace(/\D/g, '')); // чистые 11 цифр
    }

    if (data.current_password) formData.append('current_password', data.current_password);
    if (data.password) {
      formData.append('password', data.password);
      formData.append('password_confirmation', data.password_confirmation);
    }
    if (data.avatar) formData.append('avatar', data.avatar);

    post('/profile/update', {
      data: formData,
      forceFormData: true,
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        reset('avatar', 'current_password', 'password', 'password_confirmation');
        setPhoneTouched(false);
        onClose();
        // Обновляем аватар в шапке (если нужно — через reload auth)
        // router.reload({ only: ['auth'] });
      },
      onError: (err) => {
        console.error('Ошибка сохранения профиля:', err);
      },
    });
  };

  const formatPhone = (value) => {
    const digits = value.replace(/\D/g, '').slice(0, 11);
    if (!digits) return '';
    let formatted = '+7';
    if (digits.length > 1) formatted += ' ' + digits.substring(1, 4);
    if (digits.length > 4) formatted += ' ' + digits.substring(4, 7);
    if (digits.length > 7) formatted += ' ' + digits.substring(7, 9);
    if (digits.length > 9) formatted += ' ' + digits.substring(9, 11);
    return formatted;
  };

  if (!isOpen) return null;

  return (
    <div className="profile-modal-overlay" onClick={onClose}>
      <div className="profile-modal-content" onClick={(e) => e.stopPropagation()}>
        <button onClick={onClose} className="profile-modal-close-btn" aria-label="Закрыть">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
            <path fill="currentColor" d="M18.3 5.71a.996.996 0 0 0-1.41 0L12 10.59 7.11 5.7a.996.996 0 1 0-1.41 1.41L10.59 12l-4.89 4.89a.996.996 0 1 0 1.41 1.41L12 13.41l4.89 4.89a.996.996 0 1 0 1.41-1.41L13.41 12l4.89-4.89c.38-.38.38-1.02 0-1.4z" />
          </svg>
        </button>

        <h1 className="profile-modal-title">Редактирование профиля</h1>

        <form onSubmit={handleSubmit} encType="multipart/form-data">
          <div className="profile-modal-form">

            {/* Фото профиля */}
            <div className="profile-modal-form-item">
              <label className="profile-modal-label">Фото профиля</label>
              <div className="profile-modal-avatar-container">
                <img src={preview} alt="Аватар" className="profile-modal-avatar" />
                <label className="profile-modal-avatar-upload">
                  +
                  <input
                    type="file"
                    accept="image/*"
                    onChange={handleFileChange}
                    style={{ display: 'none' }}
                  />
                </label>
              </div>
              {errors.avatar && <p className="profile-modal-error">{errors.avatar}</p>}
            </div>

            {/* Имя */}
            <div className="profile-modal-form-item">
              <label className="profile-modal-label">
                Имя <span className="profile-modal-hint">({data.name?.length || 0}/25)</span>
              </label>
              <input
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value.slice(0, 25))}
                className="profile-modal-input"
                placeholder="Ваше имя"
                maxLength={25}
              />
              {errors.name && <p className="profile-modal-error">{errors.name}</p>}
            </div>

            {/* Email */}
            <div className="profile-modal-form-item">
              <label className="profile-modal-label">
                Email <span className="profile-modal-hint">({data.email?.length || 0}/75)</span>
              </label>
              <input
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                className="profile-modal-input"
                placeholder="example@mail.com"
                maxLength={75}
              />
              {errors.email && <p className="profile-modal-error">{errors.email}</p>}
            </div>

            {/* Телефон — только если уже есть */}
            {auth.user.phone && (
              <div className="profile-modal-form-item">
                <label className="profile-modal-label">Телефон</label>
                <input
                  type="tel"
                  value={phoneTouched ? formatPhone(data.phone) : formatPhone(auth.user.phone)}
                  onFocus={() => {
                    setPhoneTouched(true);
                    if (!data.phone) setData('phone', auth.user.phone);
                  }}
                  onChange={(e) => {
                    setPhoneTouched(true);
                    const digits = e.target.value.replace(/\D/g, '').slice(0, 11);
                    setData('phone', digits || '');
                  }}
                  placeholder="+7 999 999 99 99"
                  className="profile-modal-input"
                />
                {errors.phone && <p className="profile-modal-error">{errors.phone}</p>}
              </div>
            )}

            {/* Текущий пароль */}
            {!auth.user?.newPassw && (
              <div className="profile-modal-form-item">
                <label className="profile-modal-label">
                  Текущий пароль <small>(нужен для смены пароля)</small>
                </label>
                <input
                  type="password"
                  value={data.current_password}
                  onChange={(e) => setData('current_password', e.target.value)}
                  className="profile-modal-input"
                  placeholder="••••••••"
                />
                {errors.current_password && <p className="profile-modal-error">{errors.current_password}</p>}
              </div>
            )}

            {/* Новый пароль */}
            <div className="profile-modal-form-item">
              <label className="profile-modal-label">Новый пароль</label>
              <input
                type="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                className="profile-modal-input"
                placeholder="Оставьте пустым, чтобы не менять"
              />
              {errors.password && <p className="profile-modal-error">{errors.password}</p>}
            </div>

            {/* Подтверждение пароля */}
            <div className="profile-modal-form-item">
              <label className="profile-modal-label">Подтвердить пароль</label>
              <input
                type="password"
                value={data.password_confirmation}
                onChange={(e) => setData('password_confirmation', e.target.value)}
                className="profile-modal-input"
                placeholder="••••••••"
              />
            </div>

            {/* Описание */}
            <div className="profile-modal-form-item">
              <label className="profile-modal-label">
                Описание <span className="profile-modal-hint">({data.description?.length || 0}/175)</span>
              </label>
              <textarea
                rows="4"
                value={data.description}
                onChange={(e) => setData('description', e.target.value.slice(0, 175))}
                className="profile-modal-input"
                placeholder="Расскажите о себе..."
                maxLength={175}
              />
              {errors.description && <p className="profile-modal-error">{errors.description}</p>}
            </div>

            {/* Кнопка */}
            <div className="profile-modal-form-actions">
              <button
                type="submit"
                disabled={processing}
                className="profile-modal-submit-btn"
              >
                {processing ? 'Сохранение...' : 'Сохранить изменения'}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}