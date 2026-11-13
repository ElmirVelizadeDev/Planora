<?php

namespace App\Http\Requests\Api\V1\Admin\Projects;

use App\Http\Requests\Api\V1\Admin\Tasks\StoreTaskRequest;

class StoreProjectTaskRequest extends StoreTaskRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['project_id' => ['prohibited']]);
    }
}
