<?php

namespace App\Http\Controllers\Api\V1\User\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\Reports\UserTaskReportRequest;
use App\Http\Resources\Api\V1\User\Tasks\UserTaskResource;
use App\Services\Api\V1\Tasks\UserTaskReportService;
use Illuminate\Http\JsonResponse;

class UserTaskReportController extends Controller
{
    public function __invoke(UserTaskReportRequest $request, UserTaskReportService $reports): JsonResponse
    {
        $data = $reports->generate($request->user()->id, $request->validated());
        $data['recent_tasks'] = UserTaskResource::collection($data['recent_tasks'])->resolve($request);

        return response()->json(['data' => $data]);
    }
}
