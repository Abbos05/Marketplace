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
        'min_price', 'sales_count', 'status',
        'moderation_comment', 'is_on_action',
    ];
    protected $casts = [
        'min_price' => 'decimal:2',
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

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'promotion_product')
            ->withTimestamps();
    }

    /** Товар виден в каталоге, поиске и на главной. */
    public function scopeVisibleInCatalog(Builder $query): Builder
    {
        return $query
            ->where('status', 'approved')
            ->where('is_on_action', true)
            ->whereHas('seller', function (Builder $q) {
                $q->whereNull('deleted_at')
                    ->where(function (Builder $inner) {
                        $inner->where('is_blocked', false)->orWhereNull('is_blocked');
                    })
                    ->whereHas('sellerProfile');
            });
    }

    /** Подзапрос: сумма просмотров всех вариантов товара. */
    public static function variantViewsSumSubquery(): Builder
    {
        return ProductVariant::query()
            ->selectRaw('COALESCE(SUM(views_count), 0)')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->whereNull('product_variants.deleted_at');
    }

    /** Сортировка для ленты и рекомендаций: просмотры → продажи → отзывы. */
    public function scopeOrderByCatalogPopularity(Builder $query): Builder
    {
        return $query
            ->orderByDesc(static::variantViewsSumSubquery())
            ->orderByDesc('products.sales_count')
            ->orderByDesc('reviews_count')
            ->orderByDesc('products.created_at');
    }

    public function sellerCanPublish(): bool
    {
        $seller = $this->relationLoaded('seller')
            ? $this->seller
            : $this->seller()->with('sellerProfile')->first();

        return $seller && $seller->hasActiveSellerCompany();
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === 'approved'
            && (bool) $this->is_on_action
            && $this->sellerCanPublish();
    }

    public function canBeViewedBy(?User $user): bool
    {
        if ($this->isPubliclyVisible()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ((int) $this->seller_id === (int) $user->id) {
            return true;
        }

        return $user->isStaff();
    }

    /** Причина блокировки витрины: moderation, hidden, rejected и т.д. */
    public function storefrontBlockReason(): ?string
    {
        if ($this->isPubliclyVisible()) {
            return null;
        }

        if (! $this->sellerCanPublish()) {
            return 'seller_inactive';
        }

        if ($this->status === 'approved' && ! $this->is_on_action) {
            return 'hidden';
        }

        return $this->status;
    }

    public function storefrontBlockMessage(): string
    {
        return match ($this->storefrontBlockReason()) {
            'moderation' => 'Товар на модерации и пока недоступен для покупки.',
            'hidden' => 'Товар скрыт с витрины и недоступен для покупки.',
            'seller_inactive' => 'Компания продавца закрыта — товар нельзя вернуть на витрину, пока продавец не восстановит компанию.',
            'rejected' => 'Товар отклонён модерацией и недоступен для покупки.',
            'archived' => 'Товар снят с продажи.',
            'draft' => 'Товар ещё не опубликован.',
            default => 'Товар недоступен для покупки.',
        };
    }

    public function isPurchasable(): bool
    {
        return $this->isPubliclyVisible();
    }

    /**
     * URL для карточки витрины: главное фото активного варианта.
     */
    public function resolveListingImageUrl(): ?string
    {
        $variant = $this->variants()
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderByDesc('is_main')->orderBy('sort_order')])
            ->orderBy('price')
            ->first();

        $variantUrl = $variant?->images->firstWhere('is_main', true)?->url
            ?? $variant?->images->first()?->url;

        return $variantUrl ? self::normalizeListingUrl($variantUrl) : null;
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (! $product->isDirty('status')) {
                return;
            }

            if ($product->status === 'approved') {
                $product->is_on_action = true;
            } elseif (in_array($product->status, ['hidden', 'rejected', 'archived', 'draft', 'moderation'], true)) {
                $product->is_on_action = false;
            }
        });
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
    public static function enrichForCatalog(Collection $products, array $variantFilters = []): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $ids = $products->pluck('id')->all();

        $priceFrom = $variantFilters['price_from'] ?? null;
        $priceTo = $variantFilters['price_to'] ?? null;

        $applyVariantPrice = static function (Builder $query) use ($priceFrom, $priceTo): Builder {
            if ($priceFrom !== null && $priceFrom !== '') {
                $query->where('price', '>=', (float) $priceFrom);
            }
            if ($priceTo !== null && $priceTo !== '') {
                $query->where('price', '<=', (float) $priceTo);
            }

            return $query;
        };

        $stockByProduct = ProductVariant::query()
            ->whereIn('product_id', $ids)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->selectRaw('product_id, SUM(stock) as stock_total')
            ->groupBy('product_id')
            ->pluck('stock_total', 'product_id');

        $cheapestByProductQuery = ProductVariant::query()
            ->whereIn('product_id', $ids)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->with(['images' => fn ($q) => $q->orderByDesc('is_main')->orderBy('sort_order')])
            ->orderBy('product_id')
            ->orderBy('price');

        $cheapestByProduct = $applyVariantPrice($cheapestByProductQuery)
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

            $variantUrl = $variant?->images->first()?->url;
            $url = self::normalizeListingUrl($variantUrl);
            $product->setAttribute('image', $url);

            $seller = $product->seller ?? $product->user;
            $shopName = $seller?->sellerProfile?->shop_name ?? $seller?->name ?? 'Магазин';
            $product->setAttribute('seller_shop_name', $shopName);
            $product->setAttribute('seller_verified', (bool) $seller?->sellerProfile);
        }

        $variantsByProductQuery = ProductVariant::query()
            ->whereIn('product_id', $ids)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->with(['images' => fn ($q) => $q->orderByDesc('is_main')->orderBy('sort_order')])
            ->orderBy('product_id')
            ->orderBy('price');

        $variantsByProduct = $applyVariantPrice($variantsByProductQuery)
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

        $badges = app(\App\Services\PromotionCatalogService::class)->badgesForProducts($products);
        foreach ($products as $product) {
            $product->setAttribute('promotion_badges', $badges[$product->id] ?? []);
        }
    }

    // Актуальная цена (минимальная среди активных вариантов) можно вычислить через accessor
    public function getActualPriceAttribute()
    {
        return $this->variants()->where('is_active', true)->min('price') ?? $this->min_price;
    }
}

