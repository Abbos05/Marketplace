<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PvzAccrual extends Model
{
    public const TYPE_ISSUED = 'issued';

    protected $fillable = [
        'order_id',
        'pickup_point_id',
        'user_id',
        'amount',
        'order_total',
        'type',
        'period',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'order_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function pickupPoint(): BelongsTo
    {
        return $this->belongsTo(PickupPoint::class, 'pickup_point_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
