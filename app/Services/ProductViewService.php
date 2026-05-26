<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductViewService
{
    /**
     * Фиксирует просмотр конкретного варианта.
     * Один и тот же вариант в рамках сессии считается один раз.
     */
    public function record(ProductVariant $variant, ?User $viewer = null): void
    {
        $variant->loadMissing('product');

        if (! $variant->product?->isPubliclyVisible()) {
            return;
        }

        if ($viewer !== null && (int) $viewer->id === (int) $variant->product->seller_id) {
            return;
        }

        $sessionKey = 'viewed_variant_'.$variant->id;
        if (session()->has($sessionKey)) {
            return;
        }

        DB::transaction(function () use ($variant, $sessionKey) {
            ProductVariant::query()
                ->whereKey($variant->id)
                ->increment('views_count');

            session()->put($sessionKey, true);
        });
    }
}
