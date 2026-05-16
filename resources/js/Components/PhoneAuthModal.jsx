import React, { useState, useRef, useEffect } from 'react';

export default function PhoneAuthModal({ isOpen, onClose }) {
  const [step, setStep] = useState(1);
  const [method, setMethod] = useState(null); // 'sms' | 'email' | 'password'
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  // Данные пользователя с сервера (шаг 1)
  const [userInfo, setUserInfo] = useState({ is_new: false, has_password: false, has_email: false });

  // Поля ввода
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [email, setEmail] = useState('');
  const [emailSent, setEmailSent] = useState(false);
  const [password, setPassword] = useState('');

  // Таймер повторной отправки кода
  const [resendCooldown, setResendCooldown] = useState(0);
  const cooldownRef = useRef(null);

  const phoneRef = useRef(null);

  // ── Сброс при открытии ───────────────────────────────────────────────────

  useEffect(() => {
    if (isOpen) {
      setStep(1);
      setMethod(null);
      setError('');
      setPhone('');
      setCode('');
      setEmail('');
      setEmailSent(false);
      setPassword('');
      setResendCooldown(0);
      clearInterval(cooldownRef.current);
      setTimeout(() => phoneRef.current?.focus(), 100);
    }
    return () => clearInterval(cooldownRef.current);
  }, [isOpen]);

  if (!isOpen) return null;

  // ── Утилиты ──────────────────────────────────────────────────────────────

  const csrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

  const apiPost = async (url, body) => {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        Accept: 'application/json',
      },
      body: JSON.stringify(body),
    });
    return res.json();
  };

  const formatPhone = (raw) => {
    const digits = (raw || '').replace(/\D/g, '').slice(0, 11);
    if (!digits) return '';
    let f = '+7';
    if (digits.length > 1) f += ' ' + digits.substring(1, 4);
    if (digits.length > 4) f += ' ' + digits.substring(4, 7);
    if (digits.length > 7) f += ' ' + digits.substring(7, 9);
    if (digits.length > 9) f += ' ' + digits.substring(9, 11);
    return f;
  };

  const handlePhoneInput = (e) => {
    let input = e.target.value.replace(/\D/g, '');
    if (input.startsWith('8')) input = '7' + input.slice(1);
    if (input && !input.startsWith('7')) input = '7' + input;
    setPhone(input.slice(0, 11));
  };

  // Запустить таймер 60 секунд
  const startCooldown = () => {
    setResendCooldown(60);
    clearInterval(cooldownRef.current);
    cooldownRef.current = setInterval(() => {
      setResendCooldown((prev) => {
        if (prev <= 1) { clearInterval(cooldownRef.current); return 0; }
        return prev - 1;
      });
    }, 1000);
  };

  // ── Шаг 1: отправить номер ───────────────────────────────────────────────

  const handleSendPhone = async (e) => {
    e?.preventDefault();
    if (phone.length < 11) { setError('Введите полный номер телефона'); return; }
    setLoading(true);
    setError('');
    try {
      const data = await apiPost('/auth/phone/send-code', { phone });
      if (data.success) {
        setUserInfo(data);
        // Новый пользователь — только SMS, пропускаем выбор метода
        if (data.is_new) {
          setMethod('sms');
          setStep(3);
          startCooldown();
        } else {
          setStep(2);
        }
      } else {
        setError(data.message || 'Ошибка, попробуйте ещё раз');
      }
    } catch {
      setError('Ошибка соединения');
    } finally {
      setLoading(false);
    }
  };

  // ── Шаг 2: выбрать метод ────────────────────────────────────────────────

  const handleSelectMethod = (m) => {
    setMethod(m);
    setError('');
    setCode('');
    setEmailSent(false);
    setStep(3);
    // Запускаем таймер сразу при переходе к SMS-коду
    if (m === 'sms') startCooldown();
  };

  // ── Повторная отправка SMS-кода ──────────────────────────────────────────

  const handleResendSms = async () => {
    if (resendCooldown > 0) return;
    setError('');
    setCode('');
    setLoading(true);
    try {
      const data = await apiPost('/auth/phone/send-code', { phone });
      if (data.success) {
        startCooldown();
      } else {
        setError(data.message || 'Ошибка при отправке');
      }
    } catch {
      setError('Ошибка соединения');
    } finally {
      setLoading(false);
    }
  };

  // ── Верификация SMS-кода ─────────────────────────────────────────────────

  const handleVerifySms = async (currentCode) => {
    const c = currentCode ?? code;
    if (c.length !== 6) return;
    setLoading(true);
    setError('');
    try {
      const data = await apiPost('/auth/phone/verify-code', { phone, code: c });
      if (data.success) {
        window.location.href = data.redirect;
      } else {
        setError(data.message || 'Неверный код');
        setCode(''); // автоочистка при ошибке
      }
    } catch {
      setError('Ошибка соединения');
      setCode('');
    } finally {
      setLoading(false);
    }
  };

  // ── Email: отправить код ─────────────────────────────────────────────────

  const handleSendEmailCode = async (e) => {
    e?.preventDefault();
    if (!email) { setError('Введите email'); return; }
    setLoading(true);
    setError('');
    try {
      const data = await apiPost('/auth/phone/send-email-code', { phone, email });
      if (data.success) {
        setEmailSent(true);
        startCooldown();
      } else {
        setError(data.message || 'Ошибка отправки');
      }
    } catch {
      setError('Ошибка соединения');
    } finally {
      setLoading(false);
    }
  };

  // ── Верификация email-кода ───────────────────────────────────────────────

  const handleVerifyEmailCode = async (currentCode) => {
    const c = currentCode ?? code;
    if (c.length !== 6) return;
    setLoading(true);
    setError('');
    try {
      const data = await apiPost('/auth/phone/verify-email-code', { phone, email, code: c });
      if (data.success) {
        window.location.href = data.redirect;
      } else {
        setError(data.message || 'Неверный код');
        setCode(''); // автоочистка
      }
    } catch {
      setError('Ошибка соединения');
      setCode('');
    } finally {
      setLoading(false);
    }
  };

  // ── Повторная отправка email-кода ────────────────────────────────────────

  const handleResendEmailCode = async () => {
    if (resendCooldown > 0) return;
    setError('');
    setCode('');
    setLoading(true);
    try {
      const data = await apiPost('/auth/phone/send-email-code', { phone, email });
      if (data.success) startCooldown();
      else setError(data.message || 'Ошибка при отправке');
    } catch {
      setError('Ошибка соединения');
    } finally {
      setLoading(false);
    }
  };

  // ── Пароль ───────────────────────────────────────────────────────────────

  const handleLoginPassword = async (e) => {
    e?.preventDefault();
    if (!password) { setError('Введите пароль'); return; }
    setLoading(true);
    setError('');
    try {
      const data = await apiPost('/auth/phone/login-password', { phone, password });
      if (data.success) {
        window.location.href = data.redirect;
      } else {
        setError(data.message || 'Неверный пароль');
      }
    } catch {
      setError('Ошибка соединения');
    } finally {
      setLoading(false);
    }
  };

  // ── Автосабмит: 6 цифр → сразу проверяем ────────────────────────────────

  const handleCodeChange = (e) => {
    const val = e.target.value.replace(/\D/g, '').slice(0, 6);
    setCode(val);
    if (val.length === 6 && !loading) {
      if (method === 'sms') handleVerifySms(val);
      if (method === 'email' && emailSent) handleVerifyEmailCode(val);
    }
  };

  // ── Рендер ───────────────────────────────────────────────────────────────

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content phone-auth-modal" onClick={(e) => e.stopPropagation()}>
        <button className="modal-close" onClick={onClose}>×</button>

    

        {/* ── ШАГ 1: Телефон ─────────────────────────────────────────────── */}
        {step === 1 && (
          <form onSubmit={handleSendPhone} className="modal-form">
            <h2 className="phone-auth-title">Войти или зарегистрироваться</h2>
            <p className="phone-auth-subtitle">Введите номер телефона — мы пришлём код для входа</p>

            <div className="modal-form-group">
              <label className="phone-auth-label">Номер телефона</label>
              <input
                ref={phoneRef}
                type="tel"
                value={formatPhone(phone)}
                onChange={handlePhoneInput}
                placeholder="+7 999 999 99 99"
                className="modal-input"
                inputMode="tel"
                autoComplete="tel"
              />
            </div>

            {error && <p className="modal-error">{error}</p>}

            <button type="submit" className="phone-auth-btn" disabled={loading || phone.length < 11}>
              {loading ? 'Проверяем...' : 'Продолжить'}
            </button>

            <div className="modal-form-divider"><span>или войти через</span></div>
            {['google', 'yandex', 'github'].map((provider) => (
              <button
                key={provider}
                type="button"
                className="modal-google-btn"
                onClick={() => { onClose(); window.location.href = `/auth/${provider}`; }}
              >
                <img src={`/img/auth/${provider}.png`} alt={provider} className="modal-google-icon" />
                <span>{provider === 'github' ? 'GitHub' : provider.charAt(0).toUpperCase() + provider.slice(1)}</span>
              </button>
            ))}
          </form>
        )}

        {/* ── ШАГ 2: Выбор способа ───────────────────────────────────────── */}
        {step === 2 && (
          <div className="modal-form">
            <button className="phone-auth-back" onClick={() => { setStep(1); setError(''); }}>← Назад</button>
            <h2 className="phone-auth-title">Как подтвердить вход?</h2>
            <p className="phone-auth-subtitle">Телефон: {formatPhone(phone)}</p>
            {userInfo.is_new && (
              <p className="phone-auth-new-badge">Новый аккаунт</p>
            )}

            <div className="phone-auth-methods">
              {/* SMS — всегда */}
              <button className="phone-auth-method-btn" onClick={() => handleSelectMethod('sms')}>
                <span className="phone-auth-method-icon">📱</span>
                <div>
                  <div className="phone-auth-method-title">Код по SMS</div>
                  <div className="phone-auth-method-desc">Отправим 6-значный код на {formatPhone(phone)}</div>
                </div>
              </button>

              {/* Email — только для существующих пользователей с email */}
              {!userInfo.is_new && userInfo.has_email && (
                <button className="phone-auth-method-btn" onClick={() => handleSelectMethod('email')}>
                  <span className="phone-auth-method-icon">📧</span>
                  <div>
                    <div className="phone-auth-method-title">Код на email</div>
                    <div className="phone-auth-method-desc">Отправим код на привязанный адрес</div>
                  </div>
                </button>
              )}

              {/* Пароль — только для существующих пользователей с паролем */}
              {!userInfo.is_new && userInfo.has_password && (
                <button className="phone-auth-method-btn" onClick={() => handleSelectMethod('password')}>
                  <span className="phone-auth-method-icon">🔒</span>
                  <div>
                    <div className="phone-auth-method-title">Войти с паролем</div>
                    <div className="phone-auth-method-desc">Введите пароль от аккаунта</div>
                  </div>
                </button>
              )}
            </div>
          </div>
        )}

        {/* ── ШАГ 3а: SMS-код ────────────────────────────────────────────── */}
        {step === 3 && method === 'sms' && (
          <form onSubmit={(e) => { e.preventDefault(); handleVerifySms(); }} className="modal-form">
            <button className="phone-auth-back" onClick={() => { setStep(2); setError(''); setCode(''); }}>← Назад</button>
            <h2 className="phone-auth-title">Введите код из SMS</h2>
            <p className="phone-auth-subtitle">Отправили код на {formatPhone(phone)}</p>

            <div className="modal-form-group">
              <label className="phone-auth-label">Код подтверждения</label>
              <input
                type="text"
                value={code}
                onChange={handleCodeChange}
                placeholder="000000"
                className="modal-input phone-auth-code-input"
                inputMode="numeric"
                maxLength={6}
                autoFocus
                autoComplete="one-time-code"
              />
            </div>

            {error && <p className="modal-error">{error}</p>}

            <button type="submit" className="phone-auth-btn" disabled={loading || code.length !== 6}>
              {loading ? 'Проверяем...' : 'Войти'}
            </button>

            <button
              type="button"
              className="phone-auth-resend"
              onClick={handleResendSms}
              disabled={resendCooldown > 0}
            >
              {resendCooldown > 0
                ? `Отправить снова (${resendCooldown}с)`
                : 'Отправить код снова'}
            </button>
          </form>
        )}

        {/* ── ШАГ 3б: Email — ввод адреса ───────────────────────────────── */}
        {step === 3 && method === 'email' && !emailSent && (
          <form onSubmit={handleSendEmailCode} className="modal-form">
            <button className="phone-auth-back" onClick={() => { setStep(2); setError(''); }}>← Назад</button>
            <h2 className="phone-auth-title">Введите email</h2>
            <p className="phone-auth-subtitle">Пришлём код подтверждения на указанный адрес</p>

            <div className="modal-form-group">
              <label className="phone-auth-label">Email</label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="example@mail.com"
                className="modal-input"
                autoFocus
              />
            </div>

            {error && <p className="modal-error">{error}</p>}

            <button type="submit" className="phone-auth-btn" disabled={loading || !email}>
              {loading ? 'Отправляем...' : 'Получить код'}
            </button>
          </form>
        )}

        {/* ── ШАГ 3б: Email — ввод кода ─────────────────────────────────── */}
        {step === 3 && method === 'email' && emailSent && (
          <form onSubmit={(e) => { e.preventDefault(); handleVerifyEmailCode(); }} className="modal-form">
            <h2 className="phone-auth-title">Введите код из письма</h2>
            <p className="phone-auth-subtitle">Отправили код на {email}</p>

            <div className="modal-form-group">
              <label className="phone-auth-label">Код подтверждения</label>
              <input
                type="text"
                value={code}
                onChange={handleCodeChange}
                placeholder="000000"
                className="modal-input phone-auth-code-input"
                inputMode="numeric"
                maxLength={6}
                autoFocus
                autoComplete="one-time-code"
              />
            </div>

            {error && <p className="modal-error">{error}</p>}

            <button type="submit" className="phone-auth-btn" disabled={loading || code.length !== 6}>
              {loading ? 'Проверяем...' : 'Войти'}
            </button>

            <button
              type="button"
              className="phone-auth-resend"
              onClick={handleResendEmailCode}
              disabled={resendCooldown > 0}
            >
              {resendCooldown > 0
                ? `Отправить снова (${resendCooldown}с)`
                : 'Отправить код снова'}
            </button>

            <button type="button" className="phone-auth-resend" style={{ marginTop: 4 }} onClick={() => { setEmailSent(false); setCode(''); setError(''); }}>
              Изменить email
            </button>
          </form>
        )}

        {/* ── ШАГ 3в: Пароль ─────────────────────────────────────────────── */}
        {step === 3 && method === 'password' && (
          <form onSubmit={handleLoginPassword} className="modal-form">
            <button className="phone-auth-back" onClick={() => { setStep(2); setError(''); }}>← Назад</button>
            <h2 className="phone-auth-title">Введите пароль</h2>
            <p className="phone-auth-subtitle">{formatPhone(phone)}</p>

            <div className="modal-form-group">
              <label className="phone-auth-label">Пароль</label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                className="modal-input"
                autoFocus
              />
            </div>

            {error && <p className="modal-error">{error}</p>}

            <button type="submit" className="phone-auth-btn" disabled={loading || !password}>
              {loading ? 'Входим...' : 'Войти'}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
