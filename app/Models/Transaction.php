<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_REFUND = 'refund';

    public const TYPE_CANCEL = 'cancel';

    protected $table = 'transactions';

    protected $fillable = [
        'order_id',
        'buyer_id',
        'seller_id',
        'product_id',
        'amount',
        'type',
        'status',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_REFUND => 'Возврат средств',
            self::TYPE_CANCEL => 'Отмена заказа',
            default => 'Оплата',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'refunded' => 'Возвращено',
            'failed' => 'Не выполнено',
            'pending' => 'В обработке',
            default => 'Успешно',
        };
    }
}
