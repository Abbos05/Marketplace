import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const setAxiosCsrfToken = (token = csrfToken()) => {
    if (token) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }
};

setAxiosCsrfToken();
window.setAxiosCsrfToken = setAxiosCsrfToken;

window.handleCsrfMismatch = (status) => {
    if (Number(status) !== 419) {
        return false;
    }

    const now = Date.now();
    const lastReload = Number(sessionStorage.getItem('csrf-refresh-at') || 0);

    if (now - lastReload < 10000) {
        return false;
    }

    sessionStorage.setItem('csrf-refresh-at', String(now));
    window.location.reload();

    return true;
};

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (window.handleCsrfMismatch(error.response?.status)) {
            return new Promise(() => {});
        }

        return Promise.reject(error);
    },
);

const nativeFetch = window.fetch;
window.fetch = async (...args) => {
    const response = await nativeFetch(...args);

    window.handleCsrfMismatch(response.status);

    return response;
};
