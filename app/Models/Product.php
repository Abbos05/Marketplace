<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'seller_id', 'category_id', 'title', 'description', 'short_description',
        'min_price', 'views_count', 'sales_count', 'status',
        'moderation_comment', 'is_on_action',
    ];

    protected $casts = [
        'min_price' => 'decimal:2',
        'views_count' => 'integer',
        'sales_count' => 'integer',
        'is_on_action' => 'boolean',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function carts()
    {
        return $this->hasMany(Cart::class, 'product_id');
    }
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    public function attributeValues()
    {
        return $this->hasMany(ProductAttributeValue::class, 'product_id');
    }

    public function favorites()
    {
        return $this->belongsToMany(User::class, 'favorites', 'product_id', 'user_id')
                    ->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'product_id');
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'product_id');
    }

    /**
     * Eager-loads relations and aggregates used on product cards (главная, категория, продавец, избранное).
     */
    public function scopeForCatalogPresentation(Builder $query): Builder
    {
        return $query->with([
            'seller.sellerProfile',
            'category',
            'images' => fn ($q) => $q->whereNull('variant_id')->orderByDesc('is_main')->orderBy('sort_order'),
        ])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('is_moderated', true)])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($q) => $q->where('is_moderated', true)], 'rating');
    }

    public static function normalizeListingUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return str_starts_with($url, '/') ? $url : '/'.ltrim($url, '/');
    }

    /**
     * Добавляет к коллекции товаров поля для карточки: картинка, цена варианта, старая цена, остаток, название магазина.
     */
    public static function enrichForCatalog(Collection $products): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $ids = $products->pluck('id')->all();

        $stockByProduct = ProductVariant::query()
            ->whereIn('product_id', $ids)
            ->where('is_active', true)
            ->selectRaw('product_id, SUM(stock) as stock_total')
            ->groupBy('product_id')
            ->pluck('stock_total', 'product_id');

        $cheapestByProduct = ProductVariant::query()
            ->whereIn('product_id', $ids)
            ->where('is_active', true)
            ->orderBy('product_id')
            ->orderBy('price')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');

        foreach ($products as $product) {
            $variant = $cheapestByProduct->get($product->id);
            $price = (float) ($variant?->price ?? $product->min_price ?? 0);
            $product->setAttribute('price', $price);

            $old = $variant?->old_price !== null ? (float) $variant->old_price : null;
            if ($old !== null && $old > $price) {
                $product->setAttribute('old_price', $old);
                $product->setAttribute('discount_percent', (int) round((($old - $price) / $old) * 100));
            } else {
                $product->setAttribute('old_price', null);
                $product->setAttribute('discount_percent', null);
            }

            $product->setAttribute('stock_total', (int) ($stockByProduct[$product->id] ?? 0));

            $mainImage = $product->relationLoaded('images')
                ? ($product->images->firstWhere('is_main', true) ?? $product->images->first())
                : null;
            $url = $mainImage?->url;
            if ($url !== null && $url !== '') {
                if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://') && ! str_starts_with($url, '/')) {
                    $url = '/'.ltrim($url, '/');
                }
            } else {
                $url = null;
            }
            $product->setAttribute('image', $url);

            $seller = $product->seller ?? $product->user;
            $shopName = $seller?->sellerProfile?->shop_name ?? $seller?->name ?? 'Магазин';
            $product->setAttribute('seller_shop_name', $shopName);
            $product->setAttribute('seller_verified', (bool) $seller?->sellerProfile);
        }

        $variantsByProduct = ProductVariant::query()
            ->whereIn('product_id', $ids)
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderByDesc('is_main')->orderBy('sort_order')])
            ->orderBy('product_id')
            ->orderBy('price')
            ->get()
            ->groupBy('product_id');

        foreach ($products as $product) {
            $fallbackImage = $product->getAttribute('image');
            $catalog = [];
            foreach ($variantsByProduct->get($product->id, collect()) as $v) {
                $vim = $v->images->firstWhere('is_main', true) ?? $v->images->first();
                $imgUrl = $vim?->url ? self::normalizeListingUrl($vim->url) : $fallbackImage;
                $price = (float) $v->price;
                $old = $v->old_price !== null ? (float) $v->old_price : null;
                $showOld = $old !== null && $old > $price;
                $catalog[] = [
                    'id' => $v->id,
                    'label' => $v->displayLabel(),
                    'price' => $price,
                    'old_price' => $showOld ? $old : null,
                    'discount_percent' => $showOld ? (int) round((($old - $price) / $old) * 100) : null,
                    'stock' => (int) $v->stock,
                    'image' => $imgUrl,
                ];
            }
            $product->setAttribute('variants_catalog', $catalog);
        }
    }

    // Актуальная цена (минимальная среди активных вариантов) можно вычислить через accessor
    public function getActualPriceAttribute()
    {
        return $this->variants()->where('is_active', true)->min('price') ?? $this->min_price;
    }
}

