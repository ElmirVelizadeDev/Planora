<?php

return [
    'moderator_permissions' => [
        'users.view' => [
            'name' => 'View users',
            'description' => 'View and search non-admin user accounts.',
            'module' => 'Users', 'allowed_role' => 'moderator', 'assignable' => true, 'sensitive' => false,
        ],
        'categories.view' => [
            'name' => 'View categories',
            'description' => 'View active and archived categories without changing them.',
            'module' => 'Categories', 'allowed_role' => 'moderator', 'assignable' => true, 'sensitive' => false,
        ],
        'projects.view' => [
            'name' => 'View projects',
            'description' => 'View projects and related task information without editing or deleting records.',
            'module' => 'Projects', 'allowed_role' => 'moderator', 'assignable' => true, 'sensitive' => false,
        ],
        'projects.status.manage' => [
            'name' => 'Manage project status',
            'description' => 'Change a project status only. Requires View projects.',
            'module' => 'Projects', 'allowed_role' => 'moderator', 'assignable' => true, 'sensitive' => true,
            'requires' => ['projects.view'],
        ],
        'tasks.view' => [
            'name' => 'View tasks',
            'description' => 'View active tasks and task details without editing, deleting, or restoring them.',
            'module' => 'Tasks', 'allowed_role' => 'moderator', 'assignable' => true, 'sensitive' => false,
        ],
        'tasks.status.manage' => [
            'name' => 'Manage task status',
            'description' => 'Change task status only. Requires View tasks.',
            'module' => 'Tasks', 'allowed_role' => 'moderator', 'assignable' => true, 'sensitive' => true,
            'requires' => ['tasks.view'],
        ],
        'reports.view' => [
            'name' => 'View reports',
            'description' => 'View Reports & Analytics. CSV export remains Admin-only.',
            'module' => 'Reports', 'allowed_role' => 'moderator', 'assignable' => true, 'sensitive' => true,
        ],
        'activity.view' => [
            'name' => 'View activity logs',
            'description' => 'Review permitted user and authentication activity.',
            'module' => 'Activity Logs', 'allowed_role' => 'moderator', 'assignable' => true, 'sensitive' => true,
        ],
        'users.status.manage' => [
            'name' => 'Manage user status',
            'description' => 'Suspend or ban non-admin user accounts. Requires View users.',
            'module' => 'Users', 'allowed_role' => 'moderator', 'assignable' => true, 'sensitive' => true,
            'requires' => ['users.view'],
        ],
    ],

    'roles' => [
        'user' => 'Personal workspace and own productivity data.',
        'moderator' => 'Limited user visibility and only explicitly assigned moderation actions.',
        'admin' => 'Full platform administration.',
    ],
];
