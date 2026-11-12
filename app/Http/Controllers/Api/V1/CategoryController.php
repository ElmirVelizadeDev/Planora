<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Categories\IndexCategoryRequest;
use App\Http\Requests\Api\V1\Categories\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Categories\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Services\Api\V1\Admin\CategoryManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(IndexCategoryRequest $request, CategoryManagementService $categories): JsonResponse
    {
        $this->authorize('viewAny', Category::class);
        $paginator = $categories->paginate($request->validated());

        return response()->json([
            'data' => CategoryResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'statistics' => $categories->statistics(),
            ],
        ]);
    }

    public function show(Request $request, int $category, CategoryManagementService $categories): JsonResponse
    {
        $model = $categories->findWithUsage($category);
        $this->authorize('view', $model);

        return response()->json(['category' => CategoryResource::make($model)->resolve($request)]);
    }

    public function store(StoreCategoryRequest $request, CategoryManagementService $categories): JsonResponse
    {
        $this->authorize('create', Category::class);
        $category = $categories->create($request->validated());

        return response()->json(['category' => CategoryResource::make($category)->resolve($request)], 201);
    }

    public function update(UpdateCategoryRequest $request, int $category, CategoryManagementService $categories): JsonResponse
    {
        $model = $categories->find($category);
        $this->authorize('update', $model);
        $updated = $categories->update($model, $request->validated());

        return response()->json(['category' => CategoryResource::make($updated)->resolve($request)]);
    }

    public function destroy(Request $request, int $category, CategoryManagementService $categories): JsonResponse
    {
        $model = $categories->find($category);
        $this->authorize('delete', $model);
        $categories->archive($model);

        return response()->json(['message' => 'Category archived.']);
    }

    public function restore(Request $request, int $category, CategoryManagementService $categories): JsonResponse
    {
        $model = $categories->find($category, withTrashed: true);
        $this->authorize('restore', $model);
        $restored = $categories->restore($model);

        return response()->json(['category' => CategoryResource::make($restored)->resolve($request)]);
    }
}
