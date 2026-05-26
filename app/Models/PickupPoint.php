<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickupPoint extends Model
{
    public const CLOSURE_NONE = 'none';

    public const CLOSURE_PENDING = 'pending';

    public const CLOSURE_CLOSED = 'closed';

    protected $fillable = [
        'title',
        'address',
        'region_id',
        'is_active',
        'sort_order',
        'closure_status',
        'closure_requested_at',
        'closure_reason',
        'closure_admin_reject_reason',
        'closure_admin_rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'closure_requested_at' => 'datetime',
            'closure_admin_rejected_at' => 'datetime',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function usersWithDefault(): HasMany
    {
        return $this->hasMany(User::class, 'default_pickup_point_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'pickup_point_id');
    }

    public function approvedStaff()
    {
        return $this->hasOne(PickupPointStaff::class, 'pickup_point_id')
            ->where('status', PickupPointStaff::STATUS_APPROVED);
    }

    public function staffApplications(): HasMany
    {
        return $this->hasMany(PickupPointStaff::class, 'pickup_point_id');
    }

    public function scopeActive($query)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('closure_status', self::CLOSURE_NONE)
                    ->orWhereNull('closure_status');
            })
            ->whereHas('approvedStaff');
    }

    public function acceptsNewOrders(): bool
    {
        return $this->is_active
            && ($this->closure_status === self::CLOSURE_NONE || $this->closure_status === null)
            && $this->approvedStaff()->exists();
    }

    /** Текст для сохранения в заказе (снимок). */
    public function snapshotAddress(): string
    {
        return trim($this->title.', '.$this->address);
    }
}
