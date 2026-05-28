<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use InvalidArgumentException;

class SellerPromotionEligibilityService
{
    /**
     * @param  array<int, array{price?: mixed, old_price?: mixed}>  $variantsPayload
     * @return array<string, array{key: string, label: string}>
     */
    public function eligibleBadges(Product $product, array $variantsPayload = []): array
    {
        $badges = config('seller_promotion_badges', []);
        $result = [];

        foreach ($badges as $key => $meta) {
            if ($this->matchesRule($product, $variantsPayload, $meta['rule'] ?? '', $meta)) {
                $result[$key] = [
                    'key' => $key,
                    'label' => $meta['label'] ?? $key,
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array{price?: mixed, old_price?: mixed}>  $variantsPayload
     */
    public function assertEligible(string $badgeKey, Product $product, array $variantsPayload = []): void
    {
        $badges = config('seller_promotion_badges', []);

        if (! isset($badges[$badgeKey])) {
            throw new InvalidArgumentException('Недопустимый тип бейджа.');
        }

        $meta = $badges[$badgeKey];
        if (! $this->matchesRule($product, $variantsPayload, $meta['rule'] ?? '', $meta)) {
            throw new InvalidArgumentException('Товар не соответствует условиям для выбранного бейджа.');
        }
    }

    public function labelForKey(string $badgeKey): string
    {
        return config("seller_promotion_badges.{$badgeKey}.label", $badgeKey);
    }

    /**
     * @param  array<int, array{price?: mixed, old_price?: mixed}>  $variantsPayload
     */
    private function matchesRule(Product $product, array $variantsPayload, string $rule, array $meta): bool
    {
        return match ($rule) {
            'has_variant_discount' => $this->hasVariantDiscount($product, $variantsPayload),
            'is_new_product' => $this->isNewProduct($product, (int) ($meta['days'] ?? 30)),
            default => false,
        };
    }

    /**
     * @param  array<int, array{price?: mixed, old_price?: mixed}>  $variantsPayload
     */
    private function hasVariantDiscount(Product $product, array $variantsPayload): bool
    {
        if ($variantsPayload !== []) {
            foreach ($variantsPayload as $variant) {
                $price = (float) ($variant['price'] ?? 0);
                $old = $variant['old_price'] ?? null;
                if ($old === '' || $old === null) {
                    continue;
                }
                $oldPrice = (float) $old;
                if ($oldPrice > $price && $price > 0) {
                    return true;
                }
            }

            return false;
        }

        return $product->variants()
            ->whereNotNull('old_price')
            ->whereColumn('old_price', '>', 'price')
            ->exists();
    }

    private function isNewProduct(Product $product, int $days): bool
    {
        if (! $product->created_at) {
            return false;
        }

        return $product->created_at->gte(now()->subDays(max(1, $days)));
    }

    /**
     * Собрать payload вариантов из запроса или модели для проверки правил.
     *
     * @return array<int, array{price: float, old_price: float|null}>
     */
    public function variantsPayloadFromRequest(array $variants): array
    {
        $payload = [];
        foreach ($variants as $variant) {
            $price = (float) ($variant['price'] ?? 0);
            $old = $variant['old_price'] ?? null;
            $oldPrice = ($old === '' || $old === null) ? null : (float) $old;
            $payload[] = [
                'price' => $price,
                'old_price' => $oldPrice,
            ];
        }

        return $payload;
    }

    /**
     * @return array<int, array{price: float, old_price: float|null}>
     */
    public function variantsPayloadFromProduct(Product $product): array
    {
        $product->loadMissing('variants');

        return $product->variants->map(fn (ProductVariant $v) => [
            'price' => (float) $v->price,
            'old_price' => $v->old_price !== null ? (float) $v->old_price : null,
        ])->all();
    }
}
