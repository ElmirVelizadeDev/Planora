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

        if (error.response?.status === 428 && error.response.data?.step_up_required && !error.config?._planoraStepUpRetried) {
            return new Promise((resolve, reject) => {
                const config = { ...error.config, _planoraStepUpRetried: true };
                window.dispatchEvent(new CustomEvent('planora:step-up-required', {
                    detail: {
                        purpose: error.response.data.purpose,
                        verify: async (credentials) => {
                            const { data } = await api.post('/admin/step-up/verify', { purpose: error.response.data.purpose, ...credentials });
                            resolve(api.request(config));
                            return data;
                        },
                        cancel: () => reject(error),
                    },
                }));
            });
        }

        return Promise.reject(error);
    },
);

export default api;
