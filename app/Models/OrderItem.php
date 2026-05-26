<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id', 'variant_id', 'seller_id', 'quantity',
        'price_at_purchase', 'commission_percent', 'commission_fixed_amount',
        'commission_amount', 'seller_payout_amount', 'commission_status',
        'commission_finalized_at',
    ];

    protected $casts = [
        'price_at_purchase' => 'decimal:2',
        'commission_percent' => 'decimal:2',
        'commission_fixed_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'seller_payout_amount' => 'decimal:2',
        'commission_finalized_at' => 'datetime',
        'quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** SQL-выражение чистой выплаты продавцу по позиции. */
    public static function payoutAmountSql(string $table = 'order_items'): string
    {
        return "CASE
            WHEN {$table}.seller_payout_amount > 0 THEN {$table}.seller_payout_amount
            ELSE ({$table}.price_at_purchase * {$table}.quantity) - {$table}.commission_amount
        END";
    }

    /** Позиции с реальной выплатой: заказ выдан, оплачен, комиссия финализирована. */
    public function scopeRealizedRevenue(Builder $query): Builder
    {
        return $query->where('commission_status', 'finalized')
            ->whereHas('order', fn (Builder $q) => $q
                ->whereNull('deleted_at')
                ->where('status', Order::STATUS_ISSUED)
                ->where('payment_status', 'paid'));
    }

    /** Оплаченные заказы в доставке / в ПВЗ — ожидают выдачи. */
    public function scopePendingRevenue(Builder $query): Builder
    {
        return $query->where('commission_status', 'pending')
            ->whereHas('order', fn (Builder $q) => $q
                ->whereNull('deleted_at')
                ->whereIn('status', [
                    Order::STATUS_NEW,
                    Order::STATUS_INTRANSIT,
                    Order::STATUS_DELIVERED,
                ])
                ->where('payment_status', 'paid'));
    }
    public function review()
    {
        return $this->hasOne(Review::class, 'variant_id', 'variant_id')
            ->whereColumn('reviews.order_id', 'order_items.order_id')
            ->where('reviews.user_id', auth()->id());
    }
// В модели OrderItem добавь
public function getProductAttribute()
{
    return $this->variant?->product;
}
}