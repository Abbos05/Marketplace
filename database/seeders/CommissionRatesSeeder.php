<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CommissionRate;
use Illuminate\Database\Seeder;

class CommissionRatesSeeder extends Seeder
{
    /**
     * Ставки комиссии по категориям товаров (листовые категории каталога).
     */
    public function run(): void
    {
        $defaultsByRootSlug = [
            'electronics' => ['percent' => 12, 'fixed' => 0],
            'clothing' => ['percent' => 15, 'fixed' => 0],
            'home' => ['percent' => 10, 'fixed' => 0],
            'sports' => ['percent' => 10, 'fixed' => 0],
            'auto' => ['percent' => 8, 'fixed' => 50],
            'books' => ['percent' => 7, 'fixed' => 0],
            'beauty' => ['percent' => 14, 'fixed' => 0],
            'kids' => ['percent' => 11, 'fixed' => 0],
            'pets' => ['percent' => 10, 'fixed' => 0],
            'furniture' => ['percent' => 9, 'fixed' => 0],
        ];

        $leaves = Category::query()
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->with('parent')
            ->get();

        foreach ($leaves as $leaf) {
            $rootSlug = $leaf->parent?->slug;
            $rate = $defaultsByRootSlug[$rootSlug] ?? ['percent' => 10, 'fixed' => 0];

            CommissionRate::query()->updateOrCreate(
                ['category_id' => $leaf->id],
                [
                    'percent' => $rate['percent'],
                    'fixed_amount' => $rate['fixed'],
                ],
            );
        }
    }
}
