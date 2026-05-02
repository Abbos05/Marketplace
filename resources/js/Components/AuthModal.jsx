// src/Components/AuthModal.jsx
import React from 'react';
import { useForm } from '@inertiajs/react';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import { Link, router } from '@inertiajs/react';

const AuthModal = ({
  isOpen,
  onClose,
  isLogin,
  setIsLogin,
  onLoginSuccess,
  onRegisterSuccess,
}) => {
  const { data: loginData, setData: setLoginData, post: loginPost, processing: loginProcessing, errors: loginErrors, reset: loginReset } = useForm({
    email: '',
    password: '',
    remember: false,
  });

  const { data: registerData, setData: setRegisterData, post: registerPost, processing: registerProcessing, errors: registerErrors, reset: registerReset } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    remember: true,
  });

  const handleLoginSubmit = (e) => {
    e.preventDefault();
    loginPost(route('login'), {
      onSuccess: () => {
        onLoginSuccess?.();
        loginReset();
      },
      onFinish: () => loginReset('password'),
    });
  };

  const handleRegisterSubmit = (e) => {
    e.preventDefault();
    registerPost(route('register'), {
      onSuccess: () => {
        onRegisterSuccess?.();
        registerReset();
      },
      onFinish: () => registerReset('password', 'password_confirmation'),
    });
  };

  if (!isOpen) return null;

  return (
    <div className="modal-overlay">
      <div className="modal-content">
        <button className="modal-close" onClick={onClose}>
          ×
        </button>
        <div className="modal-tabs">
          <button
            className={`tab-button ${isLogin ? 'active' : ''}`}
            onClick={() => setIsLogin(true)}
          >
            Вход
          </button>
          <button
            className={`tab-button ${!isLogin ? 'active' : ''}`}
            onClick={() => setIsLogin(false)}
          >
            Регистрация
          </button>
        </div>

        {isLogin ? (
          <form onSubmit={handleLoginSubmit} className="modal-form">
            <div className="modal-form-group">
              <InputLabel htmlFor="email" value="Email" />
              <TextInput
                id="email"
                type="email"
                name="email"
                value={loginData.email}
                className="modal-input"
                autoComplete="username"
                isFocused={true}
                onChange={(e) => setLoginData('email', e.target.value)}
              />
              <InputError message={loginErrors.email} className="modal-error" />
            </div>
            <div className="modal-form-group">
              <InputLabel htmlFor="password" value="Пароль" />
              <TextInput
                id="password"
                type="password"
                name="password"
                value={loginData.password}
                className="modal-input"
                autoComplete="current-password"
                onChange={(e) => setLoginData('password', e.target.value)}
              />
              <InputError message={loginErrors.password} className="modal-error" />
            </div>
            <div className="modal-form-actions">
             
              <PrimaryButton className="modal-submit" disabled={loginProcessing}>
                Войти
              </PrimaryButton>
            </div>
            <div className="modal-form-divider"><span>или</span></div>
            {['google', 'yandex', 'github'].map((provider) => (
              <button
                key={provider}
                type="button"
                className="modal-google-btn"
                onClick={() => {
                  onClose();
                  window.location.href = `/auth/${provider}`;
                }}
              >
                <img
                  src={`/img/auth/${provider}.png`}
                  alt={provider.charAt(0).toUpperCase() + provider.slice(1)}
                  className="modal-google-icon"
                />
                <span>{provider === 'github' ? 'GitHub' : provider.charAt(0).toUpperCase() + provider.slice(1)}</span>
              </button>
            ))}
          </form>
        ) : (
          <form onSubmit={handleRegisterSubmit} className="modal-form">
            <div className="modal-form-group">
              <InputLabel htmlFor="name" value="Имя" />
              <TextInput
                id="name"
                name="name"
                value={registerData.name}
                className="modal-input"
                autoComplete="name"
                isFocused={true}
                onChange={(e) => setRegisterData('name', e.target.value)}
                required
              />
              <InputError message={registerErrors.name} className="modal-error" />
            </div>
            <div className="modal-form-group">
              <InputLabel htmlFor="email" value="Email" />
              <TextInput
                id="email"
                type="email"
                name="email"
                value={registerData.email}
                className="modal-input"
                autoComplete="username"
                onChange={(e) => setRegisterData('email', e.target.value)}
                required
              />
              <InputError message={registerErrors.email} className="modal-error" />
            </div>
            <div className="modal-form-group">
              <InputLabel htmlFor="password" value="Пароль" />
              <TextInput
                id="password"
                type="password"
                name="password"
                value={registerData.password}
                className="modal-input"
                autoComplete="new-password"
                onChange={(e) => setRegisterData('password', e.target.value)}
                required
              />
              <InputError message={registerErrors.password} className="modal-error" />
            </div>
            <div className="modal-form-group">
              <InputLabel htmlFor="password_confirmation" value="Подтверждение пароля" />
              <TextInput
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                value={registerData.password_confirmation}
                className="modal-input"
                autoComplete="new-password"
                onChange={(e) => setRegisterData('password_confirmation', e.target.value)}
                required
              />
              <InputError message={registerErrors.password_confirmation} className="modal-error" />
            </div>
            <div className="modal-form-actionsreg">
              <Link
                href="#"
                className="modal-link"
                onClick={(e) => {
                  e.preventDefault();
                  setIsLogin(true);
                }}
              >
                Уже зарегистрированы?
              </Link>
              <PrimaryButton className="modal-submit" disabled={registerProcessing}>
                Зарегистрироваться
              </PrimaryButton>
            </div>
            <div className="modal-form-divider"><span>или</span></div>
            <button
              type="button"
              className="modal-google-btn"
              onClick={() => {
                onClose();
                window.location.href = '/auth/google';
              }}
            >
              <img src="/img/auth/google.png" alt="Google" className="modal-google-icon" />
              <span>Google</span>
            </button>
          </form>
        )}
      </div>
    </div>
  );
};

export default AuthModal;