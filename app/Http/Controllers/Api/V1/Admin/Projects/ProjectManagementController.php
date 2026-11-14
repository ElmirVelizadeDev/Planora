<?php

namespace App\Http\Controllers\Api\V1\Admin\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Projects\IndexProjectRequest;
use App\Http\Requests\Api\V1\Admin\Projects\StoreProjectRequest;
use App\Http\Requests\Api\V1\Admin\Projects\UpdateProjectRequest;
use App\Http\Requests\Api\V1\Admin\Projects\UpdateProjectStatusRequest;
use App\Http\Resources\Api\V1\Shared\Projects\ProjectResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Api\V1\Projects\ProjectService;
use Illuminate\Http\JsonResponse;

class ProjectManagementController extends Controller
{
    public function index(IndexProjectRequest $request, ProjectService $projects): JsonResponse
    {
        $paginator = $projects->paginate($request->validated());
        return response()->json([
            'data' => ProjectResource::collection($paginator->getCollection())->resolve($request),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'statistics' => $projects->statistics()],
        ]);
    }

    public function owners(): JsonResponse
    {
        $users = User::query()->where('role', 'user')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']);
        return response()->json(['data' => $users]);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $project->load(['owner:id,name,email,profile_photo', 'category'])->loadCount('tasks');

        return response()->json(['project' => ProjectResource::make($project)->resolve(request())]);
    }

    public function store(StoreProjectRequest $request, ProjectService $projects): JsonResponse
    {
        return response()->json(['project' => ProjectResource::make($projects->create($request->validated(), $request->user()))->resolve($request)], 201);
    }

    public function update(UpdateProjectRequest $request, Project $project, ProjectService $projects): JsonResponse
    {
        return response()->json(['project' => ProjectResource::make($projects->update($project, $request->validated(), $request->user()))->resolve($request)]);
    }

    public function updateStatus(UpdateProjectStatusRequest $request, Project $project, ProjectService $projects): JsonResponse
    {
        $updated = $projects->update($project, $request->validated(), $request->user());

        return response()->json(['project' => ProjectResource::make($updated)->resolve($request)]);
    }

    public function destroy(\Illuminate\Http\Request $request, Project $project, ProjectService $projects): JsonResponse
    {
        $projects->delete($project, $request->user());
        return response()->json(['message' => 'Project deleted.']);
    }
}
