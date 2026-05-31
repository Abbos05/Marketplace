import React, { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Отправка кода на новый email и подтверждение перед сохранением в профиле.
 */
export default function ProfileEmailVerificationFields({
  email,
  onEmailChange,
  currentEmail = '',
  onVerified,
  inputClassName = 'settings-input',
  labelClassName = 'settings-label',
  errorClassName = 'settings-error',
  infoClassName = 'settings-hint-text settings-hint-text--success',
  resendClassName = 'phone-auth-resend',
  showLabel = true,
  disabled = false,
}) {
  const normalizedCurrent = (currentEmail || '').trim().toLowerCase();
  const normalizedDraft = (email || '').trim().toLowerCase();
  const needsVerification = normalizedDraft !== '' && normalizedDraft !== normalizedCurrent;

  const [step, setStep] = useState('email');
  const [code, setCode] = useState('');
  const [error, setError] = useState('');
  const [info, setInfo] = useState('');
  const [processing, setProcessing] = useState(false);
  const [resendCooldown, setResendCooldown] = useState(0);

  useEffect(() => {
    if (!needsVerification) {
      setStep('email');
      setCode('');
      setError('');
      setInfo('');
      setResendCooldown(0);
    }
  }, [needsVerification, normalizedDraft]);

  useEffect(() => {
    if (resendCooldown <= 0) return undefined;
    const timer = window.setTimeout(() => {
      setResendCooldown((prev) => Math.max(prev - 1, 0));
    }, 1000);
    return () => window.clearTimeout(timer);
  }, [resendCooldown]);

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

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

  const sendCode = async () => {
    if (!needsVerification) return false;
    if (resendCooldown > 0) {
      setInfo(`Повторная отправка будет доступна через ${resendCooldown} с.`);
      return false;
    }

    const trimmed = email.trim();
    if (!trimmed || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed)) {
      setError('Введите корректный email.');
      return false;
    }

    setProcessing(true);
    setError('');
    setInfo('');

    try {
      const payload = await apiPost('/profile/email/send-code', { email: trimmed });
      setStep('code');
      const cooldownSeconds = Number(payload?.cooldown_seconds || 60);
      setResendCooldown(cooldownSeconds);
      setInfo(
        payload?.message
          || `Код отправлен. Повторная отправка будет доступна через ${cooldownSeconds} секунд.`,
      );
      if (payload?.email_sent === false) {
        setInfo(payload.message || 'Не удалось отправить код. Временно введите 000000.');
      }
      return true;
    } catch (err) {
      setError(err?.errors?.email?.[0] || err?.message || 'Не удалось отправить код.');
      return false;
    } finally {
      setProcessing(false);
    }
  };

  const verifyCode = async () => {
    if (code.trim().length !== 6) {
      setError('Введите 6 цифр кода подтверждения.');
      return false;
    }

    setProcessing(true);
    setError('');

    try {
      const payload = await apiPost('/profile/email/verify-code', { code: code.trim() });
      const verifiedEmail = payload?.email || email.trim();
      onEmailChange?.(verifiedEmail);
      onVerified?.(verifiedEmail);
      setStep('email');
      setCode('');
      setInfo('Email подтверждён.');
      router.reload({ only: ['auth', 'flash'] });
      return true;
    } catch (err) {
      setError(err?.errors?.code?.[0] || err?.message || 'Неверный код подтверждения.');
      return false;
    } finally {
      setProcessing(false);
    }
  };

  if (!needsVerification) {
    return null;
  }

  return (
    <div className="profile-email-verify">
      {step === 'email' && (
        <div className="profile-email-verify-actions">
          <button
            type="button"
            className="settings-save-btn"
            onClick={sendCode}
            disabled={disabled || processing}
          >
            {processing ? 'Отправка...' : 'Отправить код на почту'}
          </button>
        </div>
      )}

      {step === 'code' && (
        <div className="settings-field" style={{ marginTop: '0.75rem' }}>
          {showLabel && (
            <label className={labelClassName}>
              Код подтверждения <span className="required">*</span>
            </label>
          )}
          <input
            type="text"
            inputMode="numeric"
            value={code}
            onChange={(e) => {
              setCode(e.target.value.replace(/\D/g, '').slice(0, 6));
              setError('');
            }}
            placeholder="000000"
            className={`${inputClassName} phone-auth-code-input`}
            maxLength={6}
            disabled={disabled || processing}
          />
          <button
            type="button"
            className={`${resendClassName}${resendCooldown > 0 ? ' is-waiting' : ''}`}
            onClick={sendCode}
            disabled={processing || resendCooldown > 0}
          >
            {resendCooldown > 0
              ? `Отправить повторно через ${resendCooldown} с`
              : 'Отправить код ещё раз'}
          </button>
          <button
            type="button"
            className="settings-save-btn"
            style={{ marginTop: '0.5rem' }}
            onClick={verifyCode}
            disabled={disabled || processing}
          >
            {processing ? 'Проверка...' : 'Подтвердить email'}
          </button>
        </div>
      )}

      {info && <p className={infoClassName}>{info}</p>}
      {error && <p className={errorClassName}>{error}</p>}
    </div>
  );
}

export function profileEmailNeedsVerification(email, currentEmail) {
  const draft = (email || '').trim().toLowerCase();
  const current = (currentEmail || '').trim().toLowerCase();
  return draft !== '' && draft !== current;
}
