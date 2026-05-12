<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id', 'variant_id', 'seller_id', 'quantity',
        'price_at_purchase', 'commission_percent',
    ];

    protected $casts = [
        'price_at_purchase' => 'decimal:2',
        'commission_percent' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
    public function review()
{
    return $this->hasOne(\App\Models\Review::class, 'variant_id', 'variant_id')
        ->where('user_id', auth()->id());
}
// В модели OrderItem добавь
public function getProductAttribute()
{
    return $this->variant?->product;
}
}