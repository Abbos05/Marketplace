<?php

namespace App\Services;

use App\Models\OrderItem;
use Illuminate\Support\Collection;

class CommissionBreakdownService
{
    public function __construct(
        private readonly CommissionService $commissionService,
    ) {}

    /**
     * @return array{
     *   gross: float,
     *   commission_total: float,
     *   seller_payout: float,
     *   commission_percent: float,
     *   commission_percent_amount: float,
     *   commission_fixed_amount: float,
     *   payment_processing_fee: float,
     *   vat_amount: float,
     *   platform_net: float,
     *   formula_note: string,
     * }
     */
    public function forOrderItem(OrderItem $item): array
    {
        $item->loadMissing('variant.product');

        $calc = $this->commissionService->resolveSnapshot($item, persist: true);
        $gross = (float) $calc['gross'];
        $commissionTotal = (float) $calc['commission'];
        $sellerPayout = (float) $calc['seller_payout'];

        $percentPart = round($gross * (float) $calc['percent'] / 100, 2);
        $fixedPart = round((float) $calc['fixed_amount'] * (int) $item->quantity, 2);

        $paymentFeePercent = (float) config('marketplace.commission_split.payment_fee_percent_of_commission', 0);
        $vatPercent = (float) config('marketplace.commission_split.vat_percent_of_commission', 20);

        // Эквайринг и НДС — только доли от комиссии позиции, не от всего оборота заказа.
        $paymentFee = $commissionTotal > 0 && $paymentFeePercent > 0
            ? round($commissionTotal * $paymentFeePercent / 100, 2)
            : 0.0;
        $vat = $commissionTotal > 0 && $vatPercent > 0
            ? round($commissionTotal * $vatPercent / 100, 2)
            : 0.0;
        $platformNet = round(max(0, $commissionTotal - $paymentFee - $vat), 2);

        $allocated = round($paymentFee + $vat + $platformNet, 2);
        $diff = round($commissionTotal - $allocated, 2);
        if (abs($diff) >= 0.01) {
            $platformNet = round($platformNet + $diff, 2);
        }

        return [
            'gross' => $gross,
            'commission_total' => $commissionTotal,
            'seller_payout' => $sellerPayout,
            'commission_percent' => (float) $calc['percent'],
            'commission_percent_amount' => $percentPart,
            'commission_fixed_amount' => $fixedPart,
            'payment_processing_fee' => $paymentFee,
            'vat_amount' => $vat,
            'platform_net' => $platformNet,
            'formula_note' => sprintf(
                'Комиссия = %.2f%% от суммы позиции + %s ₽ фикс. за ед.; из комиссии: эквайринг %.2f%%, НДС %.2f%%, остаток — доля маркетплейса.',
                $calc['percent'],
                number_format($calc['fixed_amount'], 2, '.', ''),
                $paymentFeePercent,
                $vatPercent
            ),
        ];
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     * @return array<string, mixed>
     */
    public function aggregate(Collection $items): array
    {
        $rows = [];
        $totals = [
            'gross' => 0.0,
            'commission_total' => 0.0,
            'seller_payout' => 0.0,
            'commission_percent_amount' => 0.0,
            'commission_fixed_amount' => 0.0,
            'payment_processing_fee' => 0.0,
            'vat_amount' => 0.0,
            'platform_net' => 0.0,
            'items_count' => $items->count(),
        ];

        foreach ($items as $item) {
            $b = $this->forOrderItem($item);
            $rows[] = array_merge($b, [
                'product_title' => $item->variant?->product?->title ?? '—',
                'quantity' => (int) $item->quantity,
            ]);
            foreach (['gross', 'commission_total', 'seller_payout', 'commission_percent_amount', 'commission_fixed_amount', 'payment_processing_fee', 'vat_amount', 'platform_net'] as $key) {
                $totals[$key] += $b[$key];
            }
        }

        foreach ($totals as $key => $val) {
            if (is_float($val)) {
                $totals[$key] = round($val, 2);
            }
        }

        $paymentFeePercent = (float) config('marketplace.commission_split.payment_fee_percent_of_commission', 0);
        $vatPercent = (float) config('marketplace.commission_split.vat_percent_of_commission', 20);

        return [
            'rows' => $rows,
            'totals' => $totals,
            'split_labels' => [
                'payment_fee' => $paymentFeePercent > 0
                    ? "Эквайринг ({$paymentFeePercent}% от комиссии)"
                    : 'Эквайринг (не применяется)',
                'vat' => $vatPercent > 0
                    ? "НДС, упрощённо ({$vatPercent}% от комиссии)"
                    : 'НДС (не применяется)',
                'platform_net' => 'Чистая доля маркетплейса',
            ],
        ];
    }
}
