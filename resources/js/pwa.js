/**
 * PWA: регистрация Service Worker и событие установки (beforeinstallprompt).
 */

let deferredInstallPrompt = null;

const INSTALL_DISMISSED_KEY = 'alvora-pwa-install-dismissed';

export function getDeferredInstallPrompt() {
  return deferredInstallPrompt;
}

export function isPwaInstalled() {
  return (
    window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true
  );
}

export function wasInstallDismissed() {
  try {
    return localStorage.getItem(INSTALL_DISMISSED_KEY) === '1';
  } catch {
    return false;
  }
}

export function markInstallDismissed() {
  try {
    localStorage.setItem(INSTALL_DISMISSED_KEY, '1');
  } catch {
    /* ignore */
  }
}

export async function promptPwaInstall() {
  if (!deferredInstallPrompt) {
    return { outcome: 'unavailable' };
  }

  deferredInstallPrompt.prompt();
  const { outcome } = await deferredInstallPrompt.userChoice;
  deferredInstallPrompt = null;

  if (outcome === 'accepted') {
    markInstallDismissed();
  }

  window.dispatchEvent(new CustomEvent('pwa-install-availability', { detail: { canInstall: false } }));
  return { outcome };
}

function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) {
    return;
  }

  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register('/sw.js', { scope: '/' })
      .then((registration) => {
        registration.addEventListener('updatefound', () => {
          const installing = registration.installing;
          if (!installing) {
            return;
          }
          installing.addEventListener('statechange', () => {
            if (installing.state === 'installed' && navigator.serviceWorker.controller) {
              console.info('[PWA] Доступно обновление. Перезагрузите страницу.');
            }
          });
        });
      })
      .catch((err) => {
        console.warn('[PWA] Service Worker не зарегистрирован:', err);
      });
  });
}

function listenInstallPrompt() {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    window.dispatchEvent(
      new CustomEvent('pwa-install-availability', { detail: { canInstall: true } }),
    );
  });

  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    markInstallDismissed();
    window.dispatchEvent(
      new CustomEvent('pwa-install-availability', { detail: { canInstall: false } }),
    );
  });
}

export function initPwa() {
  registerServiceWorker();
  listenInstallPrompt();
}

initPwa();
