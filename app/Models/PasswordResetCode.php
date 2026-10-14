<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
    protected $fillable = ['user_id', 'code_hash', 'attempts', 'expires_at', 'last_sent_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
