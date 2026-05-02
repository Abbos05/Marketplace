<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promocode extends Model
{
    use HasFactory;

    protected $table = 'promocodes';

    protected $fillable = [
        'code', 'discount_type', 'discount_value', 'min_order_amount',
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
}