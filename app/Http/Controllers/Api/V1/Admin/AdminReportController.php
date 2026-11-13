<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminReportFilterOptionsRequest;
use App\Http\Requests\Api\V1\Admin\AdminReportsRequest;
use App\Services\Api\V1\Admin\AdminReportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function overview(AdminReportsRequest $request, AdminReportService $reports): JsonResponse
    {
        return response()->json(['data' => $reports->build($request->validated())]);
    }

    public function filterOptions(AdminReportFilterOptionsRequest $request, AdminReportService $reports): JsonResponse
    {
        return response()->json(['data' => $reports->filterOptions(
            $request->validated('type'),
            $request->validated('search')
        )]);
    }

    public function table(AdminReportsRequest $request, AdminReportService $reports, string $table): JsonResponse
    {
        return response()->json(['data' => $reports->tableData($table, $request->validated())]);
    }

    public function export(AdminReportsRequest $request, AdminReportService $reports): StreamedResponse
    {
        $filters = $request->validated();
        $report = $reports->build($filters);
        $filters['from'] = $report['period']['from'];
        $filters['to'] = $report['period']['to'];
        $rows = $reports->exportRows($report, $filters);
        $filename = 'planora-admin-reports-'.$report['period']['from'].'-to-'.$report['period']['to'].'.csv';
        $header = ['Section', 'Record ID', 'Name', 'Role', 'Status', 'Priority', 'Category', 'Project', 'Due date', 'Created at', 'Metric', 'Value', 'Period'];

        return response()->streamDownload(function () use ($rows, $header): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $header, ',', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($stream, array_map([$this, 'safeCsvCell'], $row), ',', '"', '\\');
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsvCell(mixed $value): string|int|float|null
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[\p{Z}\x00-\x20]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
