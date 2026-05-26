<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductDataSeeder extends Seeder
{
    public function run()
    {
     

        // 2. Товары (20 штук, по 2 на категорию)
        $products = [
            // Категория 1: Электроника
            ['seller_id' => 2, 'category_id' => 1, 'title' => 'Смартфон iPhone 15 Pro', 'description' => 'Флагманский смартфон с процессором A17 Pro, титановым корпусом.', 'short_description' => 'Мощный iPhone', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],
            ['seller_id' => 3, 'category_id' => 1, 'title' => 'Ноутбук Xiaomi Book Pro', 'description' => '15.6" OLED, Intel Core i7, 16GB RAM, 512GB SSD.', 'short_description' => 'Ультрабук', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],

            // Категория 2: Одежда и обувь
            ['seller_id' => 3, 'category_id' => 2, 'title' => 'Кроссовки Nike Air Max', 'description' => 'Удобные кроссовки с амортизацией Air Max.', 'short_description' => 'Стильные кроссовки', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],
            ['seller_id' => 2, 'category_id' => 2, 'title' => 'Пуховик зимний Columbia', 'description' => 'Тёплый пуховик с технологией Omni-Heat.', 'short_description' => 'Защита от холода', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],

            // Категория 3: Дом и интерьер
            ['seller_id' => 2, 'category_id' => 3, 'title' => 'Умная колонка Яндекс Станция 2', 'description' => 'Голосовой помощник Алиса, качественный звук.', 'short_description' => 'Колонка с Алисой', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],
            ['seller_id' => 3, 'category_id' => 3, 'title' => 'Набор посуды Tefal', 'description' => 'Антипригарное покрытие, 5 кастрюль + 3 сковороды.', 'short_description' => 'Качественная посуда', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],

            // Категория 4: Спорт и отдых
            ['seller_id' => 3, 'category_id' => 4, 'title' => 'Беговая дорожка', 'description' => 'Электрическая, 12 программ, нагрузка до 120 кг.', 'short_description' => 'Домашний тренажёр', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],
            ['seller_id' => 2, 'category_id' => 4, 'title' => 'Велосипед горный Stels', 'description' => '24 скорости, амортизационная вилка, дисковые тормоза.', 'short_description' => 'Для активного отдыха', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],

            // Категория 5: Книги и медиа
            ['seller_id' => 2, 'category_id' => 5, 'title' => 'Электронная книга PocketBook', 'description' => 'Экран E Ink, подсветка, поддержка всех форматов.', 'short_description' => 'Удобное чтение', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],
            ['seller_id' => 3, 'category_id' => 5, 'title' => 'Bluetooth колонка JBL', 'description' => 'Защита от воды, 10 часов работы, мощный звук.', 'short_description' => 'Портативная колонка', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],

            // Категория 6: Красота и здоровье
            ['seller_id' => 3, 'category_id' => 6, 'title' => 'Массажёр для спины', 'description' => 'Электрический с подогревом, 4 ролика.', 'short_description' => 'Домашний массаж', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],
            ['seller_id' => 2, 'category_id' => 6, 'title' => 'Ирригатор для зубов', 'description' => 'Съёмный бак, 4 насадки, импульсная технология.', 'short_description' => 'Уход за полостью рта', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],

            // Категория 7: Детские товары
            ['seller_id' => 2, 'category_id' => 7, 'title' => 'Детская коляска 3 в 1', 'description' => 'Трансформируемая, лёгкая, большие колёса.', 'short_description' => 'Надёжная коляска', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],
            ['seller_id' => 3, 'category_id' => 7, 'title' => 'Конструктор LEGO City', 'description' => '300 деталей, фигурки полицейских и преступников.', 'short_description' => 'Любимая игра', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],

            // Категория 8: Автотовары
            ['seller_id' => 3, 'category_id' => 8, 'title' => 'Видеорегистратор 4K', 'description' => 'Ночная съёмка, GPS модуль, 170° обзор.', 'short_description' => 'Надёжная защита', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],
            ['seller_id' => 2, 'category_id' => 8, 'title' => 'Автомобильный пылесос', 'description' => 'Беспроводной, 12000 Pa, набор насадок.', 'short_description' => 'Чистота в авто', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],

            // Категория 9: Зоотовары
            ['seller_id' => 2, 'category_id' => 9, 'title' => 'Сухой корм для собак', 'description' => 'Премиум класс, натуральные ингредиенты.', 'short_description' => 'Правильное питание', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],
            ['seller_id' => 3, 'category_id' => 9, 'title' => 'Когтеточка', 'description' => 'Столбик с полками, верёвка сизаль 50 см.', 'short_description' => 'Для кошек', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],

            // Категория 10: Мебель
            ['seller_id' => 3, 'category_id' => 10, 'title' => 'Компьютерное кресло', 'description' => 'Эргономичное, регулировка высоты и подлокотников.', 'short_description' => 'Комфортное кресло', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],
            ['seller_id' => 2, 'category_id' => 10, 'title' => 'Письменный стол', 'description' => 'ЛДСП 120x60 см, белый, ящик.', 'short_description' => 'Стол для работы', 'min_price' => 0 'sales_count' => rand(10, 200), 'status' => 'approved', 'is_on_action' => true, 'created_at' => Carbon::now()->subDays(rand(1, 30)), 'updated_at' => Carbon::now()],
        ];

     

        // 4. Вставляем товары (получаем их ID)
        DB::table('products')->insert($products);
        $productIds = DB::table('products')->orderBy('id')->pluck('id')->toArray();

        // 5. Варианты для каждого товара (суммарно ~50)
        $variants = [];
        $variantId = 1;
        $allVariants = []; // для связи изображений

        foreach ($productIds as $index => $productId) {
            // Определяем количество вариантов: 2 или 3, чтобы в сумме было около 50 (20 товаров -> 50 вариантов)
            $variantsCount = ($index < 10) ? 3 : 2; // первым 10 товарам – по 3 варианта, остальным – по 2 = 10*3 + 10*2 = 50
            $viewsLeft = rand(100, 5000);
            for ($i = 1; $i <= $variantsCount; $i++) {
                // Генерация опций (цвет, размер, память и т.д.)
                $options = $this->generateOptions($index, $i);
                $basePrice = $products[$index]['min_price'] == 0 ? rand(1000, 100000) : $products[$index]['min_price'];
                // Корректируем цену варианта: базовая +- 10-30%
                $price = round($basePrice * (1 + (rand(-10, 30) / 100)), 2);
                $oldPrice = rand(0, 1) ? round($price * (1 + rand(5, 40) / 100), 2) : null;
                $discountPercent = $oldPrice ? round((1 - $price / $oldPrice) * 100, 2) : 0;
                $stock = rand(0, 500);
                $weight = rand(100, 5000);
                $variantViews = $i === $variantsCount
                    ? $viewsLeft
                    : rand(1, max(1, (int) ($viewsLeft / ($variantsCount - $i + 1))));
                $viewsLeft -= $variantViews;

                $variants[] = [
                    'id' => $variantId,
                    'product_id' => $productId,
                    'sku' => app(\App\Services\ArticleNumberService::class)->format($variantId),
                    'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
                    'price' => $price,
                    'old_price' => $oldPrice,
                    'discount_percent' => $discountPercent,
                    'action_start' => rand(0, 1) ? Carbon::now()->addDays(rand(1, 10)) : null,
                    'action_end' => rand(0, 1) ? Carbon::now()->addDays(rand(20, 60)) : null,
                    'stock' => $stock,
                    'views_count' => $variantViews,
                    'weight_grams' => $weight,
                    'region_id' => null,
                    'is_active' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'deleted_at' => null,
                ];
                $allVariants[] = ['id' => $variantId, 'product_id' => $productId];
                $variantId++;
            }

            // Обновляем min_price товара (минимальная цена среди его вариантов)
            $productVariantsPrices = array_column(array_filter($variants, fn($v) => $v['product_id'] == $productId), 'price');
            $minPrice = min($productVariantsPrices);
            DB::table('products')->where('id', $productId)->update(['min_price' => $minPrice]);
        }

        DB::table('product_variants')->insert($variants);

        // 6. Изображения для товаров и вариантов (по 2-5 на каждый товар, часть привязана к вариантам)
        $images = [];
        $imageId = 1;

        // Список заглушек изображений (можно заменить на реальные URL)
        $fakeImages = [
            'https://picsum.photos/id/1/800/600',
            'https://picsum.photos/id/2/800/600',
            'https://picsum.photos/id/3/800/600',
            'https://picsum.photos/id/4/800/600',
            'https://picsum.photos/id/5/800/600',
            'https://picsum.photos/id/6/800/600',
            'https://picsum.photos/id/7/800/600',
            'https://picsum.photos/id/8/800/600',
            'https://picsum.photos/id/9/800/600',
            'https://picsum.photos/id/10/800/600',
            'https://picsum.photos/id/11/800/600',
            'https://picsum.photos/id/12/800/600',
            'https://picsum.photos/id/13/800/600',
            'https://picsum.photos/id/14/800/600',
            'https://picsum.photos/id/15/800/600',
        ];

        foreach ($productIds as $productId) {
            // Общее количество изображений для этого товара (от 2 до 5)
            $totalImages = rand(2, 5);
            // Сколько из них привязано к вариантам (0 до totalImages)
            $variantImagesCount = rand(0, min($totalImages, count($allVariants)));

            for ($i = 0; $i < $totalImages; $i++) {
                $variantId = null;
                // Если ещё не все изображения для вариантов распределены, привязываем к случайному варианту этого товара
                if ($variantImagesCount > 0) {
                    // Находим варианты этого товара
                    $productVariants = array_filter($allVariants, fn($v) => $v['product_id'] == $productId);
                    if (!empty($productVariants)) {
                        $randomVariant = $productVariants[array_rand($productVariants)];
                        $variantId = $randomVariant['id'];
                        $variantImagesCount--;
                    }
                }

                $images[] = [
                    'id' => $imageId++,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'url' => $fakeImages[array_rand($fakeImages)],
                    'sort_order' => $i,
                    'is_main' => ($i == 0), // первое изображение делаем главным
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'deleted_at' => null,
                ];
            }
        }

        DB::table('product_images')->insert($images);
    }

    /**
     * Генерация опций варианта в зависимости от товара
     */
    private function generateOptions($productIndex, $variantNum)
    {
        $colors = ['Чёрный', 'Белый', 'Красный', 'Синий', 'Зелёный', 'Серый', 'Золотой'];
        $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
        $memory = ['128GB', '256GB', '512GB', '1TB'];

        // Для разных категорий разные типы опций
        switch ($productIndex % 10) {
            case 0: // Электроника: память + цвет
                return [
                    'color' => $colors[array_rand($colors)],
                    'memory' => $memory[array_rand($memory)]
                ];
            case 1: // Одежда: размер + цвет
                return [
                    'size' => $sizes[array_rand($sizes)],
                    'color' => $colors[array_rand($colors)]
                ];
            case 2: // Дом: только цвет
                return ['color' => $colors[array_rand($colors)]];
            case 3: // Спорт: размер
                return ['size' => $sizes[array_rand($sizes)]];
            default: // универсальные опции
                return [
                    'color' => $colors[array_rand($colors)]
                ];
        }
    }
}