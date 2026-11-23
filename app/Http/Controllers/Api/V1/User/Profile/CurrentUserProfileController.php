<?php

namespace App\Http\Controllers\Api\V1\User\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\Profile\ChangeCurrentPasswordRequest;
use App\Http\Requests\Api\V1\User\Profile\UpdateCurrentAvatarRequest;
use App\Http\Requests\Api\V1\User\Profile\UpdateCurrentProfileRequest;
use App\Http\Resources\Api\V1\Shared\Users\UserResource;
use App\Services\Api\V1\User\Profile\CurrentUserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserProfileController extends Controller
{
    public function update(UpdateCurrentProfileRequest $request, CurrentUserProfileService $profile): JsonResponse
    {
        $user = $profile->update($request->user(), $request->validated());

        return response()->json([
            'message' => 'Your profile was updated.',
            'user' => UserResource::make($user)->resolve($request),
        ]);
    }

    public function updateAvatar(UpdateCurrentAvatarRequest $request, CurrentUserProfileService $profile): JsonResponse
    {
        $user = $profile->updateAvatar($request->user(), $request->file('avatar'));

        return response()->json([
            'message' => 'Your profile photo was updated.',
            'user' => UserResource::make($user)->resolve($request),
        ]);
    }

    public function removeAvatar(Request $request, CurrentUserProfileService $profile): JsonResponse
    {
        $user = $profile->removeAvatar($request->user());

        return response()->json([
            'message' => 'Your profile photo was removed.',
            'user' => UserResource::make($user)->resolve($request),
        ]);
    }

    public function changePassword(ChangeCurrentPasswordRequest $request, CurrentUserProfileService $profile): JsonResponse
    {
        $user = $profile->changePassword($request->user(), $request->validated('password'));
        if ($request->hasSession()) {
            $request->session()->put('password_hash_web', $user->getAuthPassword());
            $request->session()->put('auth_version_web', (int) ($user->auth_version ?? 1));
        }

        return response()->json([
            'message' => 'Your password was changed. Other sessions have been signed out.',
            'user' => UserResource::make($user)->resolve($request),
        ]);
    }
}
