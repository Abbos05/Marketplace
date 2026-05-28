<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Promotion;
use App\Services\SellerPromotionEligibilityService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoPromotionSeeder extends Seeder
{
    /** Доля товаров с бейджем акции (остальные — только скидка по old_price, если есть). */
    private const PROMO_SHARE_PERCENT = 42;

    private const DEMO_SELLER_IDS = [6, 7];

    public function run(): void
    {
        Promotion::query()
            ->where('created_by', Promotion::CREATED_BY_SELLER)
            ->whereIn('seller_id', self::DEMO_SELLER_IDS)
            ->delete();

        $eligibility = app(SellerPromotionEligibilityService::class);
        $now = Carbon::now();

        $products = Product::query()
            ->whereIn('seller_id', self::DEMO_SELLER_IDS)
            ->where('status', 'approved')
            ->with('variants')
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            $hash = crc32('demo-promo:'.$product->id);

            if ($hash % 100 >= self::PROMO_SHARE_PERCENT) {
                continue;
            }

            $this->ensureSomeEligibility($product, $hash, $now);

            $payload = $eligibility->variantsPayloadFromProduct($product);
            $eligible = $eligibility->eligibleBadges($product, $payload);
            if ($eligible === []) {
                continue;
            }

            $keys = array_values(array_keys($eligible));
            $badgeKey = $keys[$hash % count($keys)];
            $label = $eligibility->labelForKey($badgeKey);

            $endsAt = $now->copy()->addDays(14 + ($hash % 46));

            $promotion = Promotion::query()->create([
                'badge_label' => $label,
                'starts_at' => $now,
                'ends_at' => $endsAt,
                'status' => Promotion::STATUS_ACTIVE,
                'created_by' => Promotion::CREATED_BY_SELLER,
                'seller_id' => $product->seller_id,
            ]);

            $promotion->products()->sync([$product->id]);
        }
    }

    /**
     * Часть товаров без скидки и с «старым» created_at — подгоняем под один из типов бейджа.
     */
    private function ensureSomeEligibility(Product $product, int $hash, Carbon $now): void
    {
        $eligibility = app(SellerPromotionEligibilityService::class);
        $payload = $eligibility->variantsPayloadFromProduct($product);
        if ($eligibility->eligibleBadges($product, $payload) !== []) {
            return;
        }

        $preferNew = ($hash & 1) === 1;

        if ($preferNew) {
            $product->forceFill([
                'created_at' => $now->copy()->subDays(3 + ($hash % 20)),
            ])->save();

            return;
        }

        $variant = $product->variants->first();
        if (! $variant) {
            return;
        }

        $price = (float) $variant->price;
        if ($price <= 0) {
            return;
        }

        $oldPrice = round($price * (1.08 + (($hash % 15) / 100)), 2);
        $variant->forceFill([
            'old_price' => $oldPrice,
            'discount_percent' => round((1 - $price / $oldPrice) * 100, 2),
        ])->save();
    }
}
