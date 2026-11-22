<?php

namespace App\Services\Api\V1\User\Tasks;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class UserTaskReportService
{
    public function __construct(private readonly UserTaskService $tasks)
    {
    }

    public function generate(int $userId, array $filters): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $filters['start_date'], 'UTC');
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $filters['end_date'], 'UTC');
        $query = $this->tasks->queryFor($userId)
            ->where('created_at', '>=', $start->startOfDay())
            ->where('created_at', '<', $end->addDay()->startOfDay());

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        $statusCounts = (clone $query)->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
        $priorityCounts = (clone $query)->select('priority', DB::raw('count(*) as aggregate'))->groupBy('priority')->pluck('aggregate', 'priority');
        $trendCounts = (clone $query)->selectRaw('DATE(created_at) as report_date, count(*) as aggregate')
            ->groupBy('report_date')->orderBy('report_date')->pluck('aggregate', 'report_date');
        $today = now()->toDateString();
        $total = (clone $query)->count();
        $completed = (int) ($statusCounts['completed'] ?? 0);
        $trend = [];
        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $key = $date->format('Y-m-d');
            $trend[] = ['date' => $key, 'count' => (int) ($trendCounts[$key] ?? 0)];
        }

        $recent = (clone $query)->with('category')->orderByDesc('created_at')->orderByDesc('id')->limit(8)->get();

        return [
            'period' => ['start_date' => $start->format('Y-m-d'), 'end_date' => $end->format('Y-m-d')],
            'metrics' => [
                'total' => $total,
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                'completed' => $completed,
                'overdue' => (clone $query)->where('status', '!=', 'completed')->whereNotNull('due_date')->whereDate('due_date', '<', $today)->count(),
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ],
            'status_breakdown' => collect(['pending', 'in_progress', 'completed'])->map(fn ($status) => ['key' => $status, 'count' => (int) ($statusCounts[$status] ?? 0)])->all(),
            'priority_breakdown' => collect(['urgent', 'high', 'medium', 'low'])->map(fn ($priority) => ['key' => $priority, 'count' => (int) ($priorityCounts[$priority] ?? 0)])->all(),
            'creation_trend' => $trend,
            'recent_tasks' => $recent,
        ];
    }
}
