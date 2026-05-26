<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Notifications\MarketplaceAlert;
use App\Support\NotificationCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PvzRefusalCodeService
{
    public function cacheKey(int $orderId, int $operatorId): string
    {
        return "pvz_refusal:{$orderId}:{$operatorId}";
    }

    public function sendVerificationCode(Order $order, User $operator, int $operatorPickupPointId): void
    {
        $pvzService = app(PvzOrderService::class);
        if (! $pvzService->canManage($order, $operatorPickupPointId)) {
            throw ValidationException::withMessages([
                'order' => 'Недостаточно прав для оформления отказа по этому заказу.',
            ]);
        }

        $order->loadMissing('buyer');
        if (! $order->buyer) {
            throw ValidationException::withMessages([
                'order' => 'Покупатель не найден.',
            ]);
        }

        $code = app(OtpCodeGenerator::class)->real();
        $key = $this->cacheKey($order->id, $operator->id);

        Cache::put($key, $code, now()->addMinutes(15));

        $number = $order->number ?? (string) $order->id;
        $order->buyer->notify(new MarketplaceAlert(
            'Код для отказа от получения',
            sprintf(
                'По заказу №%s в пункте выдачи оформляется отказ. Сообщите оператору код: %s',
                $number,
                $code,
            ),
            route('order.show', $order, false),
            NotificationCategory::OrderRefusalCode,
        ));
    }

    public function assertValidCode(Order $order, User $operator, string $inputCode): void
    {
        $key = $this->cacheKey($order->id, $operator->id);
        $stored = Cache::get($key);

        if ($stored === null) {
            throw ValidationException::withMessages([
                'refusal_code' => 'Код не запрашивался или истёк. Нажмите «Отправить код» в окне отказа.',
            ]);
        }

        $input = preg_replace('/\D+/', '', $inputCode);
        $expected = preg_replace('/\D+/', '', (string) $stored);

        if ($input === '' || $input !== $expected) {
            throw ValidationException::withMessages([
                'refusal_code' => 'Неверный код. Покупатель видит код в разделе «Уведомления».',
            ]);
        }

        Cache::forget($key);
    }
}
