<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PvzAccrual;
use App\Models\User;
use App\Services\OrderNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PvzOrderService
{
    public const PVZ_TRANSITIONS = [
        Order::STATUS_DELIVERED => [Order::STATUS_ISSUED, Order::STATUS_REFUSED],
    ];

    public function canManage(Order $order, int $operatorPickupPointId): bool
    {
        if ((int) $order->pickup_point_id !== $operatorPickupPointId) {
            return false;
        }

        if ($order->status !== Order::STATUS_DELIVERED) {
            return false;
        }

        return true;
    }

    public function manageHint(Order $order, int $operatorPickupPointId): string
    {
        if ((int) $order->pickup_point_id !== $operatorPickupPointId) {
            $addr = $order->delivery_address ?: ($order->pickupPoint
                ? $order->pickupPoint->title.', '.$order->pickupPoint->address
                : 'другой пункт');

            return 'Заказ назначен на другой пункт выдачи: '.$addr;
        }

        return match ($order->status) {
            Order::STATUS_NEW => 'Заказ ещё новый — не доставлен в пункт.',
            Order::STATUS_INTRANSIT => 'Заказ в пути — ещё не прибыл в пункт.',
            Order::STATUS_ISSUED => 'Заказ уже выдан покупателю.',
            Order::STATUS_CANCELED => 'Заказ отменён.',
            Order::STATUS_REFUSED => 'Покупатель отказался от получения.',
            Order::STATUS_DELIVERED => $order->isPaid()
                ? 'Можно выдать заказ или оформить отказ.'
                : 'Заказ не оплачен — выдача недоступна.',
            default => 'Действия недоступны для текущего статуса.',
        };
    }

    public function buyerMatchesDailyCode(Order $order, string $formattedDailyCode): bool
    {
        $order->loadMissing('buyer');
        $buyer = $order->buyer;
        if (! $buyer) {
            return false;
        }

        $buyerCode = $buyer->daily_pickup_code ?? $buyer->ensureDailyPickupCode();

        return $buyerCode === $formattedDailyCode;
    }

    /**
     * @param  array{daily_code?: ?string, order_code?: ?string, at?: ?int}  $issueAuth
     */
    public function isIssueAuthValid(array $issueAuth): bool
    {
        $at = $issueAuth['at'] ?? null;
        if ($at === null) {
            return false;
        }

        return now()->timestamp - (int) $at <= 1800;
    }

    /**
     * @param  array{daily_code?: ?string, order_code?: ?string, at?: ?int}  $issueAuth
     */
    public function orderMatchesIssueAuth(Order $order, array $issueAuth): bool
    {
        if (! $this->isIssueAuthValid($issueAuth)) {
            return false;
        }

        if (! empty($issueAuth['daily_code']) && $this->buyerMatchesDailyCode($order, $issueAuth['daily_code'])) {
            return true;
        }

        if (! empty($issueAuth['order_code']) && $order->order_code) {
            return strtoupper((string) $order->order_code) === strtoupper((string) $issueAuth['order_code']);
        }

        return false;
    }

    public function canIssueAfterSearch(
        Order $order,
        int $operatorPickupPointId,
        string $searchType,
        array $issueAuth,
    ): bool {
        if (! $this->canManage($order, $operatorPickupPointId)) {
            return false;
        }

        if (! $order->isPaid()) {
            return false;
        }

        if (! in_array($searchType, [OrderSearchService::SEARCH_DAILY_CODE, OrderSearchService::SEARCH_ORDER_CODE], true)) {
            return false;
        }

        return $this->orderMatchesIssueAuth($order, $issueAuth);
    }

    /**
     * @param  array{daily_code?: ?string, order_code?: ?string, at?: ?int}  $issueAuth
     */
    public function issueBlockReason(
        Order $order,
        int $operatorPickupPointId,
        string $searchType,
        array $issueAuth,
    ): ?string {
        if (! $this->canManage($order, $operatorPickupPointId)) {
            return null;
        }

        if (! $order->isPaid()) {
            return 'Заказ не оплачен. Покупателю нужно оплатить заказ в личном кабинете — выдача недоступна.';
        }

        if (! in_array($searchType, [OrderSearchService::SEARCH_DAILY_CODE, OrderSearchService::SEARCH_ORDER_CODE], true)) {
            return 'Выдача: найдите заказ по суточному коду (8 цифр, все заказы покупателя) или по коду заказа (10 символов на странице заказа).';
        }

        if ($searchType === OrderSearchService::SEARCH_ORDER_CODE && ! $this->orderMatchesIssueAuth($order, $issueAuth)) {
            return 'Выдать можно только заказ, найденный по его коду (10 символов).';
        }

        if ($searchType === OrderSearchService::SEARCH_DAILY_CODE && ! $this->buyerMatchesDailyCode($order, $issueAuth['daily_code'] ?? '')) {
            return 'Этот заказ другого покупателя — выдайте по его суточному коду.';
        }

        if (! $this->orderMatchesIssueAuth($order, $issueAuth)) {
            return 'Сессия поиска истекла — найдите заказ по коду снова.';
        }

        return null;
    }

    /**
     * @param  array{daily_code?: ?string, order_code?: ?string, at?: ?int}  $issueAuth
     */
    public function assertAuthorizedForIssue(Order $order, array $issueAuth): void
    {
        if (! $this->isIssueAuthValid($issueAuth)) {
            throw ValidationException::withMessages([
                'status' => 'Сначала найдите заказ по суточному коду (8 цифр) или по коду заказа (10 символов).',
            ]);
        }

        if (! $this->orderMatchesIssueAuth($order, $issueAuth)) {
            throw ValidationException::withMessages([
                'status' => 'Этот заказ нельзя выдать по текущему поиску. Найдите его по нужному коду.',
            ]);
        }
    }

    public function transition(
        Order $order,
        string $newStatus,
        User $operator,
        int $operatorPickupPointId,
        array $issueAuth = [],
    ): Order {
        if (! $this->canManage($order, $operatorPickupPointId)) {
            throw ValidationException::withMessages([
                'status' => 'Недостаточно прав для изменения этого заказа.',
            ]);
        }

        $allowed = self::PVZ_TRANSITIONS[$order->status] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Недопустимый переход статуса.',
            ]);
        }

        if ($newStatus === Order::STATUS_ISSUED && ! $order->canSetDeliveryStatus($newStatus)) {
            throw ValidationException::withMessages([
                'status' => 'Нельзя выдать неоплаченный заказ.',
            ]);
        }

        if ($newStatus === Order::STATUS_ISSUED) {
            $this->assertAuthorizedForIssue($order, $issueAuth);
        }

        return DB::transaction(function () use ($order, $newStatus, $operator, $operatorPickupPointId) {
            $now = now();
            $payload = ['status' => $newStatus];

            if ($newStatus === Order::STATUS_ISSUED) {
                $payload['issued_by_user_id'] = $operator->id;
                $payload['issued_at'] = $now;
                $payload['refused_by_user_id'] = null;
                $payload['refused_at'] = null;
            } else {
                $payload['refused_by_user_id'] = $operator->id;
                $payload['refused_at'] = $now;
            }

            $order->update($payload);

            $message = 'Статус заказа обновлён';

            if ($newStatus === Order::STATUS_REFUSED) {
                app(OrderLedgerService::class)->reverseCommission($order->fresh());
                if ($order->payment_status === 'paid') {
                    $message = 'Отказ оформлен. Покупателю нужно подтвердить возврат средств в личном кабинете.';
                }
            } elseif ($newStatus === Order::STATUS_ISSUED) {
                app(OrderLedgerService::class)->finalizeCommission($order->fresh());
                $this->recordAccrual($order->fresh(), $operator, $operatorPickupPointId);
            }

            $fresh = $order->fresh(['buyer', 'pickupPoint', 'items.variant.product.images']);
            app(OrderNotificationService::class)->notifyStatusChange($fresh, $newStatus);

            return $fresh;
        });
    }

    protected function recordAccrual(Order $order, User $operator, int $pickupPointId): void
    {
        $orderTotal = (float) $order->total;
        $amount = PvzFeeCalculator::forOrderTotal($orderTotal);
        $period = now()->format('Y-m');

        PvzAccrual::query()->firstOrCreate(
            ['order_id' => $order->id],
            [
                'pickup_point_id' => $pickupPointId,
                'user_id' => $operator->id,
                'amount' => $amount,
                'order_total' => $orderTotal,
                'type' => PvzAccrual::TYPE_ISSUED,
                'period' => $period,
            ],
        );
    }

    /**
     * @return array{issued_count: int, refused_count: int, earnings: float, period: string}
     */
    public function monthlyStats(User $operator, int $pickupPointId, ?string $period = null): array
    {
        $period = $period ?? now()->format('Y-m');
        [$year, $month] = explode('-', $period);

        $start = now()->setDate((int) $year, (int) $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $issuedCount = Order::query()
            ->where('pickup_point_id', $pickupPointId)
            ->where('issued_by_user_id', $operator->id)
            ->where('status', Order::STATUS_ISSUED)
            ->whereBetween('issued_at', [$start, $end])
            ->count();

        $refusedCount = Order::query()
            ->where('pickup_point_id', $pickupPointId)
            ->where('refused_by_user_id', $operator->id)
            ->where('status', Order::STATUS_REFUSED)
            ->whereBetween('refused_at', [$start, $end])
            ->count();

        $earnings = (float) PvzAccrual::query()
            ->where('user_id', $operator->id)
            ->where('pickup_point_id', $pickupPointId)
            ->where('period', $period)
            ->sum('amount');

        return [
            'issued_count' => $issuedCount,
            'refused_count' => $refusedCount,
            'earnings' => $earnings,
            'period' => $period,
        ];
    }

    /**
     * @return list<array{period: string, issued_count: int, refused_count: int, earnings: float}>
     */
    public function periodSummaries(
        User $operator,
        int $pickupPointId,
        int $months = 12,
        bool $onlyWithActivity = true,
        ?string $periodFrom = null,
        ?string $periodTo = null,
        string $sort = 'desc',
    ): array {
        $summaries = [];

        if ($periodFrom !== null && $periodTo !== null) {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $periodFrom)->startOfMonth();
            $end = \Carbon\Carbon::createFromFormat('Y-m', $periodTo)->startOfMonth();
            if ($start->gt($end)) {
                [$start, $end] = [$end, $start];
            }
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $period = $cursor->format('Y-m');
                $row = $this->monthlyStats($operator, $pickupPointId, $period);
                if (! $onlyWithActivity || $row['issued_count'] > 0 || $row['refused_count'] > 0) {
                    $summaries[] = $row;
                }
                $cursor->addMonth();
            }
        } else {
            for ($i = 0; $i < $months; $i++) {
                $period = now()->subMonths($i)->format('Y-m');
                $row = $this->monthlyStats($operator, $pickupPointId, $period);
                if ($onlyWithActivity && $row['issued_count'] === 0 && $row['refused_count'] === 0) {
                    continue;
                }
                $summaries[] = $row;
            }
        }

        usort($summaries, function (array $a, array $b) use ($sort) {
            return $sort === 'asc'
                ? strcmp($a['period'], $b['period'])
                : strcmp($b['period'], $a['period']);
        });

        return $summaries;
    }

    /**
     * @return list<string>
     */
    public function availablePeriods(User $operator, int $pickupPointId, int $monthsBack = 24): array
    {
        $periods = [];
        for ($i = 0; $i < $monthsBack; $i++) {
            $periods[] = now()->subMonths($i)->format('Y-m');
        }

        return $periods;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function incomingForPickup(int $pickupPointId, int $limit = 8): array
    {
        $searchService = app(OrderSearchService::class);

        return $searchService->mapOrdersForPanel(
            Order::query()
                ->with([
                    'buyer' => fn ($q) => $q->withTrashed(),
                    'pickupPoint',
                    'items.variant.product.images',
                ])
                ->where('pickup_point_id', $pickupPointId)
                ->whereIn('status', [Order::STATUS_NEW, Order::STATUS_INTRANSIT])
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get(),
            $pickupPointId,
        );
    }

    /**
     * @return list<array{order_id: int, number: string, action: string, action_label: string, total: float, handled_at: string|null}>
     */
    public function recentOperations(User $operator, int $pickupPointId, int $limit = 12): array
    {
        return Order::query()
            ->where('pickup_point_id', $pickupPointId)
            ->where(function ($q) use ($operator) {
                $q->where(function ($q2) use ($operator) {
                    $q2->where('status', Order::STATUS_ISSUED)
                        ->where('issued_by_user_id', $operator->id);
                })->orWhere(function ($q2) use ($operator) {
                    $q2->where('status', Order::STATUS_REFUSED)
                        ->where('refused_by_user_id', $operator->id);
                });
            })
            ->orderByRaw('COALESCE(refused_at, issued_at) DESC')
            ->limit($limit)
            ->get()
            ->map(function (Order $order) {
                $isRefused = $order->status === Order::STATUS_REFUSED;
                $at = $isRefused ? $order->refused_at : $order->issued_at;

                return [
                    'order_id' => $order->id,
                    'number' => $order->number,
                    'action' => $isRefused ? 'refused' : 'issued',
                    'action_label' => $isRefused ? 'Отказ' : 'Выдача',
                    'total' => (float) $order->total,
                    'handled_at' => $at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function queueForPickup(int $pickupPointId, int $limit = 50): array
    {
        $searchService = app(OrderSearchService::class);

        return $searchService->mapOrdersForPanel(
            Order::query()
                ->with([
                    'buyer' => fn ($q) => $q->withTrashed(),
                    'pickupPoint',
                    'items.variant.product.images',
                ])
                ->where('pickup_point_id', $pickupPointId)
                ->where('status', Order::STATUS_DELIVERED)
                ->orderBy('updated_at')
                ->limit($limit)
                ->get(),
            $pickupPointId
        );
    }
}
