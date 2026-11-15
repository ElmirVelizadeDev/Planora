<?php

namespace App\Http\Requests\Api\V1\User\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrentAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=4096,max_height=4096', 'max:2048'],
            'email' => ['prohibited'],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
            'moderator_permissions' => ['prohibited'],
        ];
    }
}
