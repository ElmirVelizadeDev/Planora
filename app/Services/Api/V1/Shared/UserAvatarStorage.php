<?php

namespace App\Services\Api\V1\Shared;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UserAvatarStorage
{
    public function store(UploadedFile $avatar): string
    {
        $path = $avatar->storePublicly('user-avatars', 'public');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The avatar could not be stored.');
        }

        return $path;
    }

    public function delete(string $path): void
    {
        Storage::disk('public')->delete($path);
    }

    public function deleteIfManaged(?string $path): void
    {
        if ($path && str_starts_with($path, 'user-avatars/')) {
            $this->delete($path);
        }
    }
}
