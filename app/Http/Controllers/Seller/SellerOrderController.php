<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderLedgerService;
use App\Services\OrderNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SellerOrderController extends Controller
{
    private const PER_PAGE = 50;

    /** Переходы статуса доставки (оплата — только payment_status). */
    private const SELLER_TRANSITIONS = [
        Order::STATUS_NEW => [Order::STATUS_INTRANSIT, Order::STATUS_CANCELED],
        Order::STATUS_INTRANSIT => [Order::STATUS_DELIVERED, Order::STATUS_CANCELED],
    ];

    private const STATUS_LABELS = [
        Order::STATUS_NEW => 'Новый заказ',
        Order::STATUS_INTRANSIT => 'В пути',
        Order::STATUS_DELIVERED => 'В пункте выдачи',
        Order::STATUS_ISSUED => 'Выдан покупателю',
        Order::STATUS_CANCELED => 'Отменён',
        Order::STATUS_REFUSED => 'Отказ от получения',
    ];

    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Order::with(['buyer', 'region', 'items.variant.product'])
            ->whereHas('items', fn($q) => $q->where('seller_id', $user->id));

        // Status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search by order number or buyer name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhereHas('buyer', fn($bq) => $bq->where('name', 'like', "%{$search}%"));
            });
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sorting
        match ($request->sort ?? 'newest') {
            'oldest'      => $query->oldest(),
            'total_asc'   => $query->orderBy('total', 'asc'),
            'total_desc'  => $query->orderBy('total', 'desc'),
            default       => $query->latest(),
        };

        $paginated = $query->paginate(self::PER_PAGE)->withQueryString();

        // Status counts for tabs
        $baseQuery = Order::whereHas('items', fn($q) => $q->where('seller_id', $user->id));
        $statusCounts = [];
        foreach (array_keys(self::STATUS_LABELS) as $status) {
            $statusCounts[$status] = (clone $baseQuery)->where('status', $status)->count();
        }

        // Summary stats
        $stats = [
            'total_orders'   => (clone $baseQuery)->count(),
            'pending_orders' => (clone $baseQuery)->whereIn('status', [Order::STATUS_NEW, Order::STATUS_INTRANSIT])->count(),
            'month_revenue'  => $this->sumSellerPayoutForMonth($user->id, realized: true),
            'month_pending_revenue' => $this->sumSellerPayoutForMonth($user->id, realized: false),
            'month_gross_revenue'  => $this->sumSellerGrossForMonth($user->id, realized: true),
            'month_commission'  => $this->sumSellerCommissionForMonth($user->id, realized: true),
            'today_orders'   => (clone $baseQuery)->whereDate('created_at', today())->count(),
        ];

        $orders = $paginated->through(fn($order) => $this->formatOrder($order, $user->id));

        return Inertia::render('Seller/Orders/Index', [
            'orders'       => $orders,
            'stats'        => $stats,
            'statusCounts' => $statusCounts,
            'statusLabels' => self::STATUS_LABELS,
            'filters'      => $request->only(['status', 'search', 'date_from', 'date_to', 'sort']),
        ]);
    }

    public function show(Order $order)
    {
        $user = Auth::user();

        $sellerItems = $order->items()
            ->where('seller_id', $user->id)
            ->with('variant.product.images')
            ->get();

        if ($sellerItems->isEmpty()) {
            abort(403, 'Этот заказ не содержит ваших товаров.');
        }

        $order->load(['buyer', 'region']);

        $nextStatuses = self::filterTransitionsForPayment(
            self::SELLER_TRANSITIONS[$order->status] ?? [],
            $order->payment_status,
        );

        return Inertia::render('Seller/Orders/Show', [
            'order'         => [
                'id'              => $order->id,
                'number'          => $order->number,
                'order_code'      => $order->order_code,
                'status'          => $order->status,
                'status_label'    => self::STATUS_LABELS[$order->status] ?? $order->status,
                'total'           => (float) $order->total,
                'discount'        => (float) $order->discount,
                'delivery_address'=> $order->delivery_address,
                'delivery_method' => $order->delivery_method,
                'payment_method'  => $order->payment_method,
                'payment_status'  => $order->payment_status,
                'comment'         => $order->comment,
                'created_at'      => $order->created_at->format('d.m.Y H:i'),
                'updated_at'      => $order->updated_at->format('d.m.Y H:i'),
                'buyer'           => [
                    'id'    => $order->buyer->id,
                    'name'  => $order->buyer->name,
                    'email' => $order->buyer->email,
                ],
                'region' => $order->region ? ['name' => $order->region->name] : null,
            ],
            'items'         => $sellerItems->map(fn($item) => $this->formatItem($item)),
            'nextStatuses'  => array_map(
                fn($s) => ['value' => $s, 'label' => self::STATUS_LABELS[$s] ?? $s],
                $nextStatuses   
            ),
            'statusLabels'  => self::STATUS_LABELS,
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $user = Auth::user();

        $hasSellerItems = $order->items()->where('seller_id', $user->id)->exists();
        if (!$hasSellerItems) {
            abort(403);
        }

        $allowed = self::SELLER_TRANSITIONS[$order->status] ?? [];
        $newStatus = $request->input('status');

        $allowed = self::filterTransitionsForPayment($allowed, $order->payment_status);

        if (! in_array($newStatus, $allowed, true)) {
            return back()->with('error', 'Недопустимый переход статуса.');
        }

        if ($order->payment_status !== 'paid' && in_array($newStatus, Order::deliveryStatuses(), true)) {
            return back()->with('error', 'Нельзя изменить доставку или выдачу без оплаты заказа.');
        }

        $order->update(['status' => $newStatus]);
        $order->refresh();

        app(OrderNotificationService::class)->notifyStatusChange($order, $newStatus);

        $flash = 'Статус заказа обновлён: '.(self::STATUS_LABELS[$newStatus] ?? $newStatus);

        if ($newStatus === Order::STATUS_CANCELED) {
            app(OrderLedgerService::class)->reverseCommission($order->fresh());
            if ($order->payment_status === 'paid') {
                $flash .= '. Покупатель подтвердит возврат средств в личном кабинете.';
            }
        }

        return back()->with('success', $flash);
    }

    public function export(Request $request)
    {
        $user = Auth::user();

        $query = Order::with(['buyer', 'region', 'items' => fn($q) => $q->where('seller_id', $user->id)->with('variant.product')])
            ->whereHas('items', fn($q) => $q->where('seller_id', $user->id));

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhereHas('buyer', fn($bq) => $bq->where('name', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->latest()->get();

        $rows = [];
        $rows[] = ['Номер заказа', 'Дата', 'Покупатель', 'Товар', 'Вариант', 'Кол-во', 'Цена', 'Сумма', 'Комиссия', 'К выплате', 'Статус заказа'];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $optionsStr = '';
                if ($item->variant && $item->variant->options) {
                    $opts = is_array($item->variant->options) ? $item->variant->options : json_decode($item->variant->options, true);
                    $optionsStr = implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($opts), array_values($opts)));
                }
                $rows[] = [
                    $order->number,
                    $order->created_at->format('d.m.Y H:i'),
                    $order->buyer->name ?? '—',
                    $item->variant->product->title ?? '—',
                    $optionsStr ?: ($item->variant->sku ?? '—'),
                    $item->quantity,
                    number_format($item->price_at_purchase, 2, '.', ''),
                    number_format($item->price_at_purchase * $item->quantity, 2, '.', ''),
                    number_format($item->commission_amount, 2, '.', ''),
                    number_format($item->seller_payout_amount, 2, '.', ''),
                    self::STATUS_LABELS[$order->status] ?? $order->status,
                ];
            }
        }

        if ($request->input('format') === 'xlsx') {
            return app(\App\Services\Excel\SellerOrdersExcelExporter::class)->download($rows);
        }

        $filename = 'orders_' . now()->format('Y-m-d_H-i') . '.csv';
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function formatOrder(Order $order, int $sellerId): array
    {
        $sellerItems = $order->items->where('seller_id', $sellerId);
        $sellerTotal = $sellerItems->sum(fn($i) => $i->price_at_purchase * $i->quantity);
        $sellerCommission = $sellerItems->sum(fn($i) => $i->commission_amount);
        $sellerPayout = $sellerItems->sum(fn($i) => $i->seller_payout_amount > 0
            ? $i->seller_payout_amount
            : ($i->price_at_purchase * $i->quantity) - $i->commission_amount);

        return [
            'id'           => $order->id,
            'number'       => $order->number,
            'status'       => $order->status,
            'status_label' => self::STATUS_LABELS[$order->status] ?? $order->status,
            'total'        => (float) $sellerTotal,
            'commission'   => (float) $sellerCommission,
            'seller_payout'=> (float) $sellerPayout,
            'items_count'  => $sellerItems->count(),
            'created_at'   => $order->created_at->format('d.m.Y H:i'),
            'buyer'        => [
                'name'  => $order->buyer->name ?? '—',
                'email' => $order->buyer->email ?? '—',
            ],
            'region' => $order->region ? $order->region->name : null,
        ];
    }

    private function formatItem(OrderItem $item): array
    {
        $variant = $item->variant;
        $product = $variant?->product;

        $mainImage = null;
        if ($product) {
            $mainImg = $product->images->where('is_main', true)->first()
                ?? $product->images->first();
            $mainImage = $mainImg?->url ?? $mainImg?->path ?? null;
        }

        $optionsStr = '';
        if ($variant && $variant->options) {
            $opts = is_array($variant->options) ? $variant->options : json_decode($variant->options, true);
            if (is_array($opts)) {
                $optionsStr = implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($opts), array_values($opts)));
            }
        }

        return [
            'id'                 => $item->id,
            'quantity'           => $item->quantity,
            'price_at_purchase'  => (float) $item->price_at_purchase,
            'subtotal'           => (float) ($item->price_at_purchase * $item->quantity),
            'commission_percent' => (float) $item->commission_percent,
            'commission_amount'  => (float) $item->commission_amount,
            'seller_payout_amount' => (float) ($item->seller_payout_amount > 0
                ? $item->seller_payout_amount
                : ($item->price_at_purchase * $item->quantity) - $item->commission_amount),
            'commission_status' => $item->commission_status,
            'variant' => [
                'id'      => $variant?->id,
                'sku'     => $variant?->sku,
                'options' => $optionsStr,
            ],
            'product' => [
                'id'    => $product?->id,
                'title' => $product?->title ?? 'Товар удалён',
                'image' => $mainImage,
            ],
        ];
    }

    /**
     * @param  list<string>  $transitions
     * @return list<string>
     */
    private static function filterTransitionsForPayment(array $transitions, string $paymentStatus): array
    {
        if ($paymentStatus === 'paid') {
            return $transitions;
        }

        return array_values(array_filter(
            $transitions,
            fn (string $status) => $status === Order::STATUS_CANCELED,
        ));
    }

    private function sumSellerPayoutForMonth(int $sellerId, bool $realized): float
    {
        $query = OrderItem::query()
            ->where('seller_id', $sellerId)
            ->whereHas('order', fn ($q) => $q
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month));

        $realized ? $query->realizedRevenue() : $query->pendingRevenue();

        return (float) $query->sum(DB::raw(OrderItem::payoutAmountSql()));
    }

    private function sumSellerGrossForMonth(int $sellerId, bool $realized): float
    {
        $query = OrderItem::query()
            ->where('seller_id', $sellerId)
            ->whereHas('order', fn ($q) => $q
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month));

        $realized ? $query->realizedRevenue() : $query->pendingRevenue();

        return (float) $query->sum(DB::raw('order_items.price_at_purchase * order_items.quantity'));
    }

    private function sumSellerCommissionForMonth(int $sellerId, bool $realized): float
    {
        $query = OrderItem::query()
            ->where('seller_id', $sellerId)
            ->whereHas('order', fn ($q) => $q
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month));

        $realized ? $query->realizedRevenue() : $query->pendingRevenue();

        return (float) $query->sum('commission_amount');
    }
}
