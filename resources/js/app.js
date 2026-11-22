import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';
import { reportRuntimeError } from './shared/utils/runtimeErrors.js';

const app = createApp(App);

app.config.errorHandler = (error, _instance, info) => {
    // Keep unexpected rendering/runtime failures out of user-facing API copy.
    // The detailed exception remains available to the developer console.
    console.error('[Planora] Unhandled Vue error:', info, error);
    reportRuntimeError();
    window.dispatchEvent(new CustomEvent('planora:runtime-error', { detail: { info } }));
};

window.addEventListener('error', (event) => {
    console.error('[Planora] Unhandled browser error:', event.error || event.message);
    reportRuntimeError();
});
window.addEventListener('unhandledrejection', (event) => {
    console.error('[Planora] Unhandled promise rejection:', event.reason);
    reportRuntimeError();
});

app
    .use(createPinia())
    .use(router)
    .mount('#app');
