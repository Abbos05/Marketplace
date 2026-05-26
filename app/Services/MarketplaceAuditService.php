<?php

namespace App\Services;

use App\Models\MarketplaceAuditEvent;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

class MarketplaceAuditService
{
    public function log(string $eventType, User $subject, ?User $actor = null, array $meta = []): MarketplaceAuditEvent
    {
        return MarketplaceAuditEvent::query()->create([
            'subject_user_id' => $subject->id,
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'meta' => $meta,
        ]);
    }

    public function logSellerCompanyClosed(User $seller, ?User $actor, array $snapshot): MarketplaceAuditEvent
    {
        return $this->log(MarketplaceAuditEvent::TYPE_SELLER_COMPANY_CLOSED, $seller, $actor, $snapshot);
    }

    public function logRoleChanged(User $subject, ?User $actor, string $from, string $to): MarketplaceAuditEvent
    {
        return $this->log(MarketplaceAuditEvent::TYPE_ROLE_CHANGED, $subject, $actor, [
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function eventsForUser(int $userId, int $limit = 30): array
    {
        return MarketplaceAuditEvent::query()
            ->with(['actor:id,name,email,role'])
            ->where('subject_user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (MarketplaceAuditEvent $e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'label' => $e->labelRu(),
                'meta' => $e->meta ?? [],
                'created_at' => $e->created_at?->toIso8601String(),
                'actor' => $e->actor ? [
                    'id' => $e->actor->id,
                    'name' => $e->actor->name,
                    'email' => $e->actor->email,
                    'role' => $e->actor->role,
                ] : null,
            ])
            ->all();
    }

    public function sellerHistorySummary(int $userId): array
    {
        $productsTotal = Product::query()->where('seller_id', $userId)->count();
        $productsOnCatalog = Product::query()
            ->where('seller_id', $userId)
            ->where('status', 'approved')
            ->where('is_on_action', true)
            ->count();
        $salesTotal = (int) OrderItem::query()->where('seller_id', $userId)->sum('quantity');

        return [
            'products_total' => $productsTotal,
            'products_on_catalog' => $productsOnCatalog,
            'products_off_catalog' => max(0, $productsTotal - $productsOnCatalog),
            'sales_units' => $salesTotal,
        ];
    }
}
