<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceAuditEvent extends Model
{
    public const TYPE_SELLER_COMPANY_CLOSED = 'seller_company_closed';

    public const TYPE_SELLER_COMPANY_RESTORE_REQUESTED = 'seller_company_restore_requested';

    public const TYPE_SELLER_COMPANY_RESTORED = 'seller_company_restored';

    public const TYPE_SELLER_COMPANY_RESTORE_REJECTED = 'seller_company_restore_rejected';

    public const TYPE_SELLER_SHOP_CHANGES_REQUESTED = 'seller_shop_changes_requested';

    public const TYPE_SELLER_SHOP_CHANGES_APPROVED = 'seller_shop_changes_approved';

    public const TYPE_SELLER_SHOP_CHANGES_REJECTED = 'seller_shop_changes_rejected';

    public const TYPE_ROLE_CHANGED = 'role_changed';

    protected $fillable = [
        'subject_user_id',
        'actor_user_id',
        'event_type',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function labelRu(): string
    {
        return match ($this->event_type) {
            self::TYPE_SELLER_COMPANY_CLOSED => 'Компания продавца закрыта',
            self::TYPE_SELLER_COMPANY_RESTORE_REQUESTED => 'Запрос на восстановление компании',
            self::TYPE_SELLER_COMPANY_RESTORED => 'Компания продавца восстановлена',
            self::TYPE_SELLER_COMPANY_RESTORE_REJECTED => 'Восстановление компании отклонено',
            self::TYPE_SELLER_SHOP_CHANGES_REQUESTED => 'Запрос на изменение магазина',
            self::TYPE_SELLER_SHOP_CHANGES_APPROVED => 'Изменения магазина одобрены',
            self::TYPE_SELLER_SHOP_CHANGES_REJECTED => 'Изменения магазина отклонены',
            self::TYPE_ROLE_CHANGED => 'Роль изменена',
            default => $this->event_type,
        };
    }
}
