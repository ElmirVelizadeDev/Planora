<?php

return [
    'maintenance' => [
        'message' => env('PLANORA_MAINTENANCE_MESSAGE', 'Planora is temporarily unavailable while we carry out scheduled maintenance.'),
        'estimated_at' => env('PLANORA_MAINTENANCE_ESTIMATED_AT'),
        'retry_after' => (int) env('PLANORA_MAINTENANCE_RETRY_AFTER', 60),
    ],
];
