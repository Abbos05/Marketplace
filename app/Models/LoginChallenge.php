<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoginChallenge extends Model
{
    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_NOTIFICATION = 'notification';

    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'phone',
        'code_hash',
        'reset_code_hash',
        'channel',
        'purpose',
        'reset_channel',
        'phone_verified_at',
        'reset_verified_at',
        'attempts',
        'reset_attempts',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'reset_verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'attempts' => 'integer',
            'reset_attempts' => 'integer',
        ];
    }

    public function hasActivePasswordReset(): bool
    {
        return $this->reset_code_hash !== null;
    }

    protected static function booted(): void
    {
        static::creating(function (LoginChallenge $challenge) {
            if (! $challenge->id) {
                $challenge->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function isResetVerified(): bool
    {
        return $this->reset_verified_at !== null;
    }
}
