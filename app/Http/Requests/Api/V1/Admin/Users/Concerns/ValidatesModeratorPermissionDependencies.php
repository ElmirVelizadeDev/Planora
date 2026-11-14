<?php

namespace App\Http\Requests\Api\V1\Admin\Users\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesModeratorPermissionDependencies
{
    protected function validateModeratorPermissionDependencies(Validator $validator, string $field): void
    {
        $permissions = $this->input($field, []);
        if (! is_array($permissions)) {
            return;
        }

        foreach (config('user_management.moderator_permissions', []) as $permission => $definition) {
            if (! in_array($permission, $permissions, true)) {
                continue;
            }

            foreach ($definition['requires'] ?? [] as $required) {
                if (! in_array($required, $permissions, true)) {
                    $validator->errors()->add($field, "The {$permission} permission requires {$required}.");
                }
            }
        }
    }
}
