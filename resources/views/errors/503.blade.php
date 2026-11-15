@include('errors.page', [
    'code' => 503,
    'title' => 'We will be back soon',
    'description' => config('planora.maintenance.message'),
    'estimatedAt' => config('planora.maintenance.estimated_at'),
    'retryAfter' => $retryAfter ?? config('planora.maintenance.retry_after', 60),
])
