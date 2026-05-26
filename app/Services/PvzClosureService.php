<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PickupPoint;

class PvzClosureService
{
    /** Статусы, при которых пункт нельзя закрыть до завершения. */
    public const BLOCKING_STATUSES = [
        Order::STATUS_NEW,
        Order::STATUS_INTRANSIT,
        Order::STATUS_DELIVERED,
    ];

    public function activeOrdersCount(PickupPoint $point): int
    {
        return Order::query()
            ->where('pickup_point_id', $point->id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->count();
    }

    public function canRequestClosure(PickupPoint $point): array
    {
        if ($point->closure_status === PickupPoint::CLOSURE_PENDING) {
            return ['ok' => false, 'message' => 'Запрос на закрытие уже отправлен. Ожидайте решения администратора.'];
        }

        if ($point->closure_status === PickupPoint::CLOSURE_CLOSED || ! $point->is_active) {
            return ['ok' => false, 'message' => 'Пункт уже закрыт.'];
        }

        $count = $this->activeOrdersCount($point);
        if ($count > 0) {
            return [
                'ok' => false,
                'message' => "Нельзя закрыть пункт: {$count} заказ(ов) ещё в пути или ожидают выдачи. Завершите их, затем повторите запрос.",
            ];
        }

        return ['ok' => true, 'message' => ''];
    }
}
