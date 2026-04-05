<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    use HasFactory;

    /**
     * Массив заполняемых полей
     * @var array
     */
    protected $fillable = [
        'user_id',
        'nft_id'
    ];
        /**
     * Отношение к пользователю
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Отношение к NFT
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function nft()
    {
        return $this->belongsTo(Nft::class);
    }
}
