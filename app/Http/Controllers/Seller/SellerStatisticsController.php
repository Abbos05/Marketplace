<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SellerStatisticsController extends Controller
{
    private const PERIOD_DAYS = [
        '30d'  => 30,
        '90d'  => 90,
        '180d' => 180,
        '365d' => 365,
        'all'  => null,
    ];

    private const STATUS_LABELS = [
        Order::STATUS_NEW => 'Новые',
        Order::STATUS_INTRANSIT => 'В пути',
        Order::STATUS_DELIVERED => 'В пункте выдачи',
        Order::STATUS_ISSUED => 'Выданы',
        Order::STATUS_CANCELED => 'Отменены',
        Order::STATUS_REFUSED => 'Отказ от получения',
    ];

    public function index(Request $request)
    {
        $user   = Auth::user();
        $userId = $user->id;

        $period  = $request->input('period', '365d');
        $days    = self::PERIOD_DAYS[$period] ?? 365;
        $dateFrom = $days ? now()->subDays($days)->startOfDay() : null;

        return Inertia::render('Seller/Statistics/Index', [
            'kpi'          => $this->getKpi($userId, $dateFrom),
            'monthly'      => $this->getMonthly($userId),
            'byStatus'     => $this->getByStatus($userId, $dateFrom),
            'topProducts'  => $this->getTopProducts($userId, $dateFrom),
            'daily'        => $this->getDaily($userId),
            'period'       => $period,
            'statusLabels' => self::STATUS_LABELS,
        ]);
    }

    // ---------------------------------------------------------------
    // KPI: revenue, orders_count, avg_order_value, items_sold
    // ---------------------------------------------------------------
    private function getKpi(int $userId, ?\Carbon\Carbon $dateFrom): array
    {
        $base = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->where('order_items.seller_id', $userId);

        if ($dateFrom) {
            $base->where('orders.created_at', '>=', $dateFrom);
        }

        $row = (clone $base)
            ->selectRaw('
                COALESCE(SUM(order_items.price_at_purchase * order_items.quantity), 0) AS revenue,
                COUNT(DISTINCT order_items.order_id) AS orders_count,
                COALESCE(SUM(order_items.quantity), 0) AS items_sold
            ')
            ->first();

        $revenue      = (float) $row->revenue;
        $ordersCount  = (int)   $row->orders_count;
        $itemsSold    = (int)   $row->items_sold;
        $avgOrderValue = $ordersCount > 0 ? round($revenue / $ordersCount, 2) : 0;

        // Compare with previous same-length period for delta
        $prevRevenue = 0;
        $prevOrders  = 0;
        if ($dateFrom) {
            $periodLen  = now()->diffInDays($dateFrom);
            $prevFrom   = $dateFrom->copy()->subDays($periodLen);
            $prevRow = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereNull('orders.deleted_at')
                ->where('order_items.seller_id', $userId)
                ->where('orders.created_at', '>=', $prevFrom)
                ->where('orders.created_at', '<',  $dateFrom)
                ->selectRaw('
                    COALESCE(SUM(order_items.price_at_purchase * order_items.quantity), 0) AS revenue,
                    COUNT(DISTINCT order_items.order_id) AS orders_count
                ')
                ->first();
            $prevRevenue = (float) $prevRow->revenue;
            $prevOrders  = (int)   $prevRow->orders_count;
        }

        return [
            'revenue'         => $revenue,
            'orders_count'    => $ordersCount,
            'avg_order_value' => $avgOrderValue,
            'items_sold'      => $itemsSold,
            'revenue_delta'   => $prevRevenue > 0 ? round(($revenue - $prevRevenue) / $prevRevenue * 100, 1) : null,
            'orders_delta'    => $prevOrders  > 0 ? round(($ordersCount - $prevOrders)  / $prevOrders  * 100, 1) : null,
        ];
    }

    // ---------------------------------------------------------------
    // Monthly data: last 12 months — revenue + orders count
    // ---------------------------------------------------------------
    private function getMonthly(int $userId): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->where('order_items.seller_id', $userId)
            ->where('orders.created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw('
                YEAR(orders.created_at)  AS yr,
                MONTH(orders.created_at) AS mo,
                COALESCE(SUM(order_items.price_at_purchase * order_items.quantity), 0) AS revenue,
                COUNT(DISTINCT order_items.order_id) AS orders_count
            ')
            ->groupByRaw('YEAR(orders.created_at), MONTH(orders.created_at)')
            ->orderByRaw('YEAR(orders.created_at), MONTH(orders.created_at)')
            ->get()
            ->keyBy(fn($r) => $r->yr . '-' . str_pad($r->mo, 2, '0', STR_PAD_LEFT));

        $monthNames = [
            1 => 'Янв', 2 => 'Фев', 3 => 'Мар', 4 => 'Апр',
            5 => 'Май', 6 => 'Июн', 7 => 'Июл', 8 => 'Авг',
            9 => 'Сен', 10 => 'Окт', 11 => 'Ноя', 12 => 'Дек',
        ];

        $months  = [];
        $revenue = [];
        $orders  = [];

        for ($i = 11; $i >= 0; $i--) {
            $dt  = now()->subMonths($i);
            $key = $dt->year . '-' . str_pad($dt->month, 2, '0', STR_PAD_LEFT);
            $row = $rows->get($key);

            $months[]  = $monthNames[$dt->month];
            $revenue[] = $row ? (float) $row->revenue      : 0;
            $orders[]  = $row ? (int)   $row->orders_count : 0;
        }

        return compact('months', 'revenue', 'orders');
    }

    // ---------------------------------------------------------------
    // Orders count grouped by status (within period)
    // ---------------------------------------------------------------
    private function getByStatus(int $userId, ?\Carbon\Carbon $dateFrom): array
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->where('order_items.seller_id', $userId)
            ->selectRaw('orders.status, COUNT(DISTINCT order_items.order_id) AS cnt')
            ->groupBy('orders.status');

        if ($dateFrom) {
            $query->where('orders.created_at', '>=', $dateFrom);
        }

        $rows  = $query->get()->keyBy('status');
        $total = $rows->sum('cnt');

        $result = [];
        foreach (self::STATUS_LABELS as $key => $label) {
            $count = (int) ($rows->get($key)->cnt ?? 0);
            $result[] = [
                'status'  => $key,
                'label'   => $label,
                'count'   => $count,
                'percent' => $total > 0 ? round($count / $total * 100, 1) : 0,
            ];
        }

        return $result;
    }

    // ---------------------------------------------------------------
    // Top-7 products by revenue within period
    // ---------------------------------------------------------------
    private function getTopProducts(int $userId, ?\Carbon\Carbon $dateFrom): array
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('orders.deleted_at')
            ->whereNull('products.deleted_at')
            ->where('order_items.seller_id', $userId)
            ->selectRaw('
                products.id,
                products.title,
                COALESCE(SUM(order_items.price_at_purchase * order_items.quantity), 0) AS revenue,
                COUNT(DISTINCT order_items.order_id) AS orders_count,
                COALESCE(SUM(order_items.quantity), 0) AS items_sold
            ')
            ->groupBy('products.id', 'products.title')
            ->orderByDesc('revenue')
            ->limit(7);

        if ($dateFrom) {
            $query->where('orders.created_at', '>=', $dateFrom);
        }

        $rows = $query->get();

        // Load main images for found product IDs
        $productIds = $rows->pluck('id');
        $images = ProductImage::whereIn('product_id', $productIds)
            ->where('is_main', true)
            ->get()
            ->keyBy('product_id');
        $fallbackImages = ProductImage::whereIn('product_id', $productIds)
            ->whereNotIn('product_id', $images->keys())
            ->orderBy('id')
            ->get()
            ->keyBy('product_id');

        return $rows->map(function ($row) use ($images, $fallbackImages) {
            $img = $images->get($row->id) ?? $fallbackImages->get($row->id);
            return [
                'id'          => $row->id,
                'title'       => $row->title,
                'image'       => $img?->url ?? $img?->path ?? null,
                'revenue'     => (float) $row->revenue,
                'orders_count'=> (int)   $row->orders_count,
                'items_sold'  => (int)   $row->items_sold,
            ];
        })->values()->all();
    }

    // ---------------------------------------------------------------
    // Daily revenue for last 30 days (sparkline)
    // ---------------------------------------------------------------
    private function getDaily(int $userId): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->where('order_items.seller_id', $userId)
            ->where('orders.created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('
                DATE(orders.created_at) AS day,
                COALESCE(SUM(order_items.price_at_purchase * order_items.quantity), 0) AS revenue
            ')
            ->groupByRaw('DATE(orders.created_at)')
            ->orderByRaw('DATE(orders.created_at)')
            ->get()
            ->keyBy('day');

        $days    = [];
        $revenue = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $row  = $rows->get($date);
            $days[]    = now()->subDays($i)->format('d.m');
            $revenue[] = $row ? (float) $row->revenue : 0;
        }

        return compact('days', 'revenue');
    }
}
