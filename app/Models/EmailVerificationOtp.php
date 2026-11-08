<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerificationOtp extends Model
{
    protected $fillable = ['email', 'otp_hash', 'otp_code', 'expires_at', 'attempts', 'last_sent_at'];

    protected $hidden = ['otp_hash', 'otp_code'];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];
}
