<?php

namespace App\Services;

use App\Models\CommissionRate;
use App\Models\OrderItem;
use App\Models\Product;

class CommissionService
{
    public const DEFAULT_PERCENT = 10.0;

    /**
     * @return array{gross: float, commission: float, seller_payout: float, percent: float, fixed_amount: float}
     */
    public function calculateForProduct(Product $product, float $unitPrice, int $quantity): array
    {
        $quantity = max(1, $quantity);
        $gross = round($unitPrice * $quantity, 2);

        $rate = $product->category_id
            ? CommissionRate::query()->where('category_id', $product->category_id)->first()
            : null;

        $percent = $rate ? (float) $rate->percent : self::DEFAULT_PERCENT;
        $fixedAmount = $rate ? (float) $rate->fixed_amount : 0.0;

        $commission = round(($gross * $percent / 100) + ($fixedAmount * $quantity), 2);
        $commission = min($commission, $gross);
        $sellerPayout = round($gross - $commission, 2);

        return [
            'gross' => $gross,
            'commission' => $commission,
            'seller_payout' => $sellerPayout,
            'percent' => round($percent, 2),
            'fixed_amount' => round($fixedAmount, 2),
        ];
    }

    /**
     * Rebuilds a snapshot for legacy items that were created before commission fields existed.
     *
     * @return array{gross: float, commission: float, seller_payout: float, percent: float, fixed_amount: float}
     */
    public function calculateForOrderItem(OrderItem $item): array
    {
        $item->loadMissing('variant.product');

        $product = $item->variant?->product;
        if ($product) {
            return $this->calculateForProduct(
                $product,
                (float) $item->price_at_purchase,
                (int) $item->quantity,
            );
        }

        $gross = round((float) $item->price_at_purchase * (int) $item->quantity, 2);
        $percent = (float) ($item->commission_percent ?? self::DEFAULT_PERCENT);
        $fixedAmount = (float) ($item->commission_fixed_amount ?? 0);
        $commission = round(($gross * $percent / 100) + ($fixedAmount * (int) $item->quantity), 2);
        $commission = min($commission, $gross);

        return [
            'gross' => $gross,
            'commission' => $commission,
            'seller_payout' => round($gross - $commission, 2),
            'percent' => round($percent, 2),
            'fixed_amount' => round($fixedAmount, 2),
        ];
    }

    /**
     * Возвращает актуальный snapshot комиссии; при необходимости дописывает его в БД.
     *
     * @return array{gross: float, commission: float, seller_payout: float, percent: float, fixed_amount: float}
     */
    public function resolveSnapshot(OrderItem $item, bool $persist = false): array
    {
        $needsPersist = (float) ($item->commission_amount ?? 0) <= 0
            || (float) ($item->seller_payout_amount ?? 0) <= 0;

        $snapshot = $this->calculateForOrderItem($item);

        if ($persist && $needsPersist) {
            $item->forceFill([
                'commission_percent' => $snapshot['percent'],
                'commission_fixed_amount' => $snapshot['fixed_amount'],
                'commission_amount' => $snapshot['commission'],
                'seller_payout_amount' => $snapshot['seller_payout'],
                'commission_status' => $item->commission_status ?: 'pending',
            ])->save();
        }

        return $snapshot;
    }
}
