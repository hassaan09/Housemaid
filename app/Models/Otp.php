<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $table = 'otps'; 

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $fillable = [
        'otp',
        'email',
        'is_verified',
        'expires_at',
    ];
}
