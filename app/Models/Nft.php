<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nft extends Model
{
    //
    use HasFactory;
    protected $fillable = [
        'title',
        'description',
        'image',
        'price',
        'previous_price',
        'tags',
        'status',
        'user_id',
        'category_id'
    ];
    protected $appends = ['percentage'];

    public function getPercentageAttribute()
    {
        if ($this->previous_price === null || $this->previous_price == 0) {
            return 0;
        }
        return round(($this->price - $this->previous_price) / $this->previous_price * 100, 2);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function carts()
    {
        return $this->hasMany(Cart::class, 'nft_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
public function favoritedBy()
{
    return $this->belongsToMany(User::class, 'favorites', 'nft_id', 'user_id');
}
}
