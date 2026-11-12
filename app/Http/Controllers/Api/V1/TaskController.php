<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Tasks\IndexTaskRequest;
use App\Http\Requests\Api\V1\Tasks\StoreTaskRequest;
use App\Http\Requests\Api\V1\Tasks\UpdateTaskRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Services\Api\V1\Tasks\TaskService;
use Illuminate\Http\JsonResponse;

class TaskController extends Controller
{
    public function index(IndexTaskRequest $request, TaskService $tasks): JsonResponse
    {
        $paginator = $tasks->paginate($request->validated());

        return response()->json([
            'data' => TaskResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'statistics' => $tasks->statistics(),
            ],
        ]);
    }

    public function assignees(): JsonResponse
    {
        $users = User::query()->where('role', 'user')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email', 'profile_photo']);

        return response()->json(['data' => $users->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url,
        ])]);
    }

    public function show(Task $task): JsonResponse
    {
        $task->load(['owner', 'assignedUser', 'creator', 'category']);

        return response()->json(['task' => TaskResource::make($task)->resolve(request())]);
    }

    public function store(StoreTaskRequest $request, TaskService $tasks): JsonResponse
    {
        $task = $tasks->create($request->validated(), (int) $request->user()->id);

        return response()->json(['task' => TaskResource::make($task)->resolve($request)], 201);
    }

    public function update(UpdateTaskRequest $request, Task $task, TaskService $tasks): JsonResponse
    {
        $task = $tasks->update($task, $request->validated());

        return response()->json(['task' => TaskResource::make($task)->resolve($request)]);
    }

    public function destroy(Task $task, TaskService $tasks): JsonResponse
    {
        $tasks->delete($task);

        return response()->json(['message' => 'Task deleted.']);
    }
}
