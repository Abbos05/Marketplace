<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'number',
        'order_code',
        'buyer_id',
        'status',
        'total',
        'discount',
        'delivery_address',
        'payment_status',
        'region_id',
        'delivery_method',
        'payment_method',
        'comment',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'order_id');
    }

    public function sellerReviews()
    {
        return $this->hasMany(SellerReview::class, 'order_id');
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class, 'order_id');
    }

    public function promocodeUsage()
    {
        return $this->hasOne(PromocodeUsage::class, 'order_id');
    }
    public function getSellerProductsAttribute()
    {
        return $this->items->flatMap(function ($item) {
            return $item->variant->product ?? null;
        })->filter();
    }
}