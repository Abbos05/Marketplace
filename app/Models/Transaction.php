<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'nft_id',
        'order_id',
        'amount',
        'buyer_id',
        'seller_id',
        'status',
    ];


    public function nft()
    {
        return $this->belongsTo(Nft::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id')->withTrashed();
    }
    
}