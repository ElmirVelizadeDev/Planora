export const routes = [
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/',
        component: () => import('../layouts/AuthLayout.vue'),
        children: [
            {
                path: 'login',
                name: 'login',
                component: () => import('../pages/auth/Login.vue'),
            },
            {
                path: 'register',
                name: 'register',
                component: () => import('../pages/auth/Register.vue'),
            },
            {
                path: 'forgot-password',
                name: 'forgot-password',
                component: () => import('../pages/auth/ForgotPassword.vue'),
            },
            {
                path: 'reset-password',
                name: 'reset-password',
                component: () => import('../pages/auth/ResetPassword.vue'),
            },
            {
                path: 'verify-email',
                name: 'verify-email',
                component: () => import('../pages/auth/VerifyEmail.vue'),
            },
        ],
    },
    {
        path: '/',
        component: () => import('../layouts/DashboardLayout.vue'),
        children: [
            {
                path: 'dashboard',
                name: 'dashboard',
                component: () => import('../pages/app/dashboard/Index.vue'),
            },
            {
                path: 'tasks',
                name: 'tasks.index',
                component: () => import('../pages/app/tasks/Index.vue'),
            },
            {
                path: 'tasks/create',
                name: 'tasks.create',
                component: () => import('../pages/app/tasks/Create.vue'),
            },
            {
                path: 'tasks/:taskId/edit',
                name: 'tasks.edit',
                component: () => import('../pages/app/tasks/Edit.vue'),
            },
            {
                path: 'tasks/:taskId',
                name: 'tasks.show',
                component: () => import('../pages/app/tasks/Show.vue'),
            },
            {
                path: 'calendar',
                name: 'calendar',
                component: () => import('../pages/app/calendar/Index.vue'),
            },
            {
                path: 'statistics',
                name: 'statistics',
                component: () => import('../pages/app/statistics/Index.vue'),
            },
            {
                path: 'categories',
                name: 'categories.index',
                component: () => import('../pages/app/categories/Index.vue'),
            },
            {
                path: 'categories/create',
                name: 'categories.create',
                component: () => import('../pages/app/categories/Create.vue'),
            },
            {
                path: 'categories/:categoryId/edit',
                name: 'categories.edit',
                component: () => import('../pages/app/categories/Edit.vue'),
            },
            {
                path: 'profile',
                name: 'profile.show',
                component: () => import('../pages/app/profile/Show.vue'),
            },
            {
                path: 'profile/edit',
                name: 'profile.edit',
                component: () => import('../pages/app/profile/Edit.vue'),
            },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('../pages/app/settings/Index.vue'),
            },
        ],
    },
];
