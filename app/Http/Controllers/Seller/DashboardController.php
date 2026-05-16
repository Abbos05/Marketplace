<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Проверка, что пользователь продавец
        if ($user->role !== 'seller') {
            return redirect()->route('profile');
        }

        // Получаем товары продавца (используем seller_id)
        $products = Product::where('seller_id', $user->id)->get();

        // Статистика - используем правильную связь items.variant.product
        $stats = [
            'total_products' => $products->count(),
            'total_orders' => Order::whereHas('items.variant.product', function ($q) use ($user) {
                $q->where('seller_id', $user->id);
            })->count(),
            'total_sales' => Order::whereHas('items.variant.product', function ($q) use ($user) {
                $q->where('seller_id', $user->id);
            })->sum('total'),
            'pending_orders' => Order::whereHas('items.variant.product', function ($q) use ($user) {
                $q->where('seller_id', $user->id);
            })->whereIn('status', [Order::STATUS_NEW, Order::STATUS_INTRANSIT])->count(),
        ];

        // Последние заказы
        // Вместо 'user' используй 'buyer'
        $recentOrders = Order::with(['items.variant.product', 'buyer']) // ← замени user на buyer
            ->whereHas('items.variant.product', function ($q) use ($user) {
                $q->where('seller_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Популярные товары (используем views_count)
        $popularProducts = Product::where('seller_id', $user->id)
            ->orderBy('views_count', 'desc')
            ->limit(5)
            ->get();

        // Данные для графика (продажи по месяцам)
        $salesChart = $this->getSalesChartData($user->id);

        return Inertia::render('Seller/Dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'popularProducts' => $popularProducts,
            'salesChart' => $salesChart,
            'sellerProfile' => $user->sellerProfile,
        ]);
    }
private function getSalesChartData($userId)
{
    $months = [];
    $sales = [];

    for ($i = 11; $i >= 0; $i--) {
        $month = now()->subMonths($i);
        
        // Русские названия месяцев
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
        
        $englishMonth = $month->format('F');
        $russianMonth = $monthNames[$englishMonth] ?? $englishMonth;
        
        $months[] = $russianMonth;

        $total = Order::whereHas('items.variant.product', function ($q) use ($userId) {
            $q->where('seller_id', $userId);
        })
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->sum('total');

        $sales[] = $total;
    }

    return ['months' => $months, 'sales' => $sales];
}

}