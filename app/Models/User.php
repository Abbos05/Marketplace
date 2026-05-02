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
        'role',
        'avatar',
        'is_active',
        'newPassw',
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
            'newPassw' => 'boolean',
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

    public function sellerReviewsGiven()
    {
        return $this->hasMany(SellerReview::class, 'user_id');
    }

    public function sellerReviewsReceived()
    {
        return $this->hasMany(SellerReview::class, 'seller_id');
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
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isModerator()
    {
        return $this->role === 'moderator';
    }

    public function isSeller()
    {
        return $this->role === 'seller';
    }

    public function isUser()
    {
        return $this->role === 'user';
    }
}
