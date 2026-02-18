import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Configure axios to read CSRF token from meta tag on each request
// This ensures the token is always current (proper Laravel Inertia approach)
window.axios.interceptors.request.use((config) => {
    const token = document.head.querySelector('meta[name="csrf-token"]');
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token.content;
    }
    return config;
});
