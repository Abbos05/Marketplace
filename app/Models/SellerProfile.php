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
        'restore_requested_at',
        'pending_shop_name',
        'pending_description',
        'pending_description_change',
        'shop_changes_requested_at',
    ];

    protected $casts = [
        'working_hours' => 'array',
        'rating' => 'decimal:2',
        'total_sales' => 'integer',
        'restore_requested_at' => 'datetime',
        'shop_changes_requested_at' => 'datetime',
        'pending_description_change' => 'boolean',
    ];

    public function isRestorePending(): bool
    {
        return $this->restore_requested_at !== null;
    }

    public function scopeRestorePending($query)
    {
        return $query->whereNotNull('restore_requested_at');
    }

    public function isShopChangesPending(): bool
    {
        return $this->shop_changes_requested_at !== null;
    }

    public function scopeShopChangesPending($query)
    {
        return $query->whereNotNull('shop_changes_requested_at');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id', 'user_id');
    }
}