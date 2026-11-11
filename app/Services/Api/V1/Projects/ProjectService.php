<?php

namespace App\Services\Api\V1\Projects;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Project::query()->with('owner:id,name,email,profile_photo')->withCount('tasks');
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")));
        }
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        return $query->latest('created_at')->latest('id')->paginate($filters['per_page'] ?? 10)->withQueryString();
    }

    public function statistics(): array
    {
        $counts = Project::query()->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
        return ['total' => Project::query()->count(), 'active' => (int) ($counts['active'] ?? 0), 'completed' => (int) ($counts['completed'] ?? 0), 'on_hold' => (int) ($counts['on_hold'] ?? 0)];
    }

    public function create(array $data): Project { return Project::query()->create($data)->load('owner:id,name,email,profile_photo')->loadCount('tasks'); }

    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $project->fill($data)->save();
            return $project->refresh()->load('owner:id,name,email,profile_photo')->loadCount('tasks');
        });
    }

    public function delete(Project $project): void
    {
        DB::transaction(fn () => $project->delete());
    }
}
