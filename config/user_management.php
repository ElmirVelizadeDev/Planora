<?php

return [
    'moderator_permissions' => [
        'users.view' => 'View non-admin user accounts',
        'activity.view' => 'View permitted user activity',
        'users.status.manage' => 'Suspend, ban, or restore non-admin accounts',
    ],

    'roles' => [
        'user' => 'Personal workspace and own productivity data.',
        'moderator' => 'Limited user visibility and only explicitly assigned moderation actions.',
        'admin' => 'Full platform administration.',
    ],
];
