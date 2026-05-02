<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_variants';

    protected $fillable = [
        'product_id', 'sku', 'options', 'price', 'old_price',
        'discount_percent', 'action_start', 'action_end', 'stock',
        'weight_grams', 'region_id', 'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'price' => 'decimal:2',
        'old_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'action_start' => 'datetime',
        'action_end' => 'datetime',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }

    public function cartItems()
    {
        return $this->hasMany(Cart::class, 'variant_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'variant_id');
    }

    // Актуальная цена с учётом скидки
    public function getCurrentPriceAttribute()
    {
        if ($this->action_start && $this->action_end && now()->between($this->action_start, $this->action_end)) {
            return round($this->price * (1 - $this->discount_percent / 100), 2);
        }
        if ($this->old_price && $this->old_price > $this->price) {
            return $this->price;
        }
        return $this->price;
    }
}