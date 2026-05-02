<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_images';

    protected $fillable = ['product_id', 'variant_id', 'url', 'sort_order', 'is_main'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_main' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}