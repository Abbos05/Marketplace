<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;

class UserRestrictionService
{
    public function sellerProfileComplete(User $user): bool
    {
        $profile = $user->sellerProfile;
        if (! $profile) {
            return false;
        }

        return filled($profile->shop_name) && filled($profile->inn);
    }

    public function pvzOperatorReady(User $user): bool
    {
        if (! $user->isProfileVerified()) {
            return false;
        }

        return $user->approvedPickupPointStaff()->exists();
    }

    /** @return array{ok: bool, message: string} */
    public function canAssignRole(User $target, string $role): array
    {
        if (in_array($role, ['admin', 'moderator'], true)) {
            return ['ok' => true, 'message' => ''];
        }

        if ($role === 'user') {
            return ['ok' => true, 'message' => ''];
        }

        if ($role === 'seller') {
            if (! $this->sellerProfileComplete($target)) {
                return [
                    'ok' => false,
                    'message' => 'Нельзя назначить продавцом: у пользователя нет заполненной компании (магазин и ИНН в разделе «Мои компании»).',
                ];
            }

            return ['ok' => true, 'message' => ''];
        }

        if ($role === 'pvz') {
            if (! $this->pvzOperatorReady($target)) {
                return [
                    'ok' => false,
                    'message' => 'Нельзя назначить оператором ПВЗ: нужны верифицированный профиль (имя, email, телефон) и одобренная заявка на пункт выдачи.',
                ];
            }

            return ['ok' => true, 'message' => ''];
        }

        return ['ok' => false, 'message' => 'Неизвестная роль.'];
    }

    public function applyBlock(User $user): void
    {
        if ($user->role === 'pvz') {
            $user->update(['role' => 'user']);
        }

        if ($user->sellerProfile) {
            Product::query()
                ->where('seller_id', $user->id)
                ->update(['is_on_action' => false]);
        }
    }

    public function roleFlagsFor(User $user): array
    {
        return [
            'can_assign_seller' => $this->sellerProfileComplete($user),
            'can_assign_pvz' => $this->pvzOperatorReady($user),
        ];
    }

    public function assignableRolesFor(User $actor, User $target): array
    {
        $all = $actor->canAssignStaffRoles()
            ? ['user', 'seller', 'pvz', 'moderator', 'admin']
            : ['user', 'seller'];

        $allowed = array_values(array_filter($all, function (string $role) use ($target) {
            return $this->canAssignRole($target, $role)['ok'];
        }));

        if (! in_array($target->role, $allowed, true) && in_array($target->role, $all, true)) {
            $allowed[] = $target->role;
        }

        return $allowed;
    }
}
