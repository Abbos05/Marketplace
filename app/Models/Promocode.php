<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promocode extends Model
{
    use HasFactory;

    protected $table = 'promocodes';

    protected $fillable = [
        'seller_id', 'code', 'discount_type', 'discount_value', 'min_order_amount',
        'usage_limit', 'usage_per_user', 'starts_at', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_per_user' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function usages()
    {
        return $this->hasMany(PromocodeUsage::class, 'promocode_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->starts_at  && now()->lt($this->starts_at))  return false;
        if ($this->expires_at && now()->gt($this->expires_at)) return false;
        return true;
    }
}