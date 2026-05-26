<?php

namespace App\Services;

use App\Models\MarketplaceAuditEvent;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SellerProfileModerationService
{
    public function requestShopChanges(SellerProfile $profile, string $shopName, ?string $description): array
    {
        $shopName = trim($shopName);
        $descriptionNorm = $this->normalizeDescription($description);
        $currentDesc = $this->normalizeDescription($profile->description);

        $nameChanged = $shopName !== trim((string) $profile->shop_name);
        $descChanged = $descriptionNorm !== $currentDesc;

        if (! $nameChanged && ! $descChanged) {
            return ['submitted' => false];
        }

        if ($profile->isShopChangesPending()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => 'Уже есть заявка на изменение названия или описания. Дождитесь решения администратора.',
            ]);
        }

        DB::transaction(function () use ($profile, $shopName, $descriptionNorm, $nameChanged, $descChanged) {
            $profile->update([
                'pending_shop_name' => $nameChanged ? $shopName : null,
                'pending_description' => $descChanged ? $descriptionNorm : null,
                'pending_description_change' => $descChanged,
                'shop_changes_requested_at' => now(),
            ]);

            app(MarketplaceAuditService::class)->log(
                MarketplaceAuditEvent::TYPE_SELLER_SHOP_CHANGES_REQUESTED,
                $profile->user,
                $profile->user,
                [
                    'current_shop_name' => $profile->shop_name,
                    'proposed_shop_name' => $nameChanged ? $shopName : null,
                    'current_description' => $profile->description,
                    'proposed_description' => $descChanged ? $descriptionNorm : null,
                ],
            );
        });

        return ['submitted' => true, 'name' => $nameChanged, 'description' => $descChanged];
    }

    public function approveShopChanges(User $user, User $actor): void
    {
        $profile = $user->sellerProfile;
        if (! $profile || ! $profile->isShopChangesPending()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => 'Нет заявки на изменение данных магазина.',
            ]);
        }

        DB::transaction(function () use ($profile, $user, $actor) {
            $updates = [
                'pending_shop_name' => null,
                'pending_description' => null,
                'pending_description_change' => false,
                'shop_changes_requested_at' => null,
            ];

            if ($profile->pending_shop_name !== null) {
                $updates['shop_name'] = $profile->pending_shop_name;
            }
            if ($profile->pending_description_change) {
                $updates['description'] = $profile->pending_description;
            }

            $oldName = $profile->shop_name;
            $oldDesc = $profile->description;

            $profile->update($updates);

            app(MarketplaceAuditService::class)->log(
                MarketplaceAuditEvent::TYPE_SELLER_SHOP_CHANGES_APPROVED,
                $user,
                $actor,
                [
                    'from_shop_name' => $oldName,
                    'to_shop_name' => $profile->shop_name,
                    'from_description' => $oldDesc,
                    'to_description' => $profile->description,
                ],
            );
        });
    }

    public function rejectShopChanges(User $user, User $actor): void
    {
        $profile = $user->sellerProfile;
        if (! $profile || ! $profile->isShopChangesPending()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => 'Нет заявки на изменение данных магазина.',
            ]);
        }

        DB::transaction(function () use ($profile, $user, $actor) {
            $meta = [
                'current_shop_name' => $profile->shop_name,
                'rejected_shop_name' => $profile->pending_shop_name,
                'rejected_description' => $profile->pending_description,
            ];

            $profile->update([
                'pending_shop_name' => null,
                'pending_description' => null,
                'pending_description_change' => false,
                'shop_changes_requested_at' => null,
            ]);

            app(MarketplaceAuditService::class)->log(
                MarketplaceAuditEvent::TYPE_SELLER_SHOP_CHANGES_REJECTED,
                $user,
                $actor,
                $meta,
            );
        });
    }

    /** @return array<string, mixed>|null */
    public function pendingPayload(?SellerProfile $profile): ?array
    {
        if (! $profile || ! $profile->isShopChangesPending()) {
            return null;
        }

        return [
            'current_shop_name' => $profile->shop_name,
            'current_description' => $profile->description,
            'proposed_shop_name' => $profile->pending_shop_name,
            'proposed_description' => $profile->pending_description,
            'changes_name' => $profile->pending_shop_name !== null,
            'changes_description' => (bool) $profile->pending_description_change,
            'requested_at' => $profile->shop_changes_requested_at?->toIso8601String(),
        ];
    }

    private function normalizeDescription(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
