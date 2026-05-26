<?php

namespace App\Services;

use App\Models\PickupPointStaff;
use App\Models\User;
use App\Notifications\MarketplaceAlert;
use App\Support\NotificationCategory;

class PvzNotificationService
{
    public function notifyAdmins(string $title, string $body, ?string $actionUrl = null): void
    {
        User::query()
            ->whereIn('role', ['admin', 'moderator'])
            ->each(fn (User $admin) => $admin->notify(new MarketplaceAlert($title, $body, $actionUrl, NotificationCategory::PvzAdmin)));
    }

    public function notifyApplicationSubmitted(PickupPointStaff $staff): void
    {
        $user = $staff->user;
        $name = trim(($user?->name ?? '').' '.($user?->last_name ?? '')) ?: 'Пользователь';
        $point = $staff->proposed_title ?? 'Новый пункт';

        $this->notifyAdmins(
            'Заявка на открытие ПВЗ',
            sprintf('%s подал заявку на пункт «%s». Проверьте данные в админке.', $name, $point),
            route('admin.dashboard', [], false).'?section=users&filter=pvz_pending',
        );
    }

    public function notifyApplicationApproved(User $operator, string $pointTitle): void
    {
        $operator->notify(new MarketplaceAlert(
            'Пункт выдачи открыт',
            sprintf('Заявка одобрена. Пункт «%s» доступен в панели ПВЗ.', $pointTitle),
            route('pvz.dashboard', [], false),
            NotificationCategory::PvzOperator,
        ));
    }

    public function notifyClosureRequested(User $operator, string $pointTitle): void
    {
        $name = trim($operator->name.' '.($operator->last_name ?? '')) ?: 'Оператор';

        $this->notifyAdmins(
            'Запрос на закрытие ПВЗ',
            sprintf('%s запросил закрытие пункта «%s». Подтвердите в разделе пунктов выдачи.', $name, $pointTitle),
            route('admin.pickup-points.index', [], false),
        );
    }

    public function notifyClosureApproved(User $operator): void
    {
        $operator->notify(new MarketplaceAlert(
            'Пункт выдачи закрыт',
            'Администратор подтвердил закрытие. Пункт снят с карты, новые заказы на него не принимаются.',
            route('pickup.partner', [], false),
            NotificationCategory::PvzOperator,
        ));
    }

    public function notifyClosureRejected(User $operator, string $reason): void
    {
        $operator->notify(new MarketplaceAlert(
            'Запрос на закрытие отклонён',
            sprintf(
                'Администратор отклонил закрытие пункта. Панель ПВЗ и выдача заказов по-прежнему доступны. Причина: %s. При необходимости подайте запрос на закрытие снова в настройках ПВЗ.',
                $reason,
            ),
            route('pvz.settings', [], false),
            NotificationCategory::PvzOperator,
        ));
    }
}
