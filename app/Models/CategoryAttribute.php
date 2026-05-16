<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryAttribute extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'type',
        'options',
        'required',
        'applies_to',
    ];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}