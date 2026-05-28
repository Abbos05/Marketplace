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
            'description' => 'Нажмите «Мои коллекции» и настройте свою коллекцию. Опишите: описание, изображения профиля и баннера, а также установите комиссию за вторичные продажи',
            'button_text' => 'Посмотреть',
            'image_path' => '/img/header/slider1.png',
            'sort_order' => 0,
            'is_active' => false,
            'link_type' => $categoryId ? HomeSlide::LINK_CATEGORY : HomeSlide::LINK_NONE,
            'link_target' => $categoryId ? (string) $categoryId : null,
        ]);

        HomeSlide::query()->create([
            'title' => 'Продавайте свои товары без комиссии',
            'description' => 'Установите свою комиссию за каждую вторичную продажу и получайте доход без усилий. Подключите смарт-контракт и начните зарабатывать.',
            'button_text' => 'Настроить продажу',
            'image_path' => '/img/header/slider2.png',
            'sort_order' => 1,
            'is_active' => false,
            'link_type' => HomeSlide::LINK_ROUTE,
            'link_target' => 'seller.products.create',
        ]);

        HomeSlide::query()->create([
            'title' => 'Создайте свою уникальную коллекцию',
            'description' => 'Загрузите свои произведения, добавьте описание, теги и настройте роялти. Ваша коллекция — ваше наследие.',
            'button_text' => 'Смотреть товар',
            'image_path' => '/img/header/slider3.png',
            'sort_order' => 2,
            'is_active' => false,
            'link_type' => $productId ? HomeSlide::LINK_PRODUCT : HomeSlide::LINK_NONE,
            'link_target' => $productId ? (string) $productId : null,
        ]);
    }
}
