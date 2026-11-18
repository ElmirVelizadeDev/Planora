<?php

namespace App\Http\Resources\Api\V1\Admin\Backups;

use Illuminate\Http\Resources\Json\JsonResource;

class BackupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this['id'],
            'filename' => $this['filename'] ?? ('planora-'.$this['type'].'-'.str_replace([':', '+'], ['-', ''], (string) $this['created_at']).'.zip.enc'),
            'type' => $this['type'],
            'status' => $this['status'],
            'created_at' => $this['created_at'],
            'completed_at' => $this['completed_at'],
            'size_bytes' => (int) $this['size_bytes'],
            'storage_location' => $this['storage_label'],
            'verified' => (bool) $this['verified'],
            'failure_code' => $this['failure_code'],
            'components' => $this['components'],
        ];
    }
}
