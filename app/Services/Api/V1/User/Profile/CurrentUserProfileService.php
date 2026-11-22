<?php

namespace App\Services\Api\V1\User\Profile;

use App\Models\User;
use App\Services\Api\V1\Shared\UserActivityLogger;
use App\Services\Api\V1\Shared\UserAvatarStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class CurrentUserProfileService
{
    public function __construct(
        private readonly UserActivityLogger $activityLogger,
        private readonly UserAvatarStorage $avatars,
    ) {
    }

    public function update(User $account, array $attributes): User
    {
        return DB::transaction(function () use ($account, $attributes): User {
            $user = User::query()->lockForUpdate()->findOrFail($account->id);
            $fields = [];

            if (array_key_exists('first_name', $attributes)) {
                $user->first_name = $attributes['first_name'];
                $fields[] = 'first_name';
            }
            if (array_key_exists('last_name', $attributes)) {
                $user->last_name = $attributes['last_name'];
                $fields[] = 'last_name';
            }
            if (array_key_exists('phone', $attributes)) {
                $user->phone = $attributes['phone'];
                $fields[] = 'phone';
            }
            if (array_intersect($fields, ['first_name', 'last_name'])) {
                $legacyName = preg_split('/\s+/', trim($user->name), 2) ?: [];
                if (! array_key_exists('first_name', $attributes) && ! $user->first_name) {
                    $user->first_name = $legacyName[0] ?? null;
                }
                if (! array_key_exists('last_name', $attributes) && ! $user->last_name) {
                    $user->last_name = $legacyName[1] ?? null;
                }
                $user->name = trim(implode(' ', array_filter([
                    $user->first_name,
                    $user->last_name,
                ], fn ($part) => is_string($part) && trim($part) !== '')));
                $fields[] = 'name';
            }

            $changed = array_values(array_diff(array_keys($user->getDirty()), ['updated_at']));
            if ($changed !== []) {
                $user->save();
                $this->activityLogger->record(
                    'user.profile_updated',
                    'Account owner updated personal information.',
                    $user,
                    $user,
                    ['fields' => $changed],
                );
            }

            return $user->fresh();
        });
    }

    public function updateAvatar(User $account, UploadedFile $avatar): User
    {
        $storedPath = null;

        try {
            $result = DB::transaction(function () use ($account, $avatar, &$storedPath): array {
                $user = User::query()->lockForUpdate()->findOrFail($account->id);
                $oldPath = $user->profile_photo;
                $storedPath = $this->avatars->store($avatar);
                $user->profile_photo = $storedPath;
                $user->save();
                $this->activityLogger->record(
                    'user.avatar_changed',
                    'Account owner updated their profile photo.',
                    $user,
                    $user,
                    ['avatar_replaced' => $oldPath !== null],
                );

                return [$user->fresh(), $oldPath];
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                $this->avatars->deleteIfManaged($storedPath);
            }
            throw $exception;
        }

        [$updated, $oldPath] = $result;
        if ($oldPath !== $updated->profile_photo) {
            $this->avatars->deleteIfManaged($oldPath);
        }

        return $updated;
    }

    public function removeAvatar(User $account): User
    {
        $result = DB::transaction(function () use ($account): array {
            $user = User::query()->lockForUpdate()->findOrFail($account->id);
            $oldPath = $user->profile_photo;
            if ($oldPath === null) {
                return [$user, null];
            }

            $user->profile_photo = null;
            $user->save();
            $this->activityLogger->record(
                'user.avatar_removed',
                'Account owner removed their profile photo.',
                $user,
                $user,
                ['avatar_removed' => true],
            );

            return [$user->fresh(), $oldPath];
        });

        [$updated, $oldPath] = $result;
        $this->avatars->deleteIfManaged($oldPath);

        return $updated;
    }

    public function changePassword(User $account, string $password): User
    {
        return DB::transaction(function () use ($account, $password): User {
            $user = User::query()->lockForUpdate()->findOrFail($account->id);
            $user->password = Hash::make($password);
            $user->setRememberToken(Str::random(60));
            $user->save();
            $user->tokens()->delete();
            $this->activityLogger->record(
                'auth.password_changed',
                'Account owner changed their password.',
                $user,
                $user,
            );

            return $user->fresh();
        });
    }
}
