<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'is_sensitive', 'updated_by'];

    protected $hidden = ['value'];

    protected $casts = ['is_sensitive' => 'boolean'];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
