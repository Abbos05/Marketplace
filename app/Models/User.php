<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes; // <--- Добавьте этот импорт
use Laravel\Sanctum\HasApiTokens;
/**
 * @method void decrement(string $column, float|int $amount = 1)
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'phone',
        'password',
        'name',
        'last_name',
        'role',
        'avatar',
        'is_active',
        'is_blocked',
        'newPassw',
        'default_pickup_point_id',
        'daily_pickup_code',
        'daily_pickup_code_date',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];


    /**
     * Get the expiration time for the remember token.
     *
     * @return \DateTimeInterface|null
     */


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_blocked' => 'boolean',
            'newPassw' => 'boolean',
            'daily_pickup_code_date' => 'date',
        ];
    }
    public function getRememberTokenName()
    {
        return 'remember_token';
    }

   public function sellerProfile()
    {
        return $this->hasOne(SellerProfile::class, 'user_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function favorites()
    {
        return $this->belongsToMany(Product::class, 'favorites', 'user_id', 'product_id')
                    ->withTimestamps();
    }

    public function cartItems()
    {
        return $this->hasMany(Cart::class, 'user_id');
    }

    // Заказы, где пользователь – покупатель
    public function ordersAsBuyer()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    // Позиции заказов, где пользователь – продавец
    public function orderItemsAsSeller()
    {
        return $this->hasMany(OrderItem::class, 'seller_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'user_id');
    }

    public function conversationsAsBuyer()
    {
        return $this->hasMany(Conversation::class, 'buyer_id');
    }

    public function conversationsAsSeller()
    {
        return $this->hasMany(Conversation::class, 'seller_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function promocodeUsages()
    {
        return $this->hasMany(PromocodeUsage::class, 'user_id');
    }

    public function transactionsAsBuyer()
    {
        return $this->hasMany(Transaction::class, 'buyer_id');
    }

    public function transactionsAsSeller()
    {
        return $this->hasMany(Transaction::class, 'seller_id');
    }

    // Хелперы ролей
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return $this->role === 'moderator';
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isModerator();
    }

    public function canAssignStaffRoles(): bool
    {
        return $this->isAdmin();
    }

    public function isStaffRole(string $role): bool
    {
        return in_array($role, ['admin', 'moderator'], true);
    }

    public function isSeller()
    {
        return $this->role === 'seller';
    }

    public function isUser()
    {
        return $this->role === 'user';
    }

    public function defaultPickupPoint()
    {
        return $this->belongsTo(PickupPoint::class, 'default_pickup_point_id');
    }

    /** Суточный код выдачи — один на все заказы пользователя, обновляется раз в сутки. */
    public function ensureDailyPickupCode(): string
    {
        $today = now()->toDateString();

        if (
            $this->daily_pickup_code
            && $this->daily_pickup_code_date
            && $this->daily_pickup_code_date->toDateString() === $today
        ) {
            return $this->daily_pickup_code;
        }

        do {
            $code = sprintf('%04d %04d', random_int(1000, 9999), random_int(1000, 9999));
        } while (
            static::query()
                ->where('daily_pickup_code', $code)
                ->whereDate('daily_pickup_code_date', $today)
                ->whereKeyNot($this->id)
                ->exists()
        );

        $this->forceFill([
            'daily_pickup_code' => $code,
            'daily_pickup_code_date' => $today,
        ])->save();

        return $code;
    }
}
