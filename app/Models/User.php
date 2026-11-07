<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const ROLES = ['user', 'moderator', 'admin'];

    public const STATUSES = ['active', 'inactive', 'suspended', 'banned'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'username',
        'password',
        'phone',
        'profile_photo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'password' => 'hashed',
        'moderator_permissions' => 'array',
    ];

    public function activity(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserActivityLog::class)->latest('created_at');
    }

    public function statusChangedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'status_changed_by')->withTrashed();
    }

    public function recordedActivities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserActivityLog::class, 'actor_id');
    }

    public function hasManagementPermission(string $permission): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        return $this->role === 'moderator'
            && in_array($permission, $this->moderator_permissions ?? [], true)
            && array_key_exists($permission, config('user_management.moderator_permissions', []));
    }

    public function getInitialsAttribute(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: 'U';
    }
}
