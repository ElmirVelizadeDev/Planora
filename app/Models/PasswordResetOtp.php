<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    protected $fillable = [
        'user_id', 'email', 'otp_hash', 'expires_at', 'attempts', 'verified_at',
        'recovery_token_hash', 'recovery_expires_at', 'last_sent_at',
    ];

    protected $hidden = ['otp_hash', 'recovery_token_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'recovery_expires_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];
}
