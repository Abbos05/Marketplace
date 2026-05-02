<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'seller_id', 'category_id', 'title', 'description', 'short_description',
        'min_price', 'views_count', 'sales_count', 'status',
        'moderation_comment', 'is_on_action',
    ];

    protected $casts = [
        'min_price' => 'decimal:2',
        'views_count' => 'integer',
        'sales_count' => 'integer',
        'is_on_action' => 'boolean',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function carts()
    {
        return $this->hasMany(Cart::class, 'product_id');
    }
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    public function favorites()
    {
        return $this->belongsToMany(User::class, 'favorites', 'product_id', 'user_id')
                    ->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'product_id');
    }

    // Актуальная цена (минимальная среди активных вариантов) можно вычислить через accessor
    public function getActualPriceAttribute()
    {
        return $this->variants()->where('is_active', true)->min('price') ?? $this->min_price;
    }
}

