<?php

namespace App\Services\Api\V1\Auth;

use App\Models\User;
use App\Services\Api\V1\Admin\UserActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegistrationService
{
    public function __construct(private readonly UserActivityLogger $activityLogger)
    {
    }

    public function register(array $attributes): User
    {
        return DB::transaction(function () use ($attributes) {
            $user = new User();
            $user->name = $attributes['name'];
            $user->email = $attributes['email'];
            $user->password = Hash::make($attributes['password']);
            $user->role = 'user';
            $user->status = 'active';
            $user->save();
            $this->activityLogger->record('user.registered', 'User account registered.', $user, $user);

            return $user;
        });
    }
}
