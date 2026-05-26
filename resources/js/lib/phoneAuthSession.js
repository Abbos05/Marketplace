const STORAGE_KEY = 'phone_auth_flow';

export function loadPhoneAuthFlow() {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

export function savePhoneAuthFlow(data) {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  } catch {
    /* ignore */
  }
}

export function clearPhoneAuthFlow() {
  try {
    sessionStorage.removeItem(STORAGE_KEY);
  } catch {
    /* ignore */
  }
}

export function cooldownSecondsLeft(cooldownUntil) {
  if (!cooldownUntil) return 0;
  return Math.max(0, Math.ceil((cooldownUntil - Date.now()) / 1000));
}
