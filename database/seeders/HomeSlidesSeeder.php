<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\Product;
use Illuminate\Database\Seeder;

class HomeSlidesSeeder extends Seeder
{
    public function run(): void
    {
        HomeSlide::query()->delete();

        $categoryId = Category::query()
            ->where('is_active', true)
            ->whereNotNull('parent_id')
            ->orderBy('id')
            ->value('id');

        if ($categoryId === null) {
            $categoryId = Category::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');
        }

        $productId = Product::query()
            ->where('status', 'approved')
            ->orderBy('id')
            ->value('id');

        HomeSlide::query()->create([
            'title' => 'Самые лучшие техники',
            'description' => 'Откройте мир современной техники от ведущих брендов. Смартфоны, ноутбуки, бытовая техника и электроника по выгодным ценам',
            'button_text' => 'Посмотреть',
            'image_path' => '/img/header/slider1.png',
            'sort_order' => 0,
            'is_active' => true,
            'link_type' => $categoryId ? HomeSlide::LINK_CATEGORY : HomeSlide::LINK_NONE,
            'link_target' => $categoryId ? (string) $categoryId : null,
        ]);

        HomeSlide::query()->create([
            'title' => 'Продавайте свои товары быстро и удобно',
            'description' => 'Размещайте товары за несколько минут, находите покупателей и управляйте продажами в одном месте. Удобная платформа для быстрой и безопасной торговли.',
            'button_text' => 'Настроить продажу',
            'image_path' => '/img/header/slider2.png',
            'sort_order' => 1,
            'is_active' => true,
            'link_type' => HomeSlide::LINK_ROUTE,
            'link_target' => 'seller.products.create',
        ]);

        HomeSlide::query()->create([
            'title' => 'iPhone 15 Pro по выгодной цене',
            'description' => 'Флагманский смартфон Apple с титановым корпусом, камерой профессионального уровня и чипом A17 Pro.',
            'button_text' => 'Смотреть товар',
            'image_path' => '/img/header/slider3.png',
            'sort_order' => 2,
            'is_active' => true,
            'link_type' => $productId ? HomeSlide::LINK_PRODUCT : HomeSlide::LINK_NONE,
            'link_target' => $productId ? 2 : null,
        ]);
    }
}
