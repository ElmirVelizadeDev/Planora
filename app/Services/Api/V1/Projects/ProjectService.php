<?php

namespace App\Services\Api\V1\Projects;

use App\Models\Project;
use App\Models\User;
use App\Services\Api\V1\Admin\UserActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function __construct(private readonly UserActivityLogger $activityLogger)
    {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Project::query()->with(['owner:id,name,email,profile_photo', 'category'])->withCount('tasks');
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")));
        }
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['category_id'])) $query->where('category_id', $filters['category_id']);
        return $query->latest('created_at')->latest('id')->paginate($filters['per_page'] ?? 10)->withQueryString();
    }

    public function statistics(): array
    {
        $counts = Project::query()->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
        return ['total' => Project::query()->count(), 'active' => (int) ($counts['active'] ?? 0), 'completed' => (int) ($counts['completed'] ?? 0), 'on_hold' => (int) ($counts['on_hold'] ?? 0)];
    }

    public function create(array $data, User $actor): Project
    {
        return DB::transaction(function () use ($data, $actor): Project {
            $project = Project::query()->create($data);
            $this->activityLogger->recordChange(
                'project.created', 'Project created.', $project, $actor, [],
                $this->auditValues($project, ['name', 'status', 'start_date', 'due_date', 'owner_id', 'category_id']),
                ['description_changed' => filled($project->description)],
            );

            return $project->load(['owner:id,name,email,profile_photo', 'category'])->loadCount('tasks');
        });
    }

    public function update(Project $project, array $data, User $actor): Project
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $before = $this->auditValues($project, ['name', 'status', 'start_date', 'due_date', 'owner_id', 'category_id']);
            $project->fill($data);
            $changed = array_values(array_diff(array_keys($project->getDirty()), ['updated_at']));
            if ($changed !== []) {
                $project->save();
                $safeFields = array_values(array_intersect($changed, ['name', 'status', 'start_date', 'due_date', 'owner_id', 'category_id']));
                $event = in_array('status', $safeFields, true) ? 'project.status_changed' : 'project.updated';
                if ($safeFields !== []) {
                    $after = $this->auditValues($project, $safeFields);
                    $this->activityLogger->recordChange(
                        $event, $event === 'project.status_changed' ? 'Project status changed.' : 'Project updated.',
                        $project, $actor, array_intersect_key($before, array_flip($safeFields)), $after,
                        ['changed_fields' => $changed],
                    );
                } else {
                    $this->activityLogger->record('project.updated', 'Project updated.', $project, $actor, ['changed_fields' => $changed]);
                }
            }

            return $project->refresh()->load(['owner:id,name,email,profile_photo', 'category'])->loadCount('tasks');
        });
    }

    public function delete(Project $project, User $actor): void
    {
        DB::transaction(function () use ($project, $actor): void {
            $this->activityLogger->record('project.deleted', 'Project deleted.', $project, $actor);
            $project->delete();
        });
    }

    private function auditValues(Project $project, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $project->getAttributes()[$field] ?? $project->getRawOriginal($field);
        }

        return $values;
    }
}
