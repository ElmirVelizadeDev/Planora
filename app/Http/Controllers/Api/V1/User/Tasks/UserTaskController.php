<?php

namespace App\Http\Controllers\Api\V1\User\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\Tasks\IndexUserTaskRequest;
use App\Http\Requests\Api\V1\User\Tasks\StoreUserTaskRequest;
use App\Http\Requests\Api\V1\User\Tasks\UpdateUserTaskRequest;
use App\Http\Requests\Api\V1\User\Tasks\UpdateUserTaskStatusRequest;
use App\Http\Resources\Api\V1\User\Tasks\UserTaskResource;
use App\Models\Task;
use App\Services\Api\V1\Tasks\UserTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserTaskController extends Controller
{
    public function index(IndexUserTaskRequest $request, UserTaskService $tasks): JsonResponse
    {
        $user = $request->user();
        $paginator = $tasks->paginate($user->id, $request->validated());

        return response()->json([
            'data' => UserTaskResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'statistics' => $tasks->statistics($user->id),
            ],
        ]);
    }

    public function show(Request $request, int $task): JsonResponse
    {
        $record = $this->accessibleTask($request, $task);

        return response()->json(['task' => UserTaskResource::make($record)->resolve($request)]);
    }

    public function store(StoreUserTaskRequest $request, UserTaskService $tasks): JsonResponse
    {
        $task = $tasks->create($request->validated(), $request->user());

        return response()->json(['task' => UserTaskResource::make($task)->resolve($request)], 201);
    }

    public function update(UpdateUserTaskRequest $request, int $task, UserTaskService $tasks): JsonResponse
    {
        $record = Task::query()->where('owner_id', $request->user()->id)->findOrFail($task);
        $record = $tasks->update($record, $request->validated(), $request->user());

        return response()->json(['task' => UserTaskResource::make($record)->resolve($request)]);
    }

    public function updateStatus(UpdateUserTaskStatusRequest $request, int $task, UserTaskService $tasks): JsonResponse
    {
        $record = $this->accessibleTask($request, $task);
        $record = $tasks->updateStatus($record, $request->validated('status'), $request->user());

        return response()->json(['task' => UserTaskResource::make($record)->resolve($request)]);
    }

    public function destroy(Request $request, int $task, UserTaskService $tasks): JsonResponse
    {
        $record = Task::query()->where('owner_id', $request->user()->id)->findOrFail($task);
        $tasks->delete($record, $request->user());

        return response()->json(['message' => 'Task deleted.']);
    }

    private function accessibleTask(Request $request, int $task): Task
    {
        return Task::query()->with(['owner', 'assignedUser', 'category'])
            ->where(fn ($query) => $query->where('owner_id', $request->user()->id)
                ->orWhere('assigned_user_id', $request->user()->id))
            ->findOrFail($task);
    }
}
