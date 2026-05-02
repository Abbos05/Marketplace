<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromocodeUsage extends Model
{
    use HasFactory;

    protected $table = 'promocode_usages';

    protected $fillable = ['promocode_id', 'user_id', 'order_id', 'discount_applied'];

    protected $casts = [
        'discount_applied' => 'decimal:2',
    ];

    public function promocode()
    {
        return $this->belongsTo(Promocode::class, 'promocode_id');
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