<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickupPoint extends Model
{
    protected $fillable = [
        'title',
        'address',
        'region_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Текст для сохранения в заказе (снимок). */
    public function snapshotAddress(): string
    {
        return trim($this->title.', '.$this->address);
    }
}
