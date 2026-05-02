<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class SellerReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'seller_reviews';

    protected $fillable = [
        'seller_id', 'user_id', 'order_id', 'rating', 'comment', 'is_moderated',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_moderated' => 'boolean',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}