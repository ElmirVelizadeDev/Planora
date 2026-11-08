<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'expires_at',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
