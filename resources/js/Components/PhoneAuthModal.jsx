import React, { useState, useRef, useEffect, useCallback } from 'react';
import {
  loadPhoneAuthFlow,
  savePhoneAuthFlow,
  clearPhoneAuthFlow,
  cooldownSecondsLeft,
} from '../lib/phoneAuthSession';

const STEPS = {
  PHONE: 'phone',
  CODE: 'code',
  PASSWORD: 'password',
  FORGOT_CODE: 'forgot_code',
  FORGOT_PASSWORD: 'forgot_password',
};

export default function PhoneAuthModal({ isOpen, onClose }) {
  const [step, setStep] = useState(STEPS.PHONE);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const [challengeId, setChallengeId] = useState(null);
  const [deliveryChannel, setDeliveryChannel] = useState('sms');
  const [maskedPhone, setMaskedPhone] = useState('');
  const [requiresPassword, setRequiresPassword] = useState(false);
  const [phoneVerified, setPhoneVerified] = useState(false);

  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [newPasswordConfirm, setNewPasswordConfirm] = useState('');

  const [resetDeliveryHint, setResetDeliveryHint] = useState('');
  const [actionMessage, setActionMessage] = useState('');
  const [smsFallbackActive, setSmsFallbackActive] = useState(false);

  const [resendCooldown, setResendCooldown] = useState(0);
  const [cooldownUntil, setCooldownUntil] = useState(0);
  const cooldownRef = useRef(null);
  const phoneRef = useRef(null);

  const applyCooldownSeconds = useCallback((seconds) => {
    const sec = Math.max(0, Number(seconds) || 0);
    if (sec <= 0) {
      setCooldownUntil(0);
      setResendCooldown(0);
      return;
    }
    const until = Date.now() + sec * 1000;
    setCooldownUntil(until);
    setResendCooldown(sec);
  }, []);

  const persistFlow = useCallback((overrides = {}) => {
    if (!challengeId && !overrides.challengeId) return;
    savePhoneAuthFlow({
      phone: overrides.phone ?? phone,
      challengeId: overrides.challengeId ?? challengeId,
      deliveryChannel: overrides.deliveryChannel ?? deliveryChannel,
      maskedPhone: overrides.maskedPhone ?? maskedPhone,
      requiresPassword: overrides.requiresPassword ?? requiresPassword,
      phoneVerified: overrides.phoneVerified ?? phoneVerified,
      smsFallbackActive: overrides.smsFallbackActive ?? smsFallbackActive,
      step: overrides.step ?? step,
      cooldownUntil: overrides.cooldownUntil ?? cooldownUntil,
      actionMessage: overrides.actionMessage ?? actionMessage,
    });
  }, [
    phone, challengeId, deliveryChannel, maskedPhone, requiresPassword,
    phoneVerified, smsFallbackActive, step, cooldownUntil, actionMessage,
  ]);

  const resetState = (keepPhone = false) => {
    const savedPhone = keepPhone ? phone : '';
    setStep(STEPS.PHONE);
    setError('');
    setChallengeId(null);
    setDeliveryChannel('sms');
    setMaskedPhone('');
    setRequiresPassword(false);
    setPhoneVerified(false);
    setPhone(savedPhone);
    setCode('');
    setPassword('');
    setNewPassword('');
    setNewPasswordConfirm('');
    setResetDeliveryHint('');
    setActionMessage('');
    setSmsFallbackActive(false);
    setCooldownUntil(0);
    setResendCooldown(0);
    clearInterval(cooldownRef.current);
    clearPhoneAuthFlow();
  };

  const handleClose = () => {
    resetState();
    onClose();
  };

  const restoreFromSaved = (saved) => {
    setPhone(saved.phone || '');
    setChallengeId(saved.challengeId);
    setDeliveryChannel(saved.deliveryChannel || 'sms');
    setMaskedPhone(saved.maskedPhone || '');
    setRequiresPassword(!!saved.requiresPassword);
    setPhoneVerified(!!saved.phoneVerified);
    setSmsFallbackActive(!!saved.smsFallbackActive);
    setStep(saved.step || STEPS.CODE);
    setActionMessage(saved.actionMessage || '');
    const left = cooldownSecondsLeft(saved.cooldownUntil);
    applyCooldownSeconds(left);
    if (saved.cooldownUntil) setCooldownUntil(saved.cooldownUntil);
  };

  useEffect(() => {
    if (!isOpen) return;

    const saved = loadPhoneAuthFlow();
    const canRestore = saved?.challengeId
      && saved?.phone
      && !saved.phoneVerified
      && saved.step !== STEPS.PHONE;

    if (canRestore) {
      restoreFromSaved(saved);
    } else {
      if (saved?.phoneVerified || saved?.phoneVerified === true) {
        clearPhoneAuthFlow();
      }
      resetState(!!saved?.phone && !saved?.phoneVerified);
    }
    setTimeout(() => phoneRef.current?.focus(), 100);

    return () => clearInterval(cooldownRef.current);
  }, [isOpen]);

  useEffect(() => {
    if (!cooldownUntil || cooldownUntil <= Date.now()) {
      setResendCooldown(0);
      return undefined;
    }
    const tick = () => {
      const left = cooldownSecondsLeft(cooldownUntil);
      setResendCooldown(left);
      if (left <= 0) setCooldownUntil(0);
    };
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [cooldownUntil]);

  useEffect(() => {
    if (isOpen && challengeId) persistFlow();
  }, [
    isOpen, challengeId, phone, step, deliveryChannel, phoneVerified,
    smsFallbackActive, cooldownUntil, actionMessage, persistFlow,
  ]);

  if (!isOpen) return null;

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

  const startCooldown = (seconds = 60) => {
    const until = Date.now() + seconds * 1000;
    setCooldownUntil(until);
    applyCooldownSeconds(seconds);
  };

  const goToLoginSuccess = (data) => {
    clearPhoneAuthFlow();
    if (data.redirect) {
      window.location.href = data.redirect;
    }
  };

  // ── Навигация назад (без повторной отправки кода) ───────────────────────

  const goBackToPhone = () => {
    setStep(STEPS.PHONE);
    setError('');
    setCode('');
    setActionMessage('');
    setChallengeId(null);
    setPhoneVerified(false);
    setRequiresPassword(false);
    setMaskedPhone('');
    clearPhoneAuthFlow();
  };

  const goBackToCode = () => {
    setStep(STEPS.CODE);
    setError('');
    setPassword('');
    setCode('');
  };

  const goBackToPassword = async () => {
    setError('');
    setCode('');
    setNewPassword('');
    setNewPasswordConfirm('');
    setResetDeliveryHint('');

    if (challengeId) {
      setLoading(true);
      try {
        await apiPost('/auth/phone/forgot-password/cancel', { challenge_id: challengeId });
      } catch {
        // игнорируем — всё равно возвращаем на экран пароля
      } finally {
        setLoading(false);
      }
    }

    setStep(STEPS.PASSWORD);
  };

  // ── Шаг 1: телефон ───────────────────────────────────────────────────────

const handleSendPhone = async (e) => {
  e?.preventDefault();
  if (phone.length < 11) {
    setError('Введите полный номер телефона');
    return;
  }
  setLoading(true);
  setError('');
  try {
    // Добавляем параметр preferred_channel = 'sms'
    const data = await apiPost('/auth/phone/send-code', { 
      phone, 
      force_resend: false,
      preferred_channel: 'sms' // ПРИОРИТЕТ SMS
    });
    
    if (data.success) {
      if (data.skip_otp) {
        if (data.requires_password) {
          setChallengeId(data.challenge_id);
          setRequiresPassword(true);
          setPhoneVerified(true);
          setStep(STEPS.PASSWORD);
          persistFlow({
            challengeId: data.challenge_id,
            phoneVerified: true,
            step: STEPS.PASSWORD,
            requiresPassword: true,
          });
          return;
        }
        goToLoginSuccess(data);
        return;
      }
      
      setChallengeId(data.challenge_id);
      setDeliveryChannel(data.delivery_channel); // 'sms' или 'notification'
      setMaskedPhone(data.masked_phone);
      setRequiresPassword(data.requires_password);
      setPhoneVerified(false);
      setSmsFallbackActive(data.delivery_channel === 'sms'); // Если пришло SMS, fallback уже активен
      setStep(STEPS.CODE);
      startCooldown(data.cooldown_seconds ?? 60);
      
      // Сообщение для пользователя
      if (data.delivery_channel === 'sms') {
        setActionMessage(`Код отправлен по SMS на ${data.masked_phone || formatPhone(phone)}`);
      } else {
        setActionMessage(
          'Вы уже авторизованы на другом устройстве. ' +
          'Код отправлен в уведомления. Если не получили SMS, нажмите "Отправить SMS" ниже.'
        );
      }
      
      persistFlow({
        challengeId: data.challenge_id,
        step: STEPS.CODE,
        cooldownUntil: Date.now() + (data.cooldown_seconds ?? 60) * 1000,
      });
    } else {
      if (data.cooldown_seconds) startCooldown(data.cooldown_seconds);
      setError(data.message || 'Ошибка, попробуйте ещё раз');
    }
  } catch {
    setError('Ошибка соединения');
  } finally {
    setLoading(false);
  }
};


  // ── Шаг 2: код ───────────────────────────────────────────────────────────

  const handleVerifyCode = async (currentCode) => {
    const c = currentCode ?? code;
    if (c.length !== 6) return;
    setLoading(true);
    setError('');
    try {
      const data = await apiPost('/auth/phone/verify-code', {
        challenge_id: challengeId,
        code: c,
      });
      if (data.success) {
        setPhoneVerified(true);
        if (data.requires_password) {
          setRequiresPassword(true);
          setStep(STEPS.PASSWORD);
          persistFlow({ phoneVerified: true, step: STEPS.PASSWORD });
        } else {
          goToLoginSuccess(data);
        }
      } else {
        setError(data.message || 'Неверный код');
        setCode('');
      }
    } catch {
      setError('Ошибка соединения');
      setCode('');
    } finally {
      setLoading(false);
    }
  };

  const skipToPasswordIfVerified = () => {
    if (phoneVerified && requiresPassword) {
      setStep(STEPS.PASSWORD);
      setError('');
      return true;
    }
    return false;
  };

const handleForceSms = async () => {
  if (loading || resendCooldown > 0) return;
  setLoading(true);
  setError('');
  try {
    const data = await apiPost('/auth/phone/send-code', { 
      phone, 
      force_resend: true,
      preferred_channel: 'sms' // ПРИНУДИТЕЛЬНО SMS
    });
    
    if (data.success) {
      if (data.skip_otp) {
        if (data.requires_password) {
          setChallengeId(data.challenge_id ?? challengeId);
          setRequiresPassword(true);
          setPhoneVerified(true);
          setStep(STEPS.PASSWORD);
          return;
        }
        goToLoginSuccess(data);
        return;
      }
      
      setChallengeId(data.challenge_id);
      setDeliveryChannel('sms');
      setMaskedPhone(data.masked_phone);
      setPhoneVerified(false);
      setSmsFallbackActive(true);
      setCode('');
      setActionMessage(`SMS отправлено на ${data.masked_phone || formatPhone(phone)}`);
      startCooldown(data.cooldown_seconds ?? 60);
      
      persistFlow({
        deliveryChannel: 'sms',
        smsFallbackActive: true,
        cooldownUntil: Date.now() + (data.cooldown_seconds ?? 60) * 1000,
      });
    } else {
      if (data.cooldown_seconds) startCooldown(data.cooldown_seconds);
      setError(data.message || 'Ошибка при отправке SMS');
    }
  } catch {
    setError('Ошибка соединения');
  } finally {
    setLoading(false);
  }
};


const handleResendCode = async () => {
  if (resendCooldown > 0 || loading) return;
  setLoading(true);
  setError('');
  try {
    // Повторная отправка в том же канале, что и был
    const data = await apiPost('/auth/phone/send-code', { 
      phone, 
      force_resend: true,
      preferred_channel: deliveryChannel // сохраняем текущий канал
    });
    
    if (data.success) {
      setChallengeId(data.challenge_id);
      setDeliveryChannel(data.delivery_channel);
      setMaskedPhone(data.masked_phone);
      setPhoneVerified(false);
      setSmsFallbackActive(data.delivery_channel === 'sms');
      setCode('');
      
      if (data.delivery_channel === 'sms') {
        setActionMessage(`Код отправлен по SMS на ${data.masked_phone || formatPhone(phone)}`);
      } else {
        setActionMessage('Код отправлен в уведомления');
      }
      
      startCooldown(data.cooldown_seconds ?? 60);
    } else {
      if (data.cooldown_seconds) startCooldown(data.cooldown_seconds);
      setError(data.message || 'Ошибка при отправке');
    }
  } catch {
    setError('Ошибка соединения');
  } finally {
    setLoading(false);
  }
};

  const showSmsFallbackButton =
    !phoneVerified
    && deliveryChannel === 'notification'
    && !smsFallbackActive;

  const smsFallbackButtonLabel = () => {
    if (loading) return 'Отправляем SMS...';
    return 'Нет доступа к устройству — отправить SMS';
  };

  const resendButtonLabel = () => {
    if (loading) return 'Отправляем...';
    if (resendCooldown > 0) return `Повторная отправка через ${resendCooldown} с`;
    return 'Отправить код снова';
  };

  // ── Шаг 3: пароль (2FA) ──────────────────────────────────────────────────

  const handleCompleteLogin = async (e) => {
    e?.preventDefault();
    if (!password) {
      setError('Введите пароль');
      return;
    }
    setLoading(true);
    setError('');
    try {
      const data = await apiPost('/auth/phone/complete-login', {
        challenge_id: challengeId,
        password,
      });
      if (data.success) {
        goToLoginSuccess(data);
      } else {
        setError(data.message || 'Неверный пароль');
      }
    } catch {
      setError('Ошибка соединения');
    } finally {
      setLoading(false);
    }
  };

  // ── Сброс пароля ─────────────────────────────────────────────────────────

  const handleForgotPasswordSend = async () => {
    setLoading(true);
    setError('');
    try {
      const data = await apiPost('/auth/phone/forgot-password/send', {
        challenge_id: challengeId,
      });
      if (data.success) {
        setResetDeliveryHint(
          data.message
            || (data.masked_target
              ? `Код отправлен на ${data.masked_target}. Проверьте входящие и папку «Спам».`
              : 'Код отправлен на привязанную почту. Проверьте входящие и папку «Спам».')
        );
        setCode('');
        setStep(STEPS.FORGOT_CODE);
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

  const handleForgotVerifyCode = async (currentCode) => {
    const c = currentCode ?? code;
    if (c.length !== 6) return;
    setLoading(true);
    setError('');
    try {
      const data = await apiPost('/auth/phone/forgot-password/verify', {
        challenge_id: challengeId,
        code: c,
      });
      if (data.success) {
        setStep(STEPS.FORGOT_PASSWORD);
        setCode('');
      } else {
        setError(data.message || 'Неверный код');
        setCode('');
      }
    } catch {
      setError('Ошибка соединения');
      setCode('');
    } finally {
      setLoading(false);
    }
  };

  const handleForgotReset = async (e) => {
    e?.preventDefault();
    if (newPassword.length < 4) {
      setError('Пароль должен быть не короче 4 символов');
      return;
    }
    if (newPassword !== newPasswordConfirm) {
      setError('Пароли не совпадают');
      return;
    }
    setLoading(true);
    setError('');
    try {
      const data = await apiPost('/auth/phone/forgot-password/reset', {
        challenge_id: challengeId,
        password: newPassword,
        password_confirmation: newPasswordConfirm,
      });
      if (data.success) {
        goToLoginSuccess(data);
      } else {
        setError(data.message || 'Ошибка сброса пароля');
      }
    } catch {
      setError('Ошибка соединения');
    } finally {
      setLoading(false);
    }
  };

  const handleCodeChange = (e, onComplete) => {
    const val = e.target.value.replace(/\D/g, '').slice(0, 6);
    setCode(val);
    if (val.length === 6 && !loading) {
      onComplete(val);
    }
  };

  const handleCodeStepBack = () => {
    if (phoneVerified && requiresPassword) {
      setStep(STEPS.PASSWORD);
      setError('');
      setCode('');
      return;
    }
    goBackToPhone();
  };

  const codeSubtitle = phoneVerified
    ? 'Телефон уже подтверждён. Можете вернуться к вводу пароля или запросить код снова.'
    : deliveryChannel === 'notification'
      ? 'Код отправлен в уведомления. Откройте «Сообщения» → «Уведомления» на устройстве, где вы уже вошли.'
      : `Отправили код на ${maskedPhone || formatPhone(phone)}`;

  return (
    <div className="modal-overlay" onClick={handleClose}>
      <div className="modal-content phone-auth-modal" onClick={(e) => e.stopPropagation()}>
        <button type="button" className="modal-close" onClick={handleClose}>×</button>

        {step === STEPS.PHONE && (
          <form onSubmit={handleSendPhone} className="modal-form">
            <h2 className="phone-auth-title">Войти или зарегистрироваться</h2>
            <p className="phone-auth-subtitle">
              Введите номер. Если аккаунт открыт на другом устройстве — код придёт в уведомления на сайте.
            </p>

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
                onClick={() => { handleClose(); window.location.href = `/auth/${provider}`; }}
              >
                <img src={`/img/auth/${provider}.png`} alt={provider} className="modal-google-icon" />
                <span>{provider === 'github' ? 'GitHub' : provider.charAt(0).toUpperCase() + provider.slice(1)}</span>
              </button>
            ))}
          </form>
        )}

        {step === STEPS.CODE && (
          <form onSubmit={(e) => { e.preventDefault(); if (!skipToPasswordIfVerified()) handleVerifyCode(); }} className="modal-form">
            <button type="button" className="phone-auth-back" onClick={handleCodeStepBack}>← Назад</button>
            <h2 className="phone-auth-title">
              {deliveryChannel === 'notification' && !phoneVerified ? 'Код в уведомлениях' : 'Введите код'}
            </h2>
            <p className="phone-auth-subtitle">{codeSubtitle}</p>

       {!phoneVerified && (
  <>
    <div className="modal-form-group">
      <label className="phone-auth-label">Код подтверждения</label>
      <input
        type="text"
        value={code}
        onChange={(e) => handleCodeChange(e, handleVerifyCode)}
        placeholder="000000"
        className="modal-input phone-auth-code-input"
        inputMode="numeric"
        maxLength={6}
        autoFocus
        autoComplete="one-time-code"
      />
    </div>

    {error && <p className="modal-error">{error}</p>}
    {actionMessage && <p className="phone-auth-action-message">{actionMessage}</p>}

    <button type="submit" className="phone-auth-btn" disabled={loading || code.length !== 6}>
      {loading ? 'Проверяем...' : 'Продолжить'}
    </button>

    {/* Кнопка для принудительной отправки SMS всегда доступна, если не в режиме ожидания */}
    <button
      type="button"
      className="phone-auth-resend phone-auth-resend--sms"
      onClick={handleForceSms}
      disabled={loading || resendCooldown > 0}
    >
      {resendCooldown > 0 
        ? `Отправить SMS через ${resendCooldown} с` 
        : 'Отправить код по SMS'}
    </button>

    {/* Кнопка повторной отправки в текущем канале */}
    <button
      type="button"
      className={`phone-auth-resend${resendCooldown > 0 ? ' is-waiting' : ''}`}
      onClick={handleResendCode}
      disabled={resendCooldown > 0 || loading}
    >
      {resendButtonLabel()}
    </button>
  </>
)}

            {phoneVerified && requiresPassword && (
              <>
                {error && <p className="modal-error">{error}</p>}
                <button
                  type="button"
                  className="phone-auth-btn"
                  onClick={() => setStep(STEPS.PASSWORD)}
                >
                  К вводу пароля
                </button>
              </>
            )}
          </form>
        )}

        {step === STEPS.PASSWORD && (
          <form onSubmit={handleCompleteLogin} className="modal-form">
            <button
              type="button"
              className="phone-auth-back"
              onClick={() => {
                if (phoneVerified) {
                  setStep(STEPS.CODE);
                  setError('');
                  setPassword('');
                } else {
                  goBackToCode();
                }
              }}
            >
              ← Назад
            </button>
            <h2 className="phone-auth-title">Введите пароль</h2>
            <p className="phone-auth-subtitle">Двухэтапная проверка для {formatPhone(phone)}</p>

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

            <button
              type="button"
              className="phone-auth-resend"
              onClick={handleForgotPasswordSend}
              disabled={loading}
            >
              Забыли пароль?
            </button>
          </form>
        )}

        {step === STEPS.FORGOT_CODE && (
          <form onSubmit={(e) => { e.preventDefault(); handleForgotVerifyCode(); }} className="modal-form">
            <button type="button" className="phone-auth-back" onClick={goBackToPassword} disabled={loading}>
              ← Назад
            </button>
            <h2 className="phone-auth-title">Код для сброса</h2>
            <p className="phone-auth-subtitle">
              {resetDeliveryHint || 'Код отправлен на привязанную почту.'}
            </p>

            <div className="modal-form-group">
              <label className="phone-auth-label">Код подтверждения</label>
              <input
                type="text"
                value={code}
                onChange={(e) => handleCodeChange(e, handleForgotVerifyCode)}
                placeholder="6 цифр из письма"
                className="modal-input phone-auth-code-input"
                inputMode="numeric"
                maxLength={6}
                autoFocus
                autoComplete="one-time-code"
              />
            </div>

            {error && <p className="modal-error">{error}</p>}

            <button type="submit" className="phone-auth-btn" disabled={loading || code.length !== 6}>
              {loading ? 'Проверяем...' : 'Продолжить'}
            </button>
          </form>
        )}

        {step === STEPS.FORGOT_PASSWORD && (
          <form onSubmit={handleForgotReset} className="modal-form">
            <button
              type="button"
              className="phone-auth-back"
              onClick={() => { setStep(STEPS.FORGOT_CODE); setError(''); }}
            >
              ← Назад
            </button>
            <h2 className="phone-auth-title">Новый пароль</h2>
            <p className="phone-auth-subtitle">Придумайте новый пароль для аккаунта</p>

            <div className="modal-form-group">
              <label className="phone-auth-label">Новый пароль</label>
              <input
                type="password"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                className="modal-input"
                autoFocus
              />
            </div>

            <div className="modal-form-group">
              <label className="phone-auth-label">Повторите пароль</label>
              <input
                type="password"
                value={newPasswordConfirm}
                onChange={(e) => setNewPasswordConfirm(e.target.value)}
                className="modal-input"
              />
            </div>

            {error && <p className="modal-error">{error}</p>}

            <button type="submit" className="phone-auth-btn" disabled={loading || !newPassword || !newPasswordConfirm}>
              {loading ? 'Сохраняем...' : 'Сохранить и войти'}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}