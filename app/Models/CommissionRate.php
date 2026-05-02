<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionRate extends Model
{
    use HasFactory;

    protected $table = 'commission_rates';

    protected $fillable = ['category_id', 'percent', 'fixed_amount'];

    protected $casts = [
        'percent' => 'decimal:2',
        'fixed_amount' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}