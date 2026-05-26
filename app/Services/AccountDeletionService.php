<?php

namespace App\Services;

use App\Models\MarketplaceAuditEvent;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\PickupPointStaff;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountDeletionService
{
    public function hasActivePvzOperatorBinding(User $user): bool
    {
        return PickupPointStaff::query()
            ->where('user_id', $user->id)
            ->where('status', PickupPointStaff::STATUS_APPROVED)
            ->whereHas('pickupPoint', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('closure_status', '!=', PickupPoint::CLOSURE_CLOSED)
                        ->orWhere('is_active', true);
                });
            })
            ->exists();
    }

    /** @return array{can_delete: bool, blockers: list<array{code: string, message: string, action_label?: string, action_url?: string}>} */
    public function accountDeletionInfo(User $user): array
    {
        $blockers = [];

        if ($user->isStaff()) {
            $blockers[] = [
                'code' => 'staff',
                'message' => 'Аккаунты администратора и модератора нельзя удалить через профиль.',
            ];
        }

        if ($user->sellerProfile) {
            $blockers[] = [
                'code' => 'seller_company',
                'message' => 'Сначала удалите компанию продавца в настройках продавца. ',
                'action_label' => ' Настройки продавца',
                'action_url' => route('seller.settings', absolute: false),
            ];
        }

        if ($this->hasActivePvzOperatorBinding($user)) {
            $blockers[] = [
                'code' => 'pvz',
                'message' => 'Сначала закройте пункт выдачи: отправьте запрос в панели ПВЗ и дождитесь подтверждения администратора.',
                'action_label' => 'Настройки ПВЗ',
                'action_url' => route('pvz.settings', absolute: false),
            ];
        }

        return [
            'can_delete' => $blockers === [],
            'blockers' => $blockers,
        ];
    }

    /** @return array{ok: bool, message: string} */
    public function canDeleteOwnAccount(User $user): array
    {
        $info = $this->accountDeletionInfo($user);
        if (! $info['can_delete']) {
            return [
                'ok' => false,
                'message' => $info['blockers'][0]['message'] ?? 'Нельзя удалить аккаунт.',
            ];
        }

        $hasActiveOrders = Order::where('buyer_id', $user->id)
            ->whereNotIn('status', Order::statusesAllowingUserDeletion())
            ->exists();

        if ($hasActiveOrders) {
            return [
                'ok' => false,
                'message' => 'Нельзя удалить аккаунт: есть активные заказы. Дождитесь доставки, выдачи или отмены.',
            ];
        }

        return ['ok' => true, 'message' => ''];
    }

    public function closeSellerCompany(User $user, ?User $actor = null): void
    {
        $profile = $user->sellerProfile;
        if (! $profile) {
            return;
        }

        DB::transaction(function () use ($user, $actor, $profile) {
            $productsHidden = Product::query()
                ->where('seller_id', $user->id)
                ->where('is_on_action', true)
                ->count();

            Product::query()
                ->where('seller_id', $user->id)
                ->update(['is_on_action' => false]);

            $previousRole = $user->role;

            app(MarketplaceAuditService::class)->logSellerCompanyClosed($user, $actor ?? $user, [
                'shop_name' => $profile->shop_name,
                'inn' => $profile->inn,
                'products_hidden' => $productsHidden,
                'products_total' => Product::query()->where('seller_id', $user->id)->count(),
                'previous_role' => $previousRole,
                'initiated_by' => ($actor && $actor->id !== $user->id) ? 'admin' : 'self',
            ]);

            $profile->delete();

            if ($user->role === 'seller') {
                $user->update(['role' => 'user']);
            }
        });
    }

    public function closedSellerProfileFor(int $userId): ?SellerProfile
    {
        return SellerProfile::withTrashed()
            ->where('user_id', $userId)
            ->orderByDesc('deleted_at')
            ->first();
    }

    public function requestSellerCompanyRestore(User $user): void
    {
        if ($user->hasSellerRestorePending()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => 'Заявка на восстановление уже отправлена. Ожидайте решения администратора.',
            ]);
        }

        if ($user->hasActiveSellerCompany()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => 'Компания уже активна.',
            ]);
        }

        $profile = $this->closedSellerProfileFor($user->id);
        if (! $profile) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => 'Нет закрытой компании для восстановления.',
            ]);
        }

        if ($user->isPvz()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => 'Нельзя восстановить компанию при активной роли оператора ПВЗ.',
            ]);
        }

        DB::transaction(function () use ($user, $profile) {
            if ($profile->trashed()) {
                $profile->restore();
            }

            $profile->update(['restore_requested_at' => now()]);

            if ($user->role === 'seller') {
                $user->update(['role' => 'user']);
            }

            app(MarketplaceAuditService::class)->log(
                MarketplaceAuditEvent::TYPE_SELLER_COMPANY_RESTORE_REQUESTED,
                $user,
                $user,
                [
                    'shop_name' => $profile->shop_name,
                    'inn' => $profile->inn,
                    'products_total' => Product::query()->where('seller_id', $user->id)->count(),
                ],
            );
        });
    }

    public function approveSellerCompanyRestore(User $user, User $actor): void
    {
        $profile = $user->sellerProfile;
        if (! $profile || ! $profile->isRestorePending()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => 'Нет заявки на восстановление компании.',
            ]);
        }

        DB::transaction(function () use ($user, $profile, $actor) {
            $profile->update(['restore_requested_at' => null]);
            $user->update(['role' => 'seller']);

            app(MarketplaceAuditService::class)->log(
                MarketplaceAuditEvent::TYPE_SELLER_COMPANY_RESTORED,
                $user,
                $actor,
                [
                    'shop_name' => $profile->shop_name,
                    'inn' => $profile->inn,
                    'products_total' => Product::query()->where('seller_id', $user->id)->count(),
                ],
            );
        });
    }

    public function rejectSellerCompanyRestore(User $user, User $actor): void
    {
        $profile = $user->sellerProfile;
        if (! $profile || ! $profile->isRestorePending()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => 'Нет заявки на восстановление компании.',
            ]);
        }

        DB::transaction(function () use ($user, $profile, $actor) {
            $profile->update(['restore_requested_at' => null]);
            $profile->delete();

            app(MarketplaceAuditService::class)->log(
                MarketplaceAuditEvent::TYPE_SELLER_COMPANY_RESTORE_REJECTED,
                $user,
                $actor,
                [
                    'shop_name' => $profile->shop_name,
                    'inn' => $profile->inn,
                ],
            );
        });
    }

    /** @return array<string, mixed>|null */
    public function closedSellerProfilePayload(int $userId): ?array
    {
        if (User::find($userId)?->hasSellerRestorePending()) {
            return null;
        }

        $profile = $this->closedSellerProfileFor($userId);

        if (! $profile || ! $profile->trashed()) {
            return null;
        }

        return [
            'shop_name' => $profile->shop_name,
            'inn' => $profile->inn,
            'legal_address' => $profile->legal_address,
            'pickup_address' => $profile->pickup_address,
            'description' => $profile->description,
            'closed_at' => $profile->deleted_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function sellerRestorePendingPayload(User $user): ?array
    {
        $profile = $user->sellerProfile;
        if (! $profile || ! $profile->isRestorePending()) {
            return null;
        }

        return [
            'shop_name' => $profile->shop_name,
            'inn' => $profile->inn,
            'requested_at' => $profile->restore_requested_at?->toIso8601String(),
        ];
    }
}
