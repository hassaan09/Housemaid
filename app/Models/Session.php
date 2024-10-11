<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;

    protected $table = 'sessions'; 

    protected $fillable = [
        'user_id',
        'device_id',
        'token_id',
        'last_activity',
        'expires_at',
        'is_active',
    ];

    /* defines a many-to-one relationship with the User model using the belongsTo relationship type.
    This setup allows you to easily access the user associated with a session and supports the relational integrity between the two models */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
