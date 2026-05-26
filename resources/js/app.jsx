import '../css/app.css';
import '../css/shared/phone-modal.css';
import './bootstrap';
import './pwa';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const syncCsrfToken = (token) => {
    if (!token) {
        return;
    }

    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
    window.setAxiosCsrfToken?.(token);
};

router.on('invalid', (event) => {
    if (window.handleCsrfMismatch?.(event.detail.response?.status)) {
        event.preventDefault();
    }
});

router.on('navigate', (event) => {
    syncCsrfToken(event.detail.page.props.csrfToken);
});

createInertiaApp({
    title: (title) => `${title}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        syncCsrfToken(props.initialPage?.props?.csrfToken);

        root.render(
      <>
        <App {...props} />
      </>
    );
    },
    progress: {
        color: '#4B5563',
    },
});


