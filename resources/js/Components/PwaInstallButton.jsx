import { useCallback, useEffect, useState } from 'react';
import {
  getDeferredInstallPrompt,
  isPwaInstalled,
  promptPwaInstall,
  wasInstallDismissed,
} from '@/pwa';
import '../../css/pwa-install.css';

function DownloadIcon() {
  return (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path
        d="M12 3v12m0 0l4-4m-4 4L8 11"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path
        d="M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
      />
    </svg>
  );
}

export default function PwaInstallButton() {
  const [visible, setVisible] = useState(false);

  const refreshVisibility = useCallback(() => {
    const canInstall = Boolean(getDeferredInstallPrompt())
      && !isPwaInstalled()
      && !wasInstallDismissed();
    setVisible(canInstall);
  }, []);

  useEffect(() => {
    refreshVisibility();

    const onAvailability = () => refreshVisibility();
    window.addEventListener('pwa-install-availability', onAvailability);

    return () => {
      window.removeEventListener('pwa-install-availability', onAvailability);
    };
  }, [refreshVisibility]);

  const handleInstall = async () => {
    const { outcome } = await promptPwaInstall();
    if (outcome === 'dismissed') {
      setVisible(false);
    }
  };

  if (!visible) {
    return null;
  }

  return (
    <div className="pwa-install-wrap" role="region" aria-label="Установка приложения">
      <button
        type="button"
        className="pwa-install-btn"
        onClick={handleInstall}
      >
        <DownloadIcon />
        Установить приложение
      </button>
    </div>
  );
}
