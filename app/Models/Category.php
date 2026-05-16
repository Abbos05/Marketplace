<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'categories';

    protected $fillable = ['name', 'parent_id', 'slug', 'icon', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function commissionRate()
    {
        return $this->hasOne(CommissionRate::class, 'category_id');
    }
    public function attributes()
    {
        return $this->hasMany(CategoryAttribute::class);
    }

    /**
     * Корневые категории, в которых или в подкатегориях есть витринные товары.
     *
     * @return Collection<int, Category>
     */
    public static function rootsForCatalogNav(): Collection
    {
        $hasListed = fn (Builder $productQuery) => $productQuery->where('is_on_action', 1);

        return self::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->where(function (Builder $q) use ($hasListed) {
                $q->whereHas('products', $hasListed)
                    ->orWhereHas('children', fn (Builder $c) => $c->whereHas('products', $hasListed));
            })
            ->orderBy('name')
            ->get();
    }

}