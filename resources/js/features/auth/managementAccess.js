const MODERATOR_ADMIN_ACCESS = {
    'admin.dashboard': ['users.view', 'categories.view', 'projects.view', 'tasks.view', 'reports.view', 'activity.view'],
    'admin.users.index': ['users.view'],
    'admin.activity-logs.index': ['activity.view'],
    'admin.categories.index': ['categories.view'],
    'admin.categories.show': ['categories.view'],
    'admin.projects.index': ['projects.view'],
    'admin.projects.show': ['projects.view'],
    'admin.tasks.index': ['tasks.view'],
    'admin.tasks.show': ['tasks.view'],
    'admin.reports': ['reports.view'],
};

export function moderatorCanAccessAdminRoute(auth, routeName) {
    if (!auth.hasRole('moderator')) return false;
    const allowed = MODERATOR_ADMIN_ACCESS[routeName];
    if (!allowed) return false;
    return allowed.some((permission) => auth.hasManagementPermission(permission));
}

export function moderatorLandingRoute(auth) {
    if (auth.hasManagementPermission('users.view')) return 'admin.users.index';
    if (auth.hasManagementPermission('categories.view')) return 'admin.categories.index';
    if (auth.hasManagementPermission('projects.view')) return 'admin.projects.index';
    if (auth.hasManagementPermission('tasks.view')) return 'admin.tasks.index';
    if (auth.hasManagementPermission('reports.view')) return 'admin.reports';
    if (auth.hasManagementPermission('activity.view')) return 'admin.activity-logs.index';
    return 'error.403';
}

export function moderatorAdminLinks(auth, links) {
    return links.filter((link) => moderatorCanAccessAdminRoute(auth, link.to.name));
}
