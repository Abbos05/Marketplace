<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reviews';

    protected $fillable = [
        'product_id', 'variant_id', 'user_id', 'order_id',
        'rating', 'comment', 'is_moderated', 'likes_count', 'dislikes_count',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_moderated' => 'boolean',
        'likes_count' => 'integer',
        'dislikes_count' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function votes()
    {
        return $this->hasMany(ReviewVote::class);
    }
}