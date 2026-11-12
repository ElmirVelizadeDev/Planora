<?php

namespace App\Services\Api\V1\Projects;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserProjectService
{
    public function paginateFor(int $userId, array $filters): LengthAwarePaginator
    {
        $query = Project::query()->where('owner_id', $userId)->with(['owner:id,name,email,profile_photo', 'category']);
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return $query->latest('created_at')->latest('id')->paginate($filters['per_page'] ?? 10)->withQueryString();
    }

    public function findFor(int $userId, int $projectId): Project
    {
        return Project::query()->where('owner_id', $userId)->with(['owner:id,name,email,profile_photo', 'category'])->findOrFail($projectId);
    }
}
