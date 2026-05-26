<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\MarketplaceAlert;
use App\Support\NotificationCategory;

class OrderNotificationService
{
    public function notifyCreated(Order $order): void
    {
        $this->send($order, 'created');
    }

    public function notifyPaid(Order $order): void
    {
        $this->send($order, 'paid');
    }

    public function notifyRefunded(Order $order): void
    {
        $this->send($order, 'refunded');
    }

    public function notifyStatusChange(Order $order, string $newStatus): void
    {
        $event = match ($newStatus) {
            Order::STATUS_INTRANSIT => 'in_transit',
            Order::STATUS_DELIVERED => 'delivered',
            Order::STATUS_ISSUED => 'issued',
            Order::STATUS_CANCELED => 'canceled',
            Order::STATUS_REFUSED => 'refused',
            default => null,
        };

        if ($event !== null) {
            $this->send($order, $event);
        }
    }

    protected function send(Order $order, string $event): void
    {
        $order->loadMissing('buyer');
        if (! $order->buyer) {
            return;
        }

        [$title, $body, $actionUrl, $category] = $this->messageFor($order, $event);

        $order->buyer->notify(new MarketplaceAlert($title, $body, $actionUrl, $category));
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    protected function messageFor(Order $order, string $event): array
    {
        $number = $order->number ?? (string) $order->id;
        $orderUrl = route('order.show', $order, false);
        $refundUrl = route('order.refund.checkout', $order, false);

        return match ($event) {
            'created' => [
                'Заказ оформлен',
                sprintf('Заказ №%s создан. Оплатите его в личном кабинете, когда будете готовы.', $number),
                $orderUrl,
                NotificationCategory::OrderCreated,
            ],
            'paid' => [
                'Заказ оплачен',
                sprintf('Оплата заказа №%s получена. Продавец подготовит отправку в пункт выдачи.', $number),
                $orderUrl,
                NotificationCategory::OrderPaid,
            ],
            'in_transit' => [
                'Заказ в пути',
                sprintf('Заказ №%s передан в доставку и едет в пункт выдачи.', $number),
                $orderUrl,
                NotificationCategory::OrderInTransit,
            ],
            'delivered' => [
                'Заказ в пункте выдачи',
                sprintf('Заказ №%s прибыл в пункт выдачи. Можно забирать по коду из карточки заказа.', $number),
                $orderUrl,
                NotificationCategory::OrderDelivered,
            ],
            'issued' => [
                'Заказ выдан',
                sprintf('Заказ №%s выдан. Спасибо за покупку!', $number),
                $orderUrl,
                NotificationCategory::OrderIssued,
            ],
            'canceled' => [
                'Заказ отменён',
                sprintf('Заказ №%s отменён.', $number),
                $order->payment_status === 'paid' ? $refundUrl : $orderUrl,
                NotificationCategory::OrderCanceled,
            ],
            'refused' => [
                'Отказ от получения',
                sprintf('По заказу №%s оформлен отказ от получения в пункте выдачи.', $number),
                $order->payment_status === 'paid' ? $refundUrl : $orderUrl,
                NotificationCategory::OrderRefused,
            ],
            'refunded' => [
                'Возврат оформлен',
                sprintf('По заказу №%s оформлен возврат средств на карту.', $number),
                route('order.show', ['order' => $order, 'view' => 1], false),
                NotificationCategory::OrderRefunded,
            ],
            default => [
                'Заказ обновлён',
                sprintf('Заказ №%s — изменение статуса.', $number),
                $orderUrl,
                NotificationCategory::General,
            ],
        };
    }
}
