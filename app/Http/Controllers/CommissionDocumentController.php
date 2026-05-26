<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CommissionBreakdownService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommissionDocumentController extends Controller
{
    public function __construct(
        private readonly CommissionBreakdownService $breakdown,
    ) {}

    /**
     * PDF по одному заказу (позиции продавца или все позиции для админа).
     */
    public function orderReceipt(Order $order, Request $request)
    {
        $user = Auth::user();
        $isStaff = $user?->isStaff() ?? false;
        $sellerId = $isStaff ? null : $user?->id;

        if (! $user || (! $isStaff && ! $sellerId)) {
            abort(403);
        }

        if ($sellerId && ! $order->items()->where('seller_id', $sellerId)->exists()) {
            abort(403);
        }

        $order->load([
            'buyer' => fn ($q) => $q->withTrashed(),
            'items.variant.product',
            'items.seller' => fn ($q) => $q->withTrashed(),
        ]);

        $items = $order->items;
        if ($sellerId) {
            $items = $items->where('seller_id', $sellerId);
        }

        if ($items->isEmpty()) {
            return redirect()->back()->with('error', 'Нет позиций для отчёта по комиссии.');
        }

        $aggregate = $this->breakdown->aggregate($items);
        $seller = $sellerId ? $order->items->firstWhere('seller_id', $sellerId)?->seller : null;

        $pdf = Pdf::loadView('pdf.commission-operation', [
            'title' => 'Отчёт по комиссии и выплате',
            'order' => $order,
            'aggregate' => $aggregate,
            'seller' => $seller,
            'audience' => $isStaff ? 'admin' : 'seller',
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans');

        $suffix = $sellerId ? "seller_{$sellerId}" : 'full';

        return $pdf->download("komissiya_zakaz_{$order->number}_{$suffix}.pdf");
    }

    /**
     * JSON для модального окна на странице статистики продавца.
     */
    public function periodBreakdown(Request $request)
    {
        $user = Auth::user();
        if (! $user || $user->role !== 'seller') {
            abort(403);
        }

        $period = $request->input('period', '365d');
        $items = $this->sellerItemsForPeriod($user->id, $period);

        return response()->json($this->breakdown->aggregate($items));
    }

    /**
     * PDF сводки по комиссии за период (статистика продавца).
     */
    public function periodReport(Request $request)
    {
        $user = Auth::user();
        if (! $user || $user->role !== 'seller') {
            abort(403);
        }

        $period = $request->input('period', '365d');
        $items = $this->sellerItemsForPeriod($user->id, $period);
        $aggregate = $this->breakdown->aggregate($items);

        $periodLabel = match ($period) {
            '30d' => '30 дней',
            '90d' => '3 месяца',
            '180d' => '6 месяцев',
            '365d' => '12 месяцев',
            'all' => 'всё время',
            default => $period,
        };

        $pdf = Pdf::loadView('pdf.commission-period', [
            'title' => 'Сводка по комиссии за период',
            'period' => $period,
            'period_label' => $periodLabel,
            'aggregate' => $aggregate,
            'seller' => $user,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download('komissiya_period_'.$period.'_'.now()->format('Y-m-d').'.pdf');
    }

    private function sellerItemsForPeriod(int $sellerId, string $period)
    {
        $days = match ($period) {
            '30d' => 30,
            '90d' => 90,
            '180d' => 180,
            '365d' => 365,
            default => null,
        };

        $query = OrderItem::query()
            ->with(['variant.product', 'order'])
            ->where('seller_id', $sellerId)
            ->realizedRevenue()
            ->whereHas('order', function ($q) use ($days) {
                if ($days) {
                    $q->where('created_at', '>=', now()->subDays($days)->startOfDay());
                }
            });

        return $query->get();
    }
}
