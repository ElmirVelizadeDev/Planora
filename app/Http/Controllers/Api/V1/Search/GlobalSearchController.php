<?php

namespace App\Http\Controllers\Api\V1\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Search\GlobalSearchRequest;
use App\Services\Api\V1\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;

class GlobalSearchController extends Controller
{
    public function __invoke(GlobalSearchRequest $request, GlobalSearchService $search): JsonResponse
    {
        return response()->json(['data' => $search->search($request->user(), $request->validated())]);
    }
}
