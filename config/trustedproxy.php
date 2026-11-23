<?php

$configured = array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))));

return [
    // Set to explicit proxy IP addresses/CIDRs supplied by the deployment operator.
    // An empty list means forwarded headers are not trusted.
    'proxies' => array_values(array_filter($configured, fn (string $proxy): bool => ! in_array($proxy, ['*', '**'], true))),
];
