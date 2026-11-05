import { createRouter, createWebHistory } from 'vue-router';
import { routes } from './routes.js';
import { installRouteGuards } from './guards.js';

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

installRouteGuards(router);

export default router;
