<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Categories\IndexUserCategoryRequest;
use App\Http\Resources\Api\V1\UserCategoryResource;
use App\Services\Api\V1\Categories\UserCategoryCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserCategoryController extends Controller
{
    public function index(IndexUserCategoryRequest $request, UserCategoryCatalogService $categories): JsonResponse
    {
        $paginator = $categories->paginate((int) $request->user()->id, $request->validated());

        return response()->json([
            'data' => UserCategoryResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, int $category, UserCategoryCatalogService $categories): JsonResponse
    {
        return response()->json([
            'category' => UserCategoryResource::make($categories->findFor($category, (int) $request->user()->id))->resolve($request),
        ]);
    }
}
