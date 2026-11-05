<?php

namespace App\Services\Api\V1\Auth;

use App\Models\User;

class RegistrationService
{
    public function register(array $attributes): User
    {
        return User::create($attributes);
    }
}
