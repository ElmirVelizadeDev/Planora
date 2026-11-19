<?php

namespace App\Services\Api\V1\Search;

use App\Models\Category;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GlobalSearchService
{
    public function search(User $actor, array $input): array
    {
        $query = trim((string) ($input['q'] ?? ''));
        $limit = (int) ($input['limit'] ?? 5);
        $groups = [];

        if (mb_strlen($query) < 2) {
            return ['groups' => [], 'total' => 0];
        }

        if ($actor->role === 'user') {
            $this->addGroup($groups, 'tasks', 'My tasks', $this->userTasks($actor, $query, $limit));
            $this->addGroup($groups, 'projects', 'My projects', $this->userProjects($actor, $query, $limit));
            $this->addGroup($groups, 'categories', 'Categories', $this->categories($query, $limit, 'categories.show'));
        } else {
            if ($actor->can('viewAny', User::class)) {
                $this->addGroup($groups, 'users', 'Users', $this->users($actor, $query, $limit));
            }
            if ($actor->can('viewAny', Task::class)) {
                $this->addGroup($groups, 'tasks', 'Tasks', $this->adminTasks($query, $limit));
            }
            if ($actor->can('viewAny', Project::class)) {
                $this->addGroup($groups, 'projects', 'Projects', $this->adminProjects($query, $limit));
            }
            if ($actor->can('viewAny', Category::class)) {
                $this->addGroup($groups, 'categories', 'Categories', $this->categories($query, $limit, 'admin.categories.show'));
            }
        }

        return ['groups' => $groups, 'total' => array_sum(array_map(fn (array $group) => count($group['results']), $groups))];
    }

    private function addGroup(array &$groups, string $type, string $label, array $results): void
    {
        if ($results !== []) {
            $groups[] = ['type' => $type, 'label' => $label, 'results' => $results];
        }
    }

    private function users(User $actor, string $term, int $limit): array
    {
        $query = User::query()->where(fn (Builder $builder) => $builder
            ->where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%"));

        if ($actor->role === 'moderator') {
            $query->where('role', '!=', 'admin');
        }

        return $query->orderBy('name')->orderBy('id')->limit($limit)->get(['id', 'name', 'email'])->map(fn (User $user) => [
            'id' => $user->id,
            'title' => $user->name,
            'subtitle' => $user->email,
            'type' => 'user',
            'to' => ['name' => 'admin.users.index', 'query' => ['view' => (string) $user->id]],
        ])->all();
    }

    private function adminTasks(string $term, int $limit): array
    {
        return Task::query()->where(fn (Builder $builder) => $builder
            ->where('title', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%"))
            ->orderByDesc('updated_at')->orderByDesc('id')->limit($limit)
            ->get(['id', 'title', 'status', 'priority'])->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'subtitle' => ucfirst(str_replace('_', ' ', $task->status->value)).' · '.ucfirst($task->priority->value),
                'type' => 'task',
                'to' => ['name' => 'admin.tasks.show', 'params' => ['taskId' => (string) $task->id]],
            ])->all();
    }

    private function userTasks(User $actor, string $term, int $limit): array
    {
        return Task::query()->where(fn (Builder $scope) => $scope->where('owner_id', $actor->id)->orWhere('assigned_user_id', $actor->id))
            ->where(fn (Builder $builder) => $builder->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"))
            ->orderByDesc('updated_at')->orderByDesc('id')->limit($limit)
            ->get(['id', 'title', 'status', 'priority'])->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'subtitle' => ucfirst(str_replace('_', ' ', $task->status->value)).' · '.ucfirst($task->priority->value),
                'type' => 'task',
                'to' => ['name' => 'tasks.show', 'params' => ['taskId' => (string) $task->id]],
            ])->all();
    }

    private function adminProjects(string $term, int $limit): array
    {
        return Project::query()->where(fn (Builder $builder) => $builder->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"))
            ->orderByDesc('updated_at')->orderByDesc('id')->limit($limit)->get(['id', 'name', 'status'])->map(fn (Project $project) => [
                'id' => $project->id, 'title' => $project->name, 'subtitle' => ucfirst(str_replace('_', ' ', $project->status)),
                'type' => 'project', 'to' => ['name' => 'admin.projects.show', 'params' => ['projectId' => (string) $project->id]],
            ])->all();
    }

    private function userProjects(User $actor, string $term, int $limit): array
    {
        return Project::query()->where('owner_id', $actor->id)->where(fn (Builder $builder) => $builder->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"))
            ->orderByDesc('updated_at')->orderByDesc('id')->limit($limit)->get(['id', 'name', 'status'])->map(fn (Project $project) => [
                'id' => $project->id, 'title' => $project->name, 'subtitle' => ucfirst(str_replace('_', ' ', $project->status)),
                'type' => 'project', 'to' => ['name' => 'projects.show', 'params' => ['projectId' => (string) $project->id]],
            ])->all();
    }

    private function categories(string $term, int $limit, string $route): array
    {
        return Category::query()->where(fn (Builder $builder) => $builder->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"))
            ->orderBy('name')->orderBy('id')->limit($limit)->get(['id', 'name', 'description'])->map(fn (Category $category) => [
                'id' => $category->id, 'title' => $category->name, 'subtitle' => $category->description,
                'type' => 'category', 'to' => ['name' => $route, 'params' => ['categoryId' => (string) $category->id]],
            ])->all();
    }
}
