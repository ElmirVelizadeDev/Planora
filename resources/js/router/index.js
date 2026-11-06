import { createRouter, createWebHistory } from 'vue-router';
import { routes } from './routes.js';
import { installRouteGuards } from './guards.js';

const router = createRouter({
    // Laravel serves the SPA at the domain root. Vite's BASE_URL points to /build/
    // for generated assets and must not be reused as the Vue Router URL prefix.
    history: createWebHistory('/'),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

installRouteGuards(router);

export default router;
