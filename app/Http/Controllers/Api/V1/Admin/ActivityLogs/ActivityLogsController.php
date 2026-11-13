<?php

namespace App\Http\Controllers\Api\V1\Admin\ActivityLogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ActivityLogs\AdminActivityLogsRequest;
use App\Http\Resources\Api\V1\Admin\ActivityLogs\ActivityLogResource;
use App\Models\UserActivityLog;
use App\Services\Api\V1\Admin\ActivityLogService;
use App\Services\Api\V1\Admin\UserActivityLogger;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogsController extends Controller
{
    public function index(AdminActivityLogsRequest $request, ActivityLogService $logs): JsonResponse
    {
        $viewer = $request->user();
        $filters = $request->validated();
        $paginator = $logs->paginate($viewer, $filters);

        return response()->json([
            'data' => ActivityLogResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'statistics' => $logs->statistics($viewer, $filters),
            ],
        ]);
    }

    public function statistics(AdminActivityLogsRequest $request, ActivityLogService $logs): JsonResponse
    {
        return response()->json(['data' => $logs->statistics($request->user(), $request->validated())]);
    }

    public function filters(AdminActivityLogsRequest $request, ActivityLogService $logs): JsonResponse
    {
        return response()->json(['data' => $logs->filterOptions($request->user(), $request->validated('actor_search'))]);
    }

    public function show(AdminActivityLogsRequest $request, int $activityLog, ActivityLogService $logs): JsonResponse
    {
        return response()->json(['data' => ActivityLogResource::make($logs->findVisible($request->user(), $activityLog))->resolve($request)]);
    }

    public function export(
        AdminActivityLogsRequest $request,
        ActivityLogService $logs,
        UserActivityLogger $activityLogger,
    ): StreamedResponse {
        $viewer = $request->user();
        $filters = $logs->normalizedFilters($request->validated());
        $rows = $logs->exportRecords($viewer, $filters);
        $filename = 'planora-activity-logs-'.$filters['from'].'-to-'.$filters['to'].'.csv';
        $header = [
            'Event ID', 'Actor Name', 'Actor Email', 'Actor Role', 'Action', 'Description', 'Module',
            'Target Type', 'Target ID', 'Target', 'Result', 'IP Address', 'HTTP Method', 'Request Path',
            'Request ID', 'Timestamp',
        ];

        return response()->streamDownload(function () use ($rows, $header, $activityLogger, $viewer, $filters): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");

            try {
                fputcsv($stream, $header, ',', '"', '\\');
                foreach ($rows as $log) {
                    /** @var UserActivityLog $log */
                    $actor = $log->actor;
                    $row = [
                        $log->event_id,
                        $log->actor_name ?? $actor?->name,
                        $log->actor_email ?? $actor?->email,
                        $log->actor_role ?? $actor?->role,
                        $log->event,
                        $log->description,
                        $log->module ?: $this->legacyModule($log->event),
                        $log->subject_type ?: ($log->user ? 'User' : ''),
                        $log->subject_id ?: ($log->user_id ?? ''),
                        $log->subject_label ?? $log->user?->name,
                        $log->status ?: 'success',
                        $log->ip_address,
                        $log->request_method,
                        $log->request_path,
                        $log->request_id,
                        $log->created_at?->toIso8601String(),
                    ];
                    fputcsv($stream, array_map([$this, 'safeCsvCell'], $row), ',', '"', '\\');
                }

                $activityLogger->record(
                    'report.activity_logs_exported',
                    'An authorized operator exported filtered activity log records.',
                    actor: $viewer,
                    context: ['filters' => array_intersect_key($filters, array_flip(['from', 'to', 'actor_id', 'module', 'subject_type', 'event', 'status']))],
                    module: 'Reports',
                );
            } finally {
                fclose($stream);
            }
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsvCell(mixed $value): string|int|float|null
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[\p{Z}\x00-\x20]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    private function legacyModule(string $event): string
    {
        return match (true) {
            str_starts_with($event, 'auth.') => 'Authentication',
            str_starts_with($event, 'category.') => 'Categories',
            str_starts_with($event, 'project.') => 'Projects',
            str_starts_with($event, 'task.') => 'Tasks',
            str_starts_with($event, 'report.') => 'Reports',
            str_starts_with($event, 'settings.') => 'Settings',
            default => 'Users',
        };
    }
}
