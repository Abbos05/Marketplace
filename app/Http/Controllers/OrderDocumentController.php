<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Transaction;
use App\Services\OrderLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderDocumentController extends Controller
{
    public function receipt(Order $order)
    {
        $this->authorizeOrderAccess($order, allowAdmin: true);

        $order->load([
            'buyer' => fn ($q) => $q->withTrashed(),
            'items.variant.product',
            'items.seller' => fn ($q) => $q->withTrashed(),
        ]);

        $bySeller = $order->items->groupBy(fn ($i) => $i->seller_id);

        $pdf = Pdf::loadView('pdf.order-receipt', [
            'order' => $order,
            'bySeller' => $bySeller,
        ])
            ->setPaper('a5', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans');

        $filename = 'chek_zakaz_'.($order->number ?? $order->id).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Финансовый документ: payment | refund | cancel
     */
    public function financial(Request $request, Order $order, string $docType)
    {
        $this->authorizeOrderAccess($order, allowAdmin: true);

        $docType = strtolower($docType);
        if (! in_array($docType, ['payment', 'refund', 'cancel'], true)) {
            abort(404);
        }

        $order->load(['buyer', 'items.variant.product']);

        $txQuery = Transaction::query()
            ->where('order_id', $order->id)
            ->where('type', $docType);

        if ($docType === 'payment') {
            $txQuery->whereIn('status', ['completed', 'pending']);
        } elseif ($docType === 'refund') {
            $txQuery->where('status', 'refunded');
        } else {
            $txQuery->where('type', OrderLedgerService::TYPE_CANCEL);
        }

        $transactions = $txQuery->with(['product', 'seller'])->orderBy('id')->get();

        if ($transactions->isEmpty()) {
            $ledger = app(OrderLedgerService::class);
            if ($docType === 'payment' && $order->payment_status === 'paid') {
                $ledger->recordPayment($order);
            } elseif ($docType === 'refund' && $order->payment_status === 'refunded') {
                $ledger->recordRefund($order);
            } elseif ($docType === 'cancel' && $order->status === Order::STATUS_CANCELED) {
                $ledger->recordCancel($order);
            }
            $transactions = Transaction::query()
                ->where('order_id', $order->id)
                ->where('type', $docType)
                ->when($docType === 'payment', fn ($q) => $q->whereIn('status', ['completed', 'pending']))
                ->when($docType === 'refund', fn ($q) => $q->where('status', 'refunded'))
                ->when($docType === 'cancel', fn ($q) => $q->where('type', OrderLedgerService::TYPE_CANCEL))
                ->with(['product', 'seller'])
                ->orderBy('id')
                ->get();
        }

        if ($transactions->isEmpty()) {
            return redirect()->back()->with('error', 'Документ для скачивания пока недоступен.');
        }

        $title = match ($docType) {
            'refund' => 'Документ о возврате средств',
            'cancel' => 'Документ об отмене заказа',
            default => 'Документ об оплате',
        };

        $pdf = Pdf::loadView('pdf.order-financial', [
            'order' => $order,
            'transactions' => $transactions,
            'docType' => $docType,
            'title' => $title,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans');

        $filename = match ($docType) {
            'refund' => 'vozvrat',
            'cancel' => 'otmena',
            default => 'oplata',
        }.'_zakaz_'.($order->number ?? $order->id).'.pdf';

        return $pdf->download($filename);
    }

    private function authorizeOrderAccess(Order $order, bool $allowAdmin = false): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if ($allowAdmin && $user->isStaff()) {
            return;
        }

        if ($order->buyer_id === $user->id) {
            return;
        }

        abort(403);
    }
}
