<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoPromotionSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()
            ->where('status', 'approved')
            ->where('is_on_action', true)
            ->orderBy('id')
            ->limit(8)
            ->pluck('id');

        if ($products->isEmpty()) {
            return;
        }

        $spring = Promotion::query()->firstOrCreate(
            ['slug' => 'vesennyaya-rasprodazha'],
            [
                'title' => 'Весенняя распродажа',
                'badge_label' => 'Весна',
                'description' => 'Подборка товаров со скидками весенней акции.',
                'starts_at' => now()->subMonth(),
                'ends_at' => now()->addMonths(2),
                'status' => Promotion::STATUS_ACTIVE,
                'created_by' => Promotion::CREATED_BY_ADMIN,
                'seller_id' => null,
                'is_featured' => true,
            ],
        );

        $spring->products()->syncWithoutDetaching($products->take(6)->all());

        $hit = Promotion::query()->firstOrCreate(
            ['slug' => 'hit-nedeli'],
            [
                'title' => 'Хит недели',
                'badge_label' => 'Хит',
                'description' => 'Популярные товары недели.',
                'starts_at' => now()->subWeek(),
                'ends_at' => now()->addWeeks(3),
                'status' => Promotion::STATUS_ACTIVE,
                'created_by' => Promotion::CREATED_BY_ADMIN,
                'seller_id' => null,
                'is_featured' => false,
            ],
        );

        $hit->products()->syncWithoutDetaching($products->slice(4, 4)->all());
    }
}
