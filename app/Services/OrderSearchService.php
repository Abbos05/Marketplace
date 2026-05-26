<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;

class OrderSearchService
{
    public const SEARCH_DAILY_CODE = 'daily_code';

    public const SEARCH_ORDER_NUMBER = 'order_number';

    public const SEARCH_ORDER_CODE = 'order_code';

    public const SEARCH_EMAIL = 'email';

    public const SEARCH_PHONE = 'phone';

    public const SEARCH_BUYER_ID = 'buyer_id';

    public const STATUS_FILTER_ACTIVE = 'active';

    public const STATUS_FILTER_READY = 'ready';

    public const STATUS_FILTER_DONE = 'done';

    public const STATUS_FILTER_ALL = 'all';

    private const BULK_SEARCH_LIMIT = 500;

    private const PVZ_RESULTS_PER_PAGE = 25;

    /**
     * Суточный код покупателя: ровно 8 цифр (пробел посередине допустим).
     */
    public function formatDailyPickupCodeFromQuery(string $query): ?string
    {
        $digitsOnly = preg_replace('/\D+/', '', $query);
        if (strlen($digitsOnly) !== 8) {
            return null;
        }

        return substr($digitsOnly, 0, 4).' '.substr($digitsOnly, 4, 4);
    }

    /**
     * Код конкретного заказа (10 символов) — «Получите товары по коду» на странице заказа.
     */
    public function normalizeOrderCodeFromQuery(string $query): ?string
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', $query));

        return strlen($normalized) === 10 && preg_match('/^[A-Z0-9]+$/', $normalized)
            ? $normalized
            : null;
    }

    /**
     * Как интерпретирован запрос поиска (для правил выдачи в ПВЗ).
     */
    public function classifySearch(string $orderSearch): string
    {
        $trimmed = $this->trimSearchInput($orderSearch);
        if ($trimmed === '') {
            return 'empty';
        }

        if ($this->formatDailyPickupCodeFromQuery($trimmed) !== null) {
            return self::SEARCH_DAILY_CODE;
        }

        if ($this->normalizeOrderCodeFromQuery($trimmed) !== null) {
            return self::SEARCH_ORDER_CODE;
        }

        if ($this->normalizeEmailFromQuery($trimmed) !== null) {
            return self::SEARCH_EMAIL;
        }

        $digitsOnly = preg_replace('/\D+/', '', $trimmed);
        if (strlen($digitsOnly) >= 10) {
            return self::SEARCH_PHONE;
        }

        if (ctype_digit($trimmed) && (int) $trimmed > 0) {
            return self::SEARCH_BUYER_ID;
        }

        return self::SEARCH_ORDER_NUMBER;
    }

    /**
     * Поиск заказов: только полное совпадение (номер, код выдачи, суточный код, buyer id/email/phone).
     */
    public function searchExact(string $orderSearch, ?int $limit = null): Collection
    {
        $orderSearch = $this->trimSearchInput($orderSearch);
        $searchType = $this->classifySearch($orderSearch);
        $limit ??= in_array($searchType, [
            self::SEARCH_DAILY_CODE,
            self::SEARCH_EMAIL,
            self::SEARCH_PHONE,
        ], true) ? self::BULK_SEARCH_LIMIT : 50;
        $normalized = preg_replace('/\s+/', '', $orderSearch);
        $digitsOnly = preg_replace('/\D+/', '', $orderSearch);
        $orderCode = strtoupper($normalized);
        $dailyCodeFormatted = (strlen($digitsOnly) === 8)
            ? substr($digitsOnly, 0, 4).' '.substr($digitsOnly, 4, 4)
            : null;
        $emailNormalized = $this->normalizeEmailFromQuery($orderSearch);

        return Order::with([
            'buyer' => fn ($q) => $q->withTrashed(),
            'pickupPoint',
            'items.variant.product.images',
        ])
            ->where(function ($q) use ($orderSearch, $normalized, $orderCode, $dailyCodeFormatted, $digitsOnly, $emailNormalized) {
                $q->where('number', $orderSearch);
                if ($normalized !== '' && $normalized !== $orderSearch) {
                    $q->orWhere('number', $normalized);
                }

                if (strlen($orderCode) === 10) {
                    $q->orWhere('order_code', $orderCode);
                }

                if ($dailyCodeFormatted !== null) {
                    $q->orWhereHas('buyer', fn ($b) => $b->withTrashed()
                        ->where('daily_pickup_code', $dailyCodeFormatted));
                }

                if (ctype_digit($orderSearch) && (int) $orderSearch > 0) {
                    $q->orWhereHas('buyer', fn ($b) => $b->withTrashed()
                        ->where('id', (int) $orderSearch));
                }

                if ($emailNormalized !== null) {
                    $buyerIds = \App\Models\User::withTrashed()
                        ->whereNotNull('email')
                        ->where('email', '!=', '')
                        ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNormalized])
                        ->pluck('id');

                    if ($buyerIds->isNotEmpty()) {
                        $q->orWhereIn('buyer_id', $buyerIds);
                    }
                }

                if ($emailNormalized === null && strlen($digitsOnly) >= 10) {
                    $q->orWhereHas('buyer', function ($b) use ($digitsOnly) {
                        $b->withTrashed()
                            ->where(function ($phoneQ) use ($digitsOnly) {
                                $phoneQ->where('phone', $digitsOnly)
                                    ->orWhereRaw(
                                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?",
                                        [$digitsOnly]
                                    );
                            });
                    });
                }
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @param  array{search_type?: string, issue_auth?: array}  $issueContext
     * @return list<array<string, mixed>>
     */
    public function mapOrdersForPanel(
        Collection $orders,
        ?int $operatorPickupPointId = null,
        array $issueContext = [],
    ): array {
        $pvzService = app(PvzOrderService::class);
        $searchType = $issueContext['search_type'] ?? '';
        $issueAuth = $issueContext['issue_auth'] ?? [];

        return $orders->map(function (Order $o) use ($operatorPickupPointId, $pvzService, $searchType, $issueAuth) {
            $base = $this->mapOrderForPvz($o);
            if ($operatorPickupPointId !== null) {
                $base['is_at_my_pickup'] = (int) $o->pickup_point_id === (int) $operatorPickupPointId;
                $base['can_manage'] = $pvzService->canManage($o, $operatorPickupPointId);
                $base['manage_hint'] = $pvzService->manageHint($o, $operatorPickupPointId);
                $base['can_issue'] = $pvzService->canIssueAfterSearch(
                    $o,
                    $operatorPickupPointId,
                    $searchType,
                    $issueAuth,
                );
                $base['issue_block_reason'] = $pvzService->issueBlockReason(
                    $o,
                    $operatorPickupPointId,
                    $searchType,
                    $issueAuth,
                );
                $base['allowed_statuses'] = $base['can_manage']
                    ? [Order::STATUS_ISSUED, Order::STATUS_REFUSED]
                    : [];
            }

            return $base;
        })->all();
    }

    /**
     * Данные заказа для панели ПВЗ — без кода выдачи (только у покупателя).
     *
     * @return array<string, mixed>
     */
    public function mapOrderForPvz(Order $o): array
    {
        $base = $this->mapOrderBase($o);
        unset($base['order_code']);

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapOrderBase(Order $o): array
    {
        return [
            'id' => $o->id,
            'number' => $o->number,
            'order_code' => $o->order_code,
            'total' => $o->total,
            'discount' => $o->discount,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'delivery_method' => $o->delivery_method,
            'delivery_address' => $o->delivery_address,
            'pickup_point_id' => $o->pickup_point_id,
            'pickup_point' => $o->pickupPoint ? [
                'id' => $o->pickupPoint->id,
                'title' => $o->pickupPoint->title,
                'address' => $o->pickupPoint->address,
            ] : null,
            'comment' => $o->comment,
            'created_at' => $o->created_at,
            'items_count' => $o->items->count(),
            'buyer' => $o->buyer ? [
                'id' => $o->buyer->id,
                'name' => $o->buyer->name,
                'last_name' => $o->buyer->last_name,
                'email' => $o->buyer->email,
                'phone' => $o->buyer->phone,
                'avatar' => $o->buyer->avatar,
                'role' => $o->buyer->role,
                'is_blocked' => $o->buyer->is_blocked,
                'deleted_at' => $o->buyer->deleted_at,
            ] : null,
            'items' => $o->items->map(fn ($item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'price_at_purchase' => $item->price_at_purchase,
                'product_name' => $item->variant?->product?->title ?? '—',
                'product_image' => $item->variant?->product?->images?->firstWhere('is_main', true)?->url ?? null,
            ]),
        ];
    }

    public function trimSearchInput(string $query, int $maxLength = 80): string
    {
        $trimmed = trim($query);

        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) > $maxLength) {
            $trimmed = mb_substr($trimmed, 0, $maxLength);
        }

        return $trimmed;
    }

    public function normalizeEmailFromQuery(string $query): ?string
    {
        $trimmed = trim($query);
        if ($trimmed === '' || ! str_contains($trimmed, '@')) {
            return null;
        }

        if (preg_match('/\s/', $trimmed)) {
            return null;
        }

        return strtolower($trimmed);
    }

    public function defaultPickupFilterForSearchType(string $searchType): string
    {
        return in_array($searchType, [
            self::SEARCH_EMAIL,
            self::SEARCH_PHONE,
            self::SEARCH_ORDER_NUMBER,
            self::SEARCH_BUYER_ID,
        ], true) ? 'all' : 'mine';
    }

    public function defaultStatusFilterForSearchType(string $searchType): string
    {
        return in_array($searchType, [
            self::SEARCH_DAILY_CODE,
            self::SEARCH_EMAIL,
            self::SEARCH_PHONE,
        ], true) ? self::STATUS_FILTER_ACTIVE : self::STATUS_FILTER_ALL;
    }

    /**
     * @return array{active: int, ready: int, done: int, all: int}
     */
    public function statusFilterCounts(Collection $orders): array
    {
        return [
            'active' => $orders->filter(fn (Order $o) => $this->matchesStatusFilter($o, self::STATUS_FILTER_ACTIVE))->count(),
            'ready' => $orders->filter(fn (Order $o) => $this->matchesStatusFilter($o, self::STATUS_FILTER_READY))->count(),
            'done' => $orders->filter(fn (Order $o) => $this->matchesStatusFilter($o, self::STATUS_FILTER_DONE))->count(),
            'all' => $orders->count(),
        ];
    }

    public function filterOrdersByStatus(Collection $orders, string $statusFilter): Collection
    {
        if ($statusFilter === self::STATUS_FILTER_ALL) {
            return $orders->values();
        }

        return $orders
            ->filter(fn (Order $o) => $this->matchesStatusFilter($o, $statusFilter))
            ->values();
    }

    public function sortOrdersForPvzPanel(Collection $orders, int $pickupPointId): Collection
    {
        $rank = fn (Order $o) => match ($o->status) {
            Order::STATUS_DELIVERED => (int) $o->pickup_point_id === $pickupPointId ? 10 : 20,
            Order::STATUS_INTRANSIT => (int) $o->pickup_point_id === $pickupPointId ? 30 : 40,
            Order::STATUS_NEW => (int) $o->pickup_point_id === $pickupPointId ? 50 : 60,
            Order::STATUS_ISSUED => 70,
            Order::STATUS_REFUSED => 80,
            Order::STATUS_CANCELED => 90,
            default => 100,
        };

        return $orders
            ->sortBy([
                fn (Order $o) => $rank($o),
                fn (Order $o) => -($o->updated_at?->timestamp ?? 0),
            ])
            ->values();
    }

    /**
     * @return array{
     *   orders: Collection,
     *   status_counts: array{active: int, ready: int, done: int, all: int},
     *   pagination: array{current_page: int, per_page: int, total: int, last_page: int}
     * }
     */
    public function paginatePvzSearchResults(
        Collection $orders,
        int $pickupPointId,
        string $statusFilter,
        int $page = 1,
    ): array {
        $statusCounts = $this->statusFilterCounts($orders);
        $filtered = $this->filterOrdersByStatus($orders, $statusFilter);
        $sorted = $this->sortOrdersForPvzPanel($filtered, $pickupPointId);
        $perPage = self::PVZ_RESULTS_PER_PAGE;
        $total = $sorted->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        return [
            'orders' => $sorted->slice(($page - 1) * $perPage, $perPage)->values(),
            'status_counts' => $statusCounts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    protected function matchesStatusFilter(Order $order, string $statusFilter): bool
    {
        return match ($statusFilter) {
            self::STATUS_FILTER_READY => $order->status === Order::STATUS_DELIVERED,
            self::STATUS_FILTER_DONE => in_array($order->status, [
                Order::STATUS_ISSUED,
                Order::STATUS_REFUSED,
                Order::STATUS_CANCELED,
            ], true),
            self::STATUS_FILTER_ACTIVE => in_array($order->status, [
                Order::STATUS_NEW,
                Order::STATUS_INTRANSIT,
                Order::STATUS_DELIVERED,
            ], true),
            default => true,
        };
    }
}
