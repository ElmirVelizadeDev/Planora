import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response) {
            window.dispatchEvent(new CustomEvent('planora:api-error', {
                detail: { status: error.response.status, data: error.response.data },
            }));
        }

        return Promise.reject(error);
    },
);

export default api;
