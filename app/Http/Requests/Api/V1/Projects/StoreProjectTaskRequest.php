<?php

namespace App\Http\Requests\Api\V1\Projects;

use App\Http\Requests\Api\V1\Tasks\StoreTaskRequest;

class StoreProjectTaskRequest extends StoreTaskRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['project_id' => ['prohibited']]);
    }
}
