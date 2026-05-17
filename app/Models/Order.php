<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Статусы заказа = только доставка / исход заказа (без «оплачен» — это payment_status).
     * NEW → INTRANSIT → DELIVERED (в ПВЗ) → ISSUED (выдан покупателю);
     * CANCELED — отмена до выдачи; REFUSED — отказ в пункте выдачи.
     */
    public const STATUS_NEW = 'NEW';

    public const STATUS_INTRANSIT = 'INTRANSIT';

    public const STATUS_DELIVERED = 'DELIVERED';

    public const STATUS_ISSUED = 'ISSUED';

    public const STATUS_CANCELED = 'CANCELED';

    public const STATUS_REFUSED = 'REFUSED';

    /**
     * @return list<string>
     */
    public static function allStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_INTRANSIT,
            self::STATUS_DELIVERED,
            self::STATUS_ISSUED,
            self::STATUS_CANCELED,
            self::STATUS_REFUSED,
        ];
    }

    /** Статусы доставки / выдачи — только после оплаты. */
    public static function deliveryStatuses(): array
    {
        return [
            self::STATUS_INTRANSIT,
            self::STATUS_DELIVERED,
            self::STATUS_ISSUED,
            self::STATUS_REFUSED,
        ];
    }

    /** Для неоплаченного заказа админ может менять доставку, но не может завершить выдачу. */
    public static function statusesAllowedWhenUnpaid(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_INTRANSIT,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELED,
            self::STATUS_REFUSED,
        ];
    }

    /** Статусы, при которых аккаунт покупателя уже можно удалить. */
    public static function statusesAllowingUserDeletion(): array
    {
        return [
            self::STATUS_ISSUED,
            self::STATUS_CANCELED,
            self::STATUS_REFUSED,
        ];
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function canSetDeliveryStatus(string $status): bool
    {
        if ($status === self::STATUS_ISSUED) {
            return $this->isPaid();
        }

        if ($this->isPaid()) {
            return in_array($status, self::allStatuses(), true);
        }

        return in_array($status, self::statusesAllowedWhenUnpaid(), true);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'order_id');
    }

    protected $table = 'orders';

    protected $fillable = [
        'number',
        'order_code',
        'buyer_id',
        'pickup_point_id',
        'status',
        'total',
        'discount',
        'delivery_address',
        'payment_status',
        'region_id',
        'delivery_method',
        'payment_method',
        'comment',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function pickupPoint()
    {
        return $this->belongsTo(PickupPoint::class, 'pickup_point_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'order_id');
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class, 'order_id');
    }

    public function promocodeUsage()
    {
        return $this->hasOne(PromocodeUsage::class, 'order_id');
    }
    public function getSellerProductsAttribute()
    {
        return $this->items->flatMap(function ($item) {
            return $item->variant->product ?? null;
        })->filter();
    }
}
