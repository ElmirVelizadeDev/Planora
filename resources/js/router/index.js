import { createRouter, createWebHistory } from 'vue-router';
import { routes } from './routes.js';
import { installRouteGuards } from './guards.js';
import { applyPageMetadata } from './pageMetadata.js';

const router = createRouter({
    // Laravel serves the SPA at the domain root. Vite's BASE_URL points to /build/
    // for generated assets and must not be reused as the Vue Router URL prefix.
    history: createWebHistory('/'),
    routes,
    scrollBehavior: (to) => to.hash ? { el: to.hash } : ({ top: 0 }),
});

installRouteGuards(router);
router.afterEach((to, _from, failure) => {
    if (!failure) applyPageMetadata(to);
});

export default router;
