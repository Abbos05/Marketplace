<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role !== 'seller') {
            return redirect()->route('profile');
        }

        $sellerId = $user->id;

        $stats = [
            'total_products' => Product::where('seller_id', $sellerId)->count(),
            'total_orders' => Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))->count(),
            'total_sales' => $this->sumSellerPayout($sellerId, realized: true),
            'pending_orders' => Order::whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
                ->whereIn('status', [Order::STATUS_NEW, Order::STATUS_INTRANSIT])
                ->count(),
        ];

        $recentOrders = Order::with(['buyer', 'items'])
            ->whereHas('items', fn ($q) => $q->where('seller_id', $sellerId))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Order $order) => $this->formatRecentOrder($order, $sellerId));

        $popularProducts = Product::query()
            ->where('seller_id', $sellerId)
            ->withSum('variants as variants_views_sum', 'views_count')
            ->orderByDesc('variants_views_sum')
            ->limit(5)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'title' => $product->title,
                'views_count' => (int) ($product->variants_views_sum ?? 0),
                'image' => $product->resolveListingImageUrl(),
            ]);

        $salesChart = $this->getSalesChartData($sellerId);

        return Inertia::render('Seller/Dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'popularProducts' => $popularProducts,
            'salesChart' => $salesChart,
            'sellerProfile' => $user->sellerProfile,
        ]);
    }

    private function sumSellerPayout(int $sellerId, bool $realized): float
    {
        $query = OrderItem::query()->where('seller_id', $sellerId);
        $realized ? $query->realizedRevenue() : $query->pendingRevenue();

        return (float) $query->sum(DB::raw(OrderItem::payoutAmountSql()));
    }

    private function formatRecentOrder(Order $order, int $sellerId): array
    {
        $sellerItems = $order->items->where('seller_id', $sellerId);
        $sellerPayout = $sellerItems->sum(fn ($i) => $i->seller_payout_amount > 0
            ? (float) $i->seller_payout_amount
            : ((float) $i->price_at_purchase * $i->quantity) - (float) $i->commission_amount);

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status,
            'seller_payout' => (float) $sellerPayout,
            'buyer' => $order->buyer ? [
                'name' => $order->buyer->name,
            ] : null,
        ];
    }

    private function getSalesChartData(int $sellerId): array
    {
        $months = [];
        $sales = [];

        $monthNames = [
            'January' => 'Янв',
            'February' => 'Фев',
            'March' => 'Мар',
            'April' => 'Апр',
            'May' => 'Май',
            'June' => 'Июн',
            'July' => 'Июл',
            'August' => 'Авг',
            'September' => 'Сен',
            'October' => 'Окт',
            'November' => 'Ноя',
            'December' => 'Дек',
        ];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $englishMonth = $month->format('F');
            $months[] = $monthNames[$englishMonth] ?? $englishMonth;

            $query = OrderItem::query()
                ->where('seller_id', $sellerId)
                ->whereHas('order', fn ($q) => $q
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month));

            $query->realizedRevenue();

            $sales[] = (float) $query->sum(DB::raw(OrderItem::payoutAmountSql()));
        }

        return ['months' => $months, 'sales' => $sales];
    }
}
