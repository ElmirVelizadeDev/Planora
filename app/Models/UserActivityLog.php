<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id', 'user_id', 'actor_id', 'event', 'description', 'module', 'subject_type', 'subject_id',
        'subject_label', 'context', 'old_values', 'new_values', 'actor_name', 'actor_email', 'actor_role',
        'ip_address', 'user_agent', 'request_method', 'request_path', 'request_id', 'status', 'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
