/**
 * PWA для страниц без React (test-mode-access и др.)
 */
(function () {
  const TEST_DISMISS_KEY = 'alvora-pwa-test-install-dismissed';
  let deferredInstallPrompt = null;

  function isPwaInstalled() {
    return (
      window.matchMedia('(display-mode: standalone)').matches
      || window.navigator.standalone === true
    );
  }

  function wasTestInstallDismissed() {
    try {
      return localStorage.getItem(TEST_DISMISS_KEY) === '1';
    } catch {
      return false;
    }
  }

  function markTestInstallDismissed() {
    try {
      localStorage.setItem(TEST_DISMISS_KEY, '1');
    } catch {
      /* ignore */
    }
  }

  function getModal() {
    return document.getElementById('pwaInstallModal');
  }

  function getInstallBtn() {
    return document.getElementById('pwaInstallBtn');
  }

  function openPwaModal() {
    if (isPwaInstalled() || wasTestInstallDismissed()) {
      return;
    }
    const modal = getModal();
    if (!modal) {
      return;
    }
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('pwa-modal-open');
    updateInstallButton();
  }

  function closePwaModal() {
    const modal = getModal();
    if (!modal) {
      return;
    }
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('pwa-modal-open');
  }

  function updateInstallButton() {
    const btn = getInstallBtn();
    if (!btn) {
      return;
    }
    const ready = Boolean(deferredInstallPrompt);
    btn.disabled = !ready;
    btn.classList.toggle('is-ready', ready);
    btn.style.display = '';
    const label = btn.querySelector('.pwa-install-btn__label');
    if (label) {
      label.textContent = ready ? 'Установить приложение' : 'Подготовка…';
    }
  }

  function showInstallUnavailable() {
    const btn = getInstallBtn();
    const hint = document.getElementById('pwaInstallHint');
    if (btn) {
      btn.style.display = 'none';
    }
    if (hint) {
      hint.style.display = 'block';
      hint.textContent = 'Установка в один клик доступна в Chrome (ПК или Android). Можно пропустить и войти по паролю.';
    }
  }

  async function promptPwaInstall() {
    if (!deferredInstallPrompt) {
      return { outcome: 'unavailable' };
    }
    deferredInstallPrompt.prompt();
    const { outcome } = await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    updateInstallButton();
    window.dispatchEvent(new CustomEvent('pwa-install-availability', { detail: { canInstall: false } }));
    return { outcome };
  }

  function bindModalActions() {
    const installBtn = getInstallBtn();
    const skipBtn = document.getElementById('pwaInstallSkip');
    const backdrop = document.querySelector('.pwa-install-modal__backdrop');

    installBtn?.addEventListener('click', async () => {
      if (!deferredInstallPrompt) {
        return;
      }
      const { outcome } = await promptPwaInstall();
      markTestInstallDismissed();
      closePwaModal();
      if (outcome === 'accepted') {
        showPwaToast('Приложение установлено');
      }
    });

    skipBtn?.addEventListener('click', () => {
      markTestInstallDismissed();
      closePwaModal();
    });

    backdrop?.addEventListener('click', () => {
      markTestInstallDismissed();
      closePwaModal();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && getModal()?.classList.contains('is-open')) {
        markTestInstallDismissed();
        closePwaModal();
      }
    });
  }

  function showPwaToast(message) {
    const toast = document.getElementById('toastMessage');
    if (!toast) {
      return;
    }
    toast.textContent = message;
    toast.style.background = '#FF2E63';
    toast.style.opacity = '1';
    setTimeout(() => {
      toast.style.opacity = '0';
    }, 3000);
  }

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
      return;
    }
    navigator.serviceWorker.register('/sw.js?v=3', { scope: '/' }).catch(() => {});
  }

  function listenInstallPrompt() {
    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault();
      deferredInstallPrompt = event;
      updateInstallButton();
      openPwaModal();
    });

    window.addEventListener('appinstalled', () => {
      deferredInstallPrompt = null;
      markTestInstallDismissed();
      closePwaModal();
      showPwaToast('Приложение установлено');
    });
  }

  function initTestPagePwaModal() {
    if (!document.body.dataset.pwaTestModal) {
      return;
    }
    if (isPwaInstalled() || wasTestInstallDismissed()) {
      return;
    }

    bindModalActions();
    registerServiceWorker();
    listenInstallPrompt();

    window.addEventListener('load', () => {
      setTimeout(() => {
        if (!isPwaInstalled() && !wasTestInstallDismissed()) {
          openPwaModal();
        }
      }, 500);

      setTimeout(() => {
        if (!deferredInstallPrompt && getModal()?.classList.contains('is-open')) {
          showInstallUnavailable();
        }
      }, 4000);
    });
  }

  window.AlvoraPwa = {
    openPwaModal,
    closePwaModal,
    promptPwaInstall,
    isPwaInstalled,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTestPagePwaModal);
  } else {
    initTestPagePwaModal();
  }
})();
