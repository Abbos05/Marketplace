<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cart extends Model
{
    use HasFactory;

    protected $table = 'cart_items';

    protected $fillable = ['user_id', 'variant_id', 'quantity'];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // Сумма по позиции
    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->variant->current_price;
    }
}