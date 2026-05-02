<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class SellerProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'seller_profiles';

    protected $fillable = [
        'user_id', 'shop_name', 'description', 'inn', 'legal_address',
        'pickup_address', 'rating', 'total_sales', 'working_hours',
    ];

    protected $casts = [
        'working_hours' => 'array',
        'rating' => 'decimal:2',
        'total_sales' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}