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

async function clearStalePwaCaches() {
  if (!('caches' in window)) {
    return;
  }
  const keys = await caches.keys();
  await Promise.all(
    keys
      .filter((key) => key.startsWith('alvora-'))
      .map((key) => caches.delete(key)),
  );
}

function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) {
    return;
  }

  window.addEventListener('load', async () => {
    await clearStalePwaCaches();

    try {
      const registration = await navigator.serviceWorker.register('/sw.js?v=3', { scope: '/' });
      await registration.update();

      if (registration.waiting) {
        registration.waiting.postMessage({ type: 'SKIP_WAITING' });
      }

      registration.addEventListener('updatefound', () => {
        const worker = registration.installing;
        if (!worker) {
          return;
        }
        worker.addEventListener('statechange', () => {
          if (worker.state === 'installed' && navigator.serviceWorker.controller) {
            worker.postMessage({ type: 'SKIP_WAITING' });
          }
        });
      });

      let reloaded = false;
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloaded) {
          return;
        }
        reloaded = true;
        window.location.reload();
      });
    } catch (err) {
      console.warn('[PWA] Service Worker не зарегистрирован:', err);
    }
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
