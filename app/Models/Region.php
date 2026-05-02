<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'regions';

    protected $fillable = ['name', 'delivery_hours'];

    public function productVariants()
    {
        return $this->hasMany(ProductVariant::class, 'region_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'region_id');
    }
}