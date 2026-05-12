<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\CategoryAttribute;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | ROOT CATEGORIES
        |--------------------------------------------------------------------------
        */

        $electronics = Category::firstOrCreate(
            ['slug' => 'electronics'],
            ['name' => 'Электроника', 'icon' => '/img/categories/electronics.png', 'is_active' => true]
        );

        $clothing = Category::firstOrCreate(
            ['slug' => 'clothing'],
            ['name' => 'Одежда и обувь', 'icon' => '/img/categories/clothing.png', 'is_active' => true]
        );

        $home = Category::firstOrCreate(
            ['slug' => 'home'],
            ['name' => 'Дом и интерьер', 'icon' => '/img/categories/home.png', 'is_active' => true]
        );

        $sports = Category::firstOrCreate(
            ['slug' => 'sports'],
            ['name' => 'Спорт и отдых', 'icon' => '/img/categories/sports.png', 'is_active' => true]
        );

        $auto = Category::firstOrCreate(
            ['slug' => 'auto'],
            ['name' => 'Автотовары', 'icon' => '/img/categories/auto.png', 'is_active' => true]
        );

        $books = Category::firstOrCreate(
            ['slug' => 'books'],
            ['name' => 'Книги и медиа', 'icon' => '/img/categories/books.png', 'is_active' => true]
        );

        $beauty = Category::firstOrCreate(
            ['slug' => 'beauty'],
            ['name' => 'Красота и здоровье', 'icon' => '/img/categories/beauty.png', 'is_active' => true]
        );

        $kids = Category::firstOrCreate(
            ['slug' => 'kids'],
            ['name' => 'Детские товары', 'icon' => '/img/categories/kids.png', 'is_active' => true]
        );

        $pets = Category::firstOrCreate(
            ['slug' => 'pets'],
            ['name' => 'Зоотовары', 'icon' => '/img/categories/pets.png', 'is_active' => true]
        );

        $furniture = Category::firstOrCreate(
            ['slug' => 'furniture'],
            ['name' => 'Мебель', 'icon' => '/img/categories/furniture.png', 'is_active' => true]
        );

        /*
        |--------------------------------------------------------------------------
        | ELECTRONICS CHILDREN
        |--------------------------------------------------------------------------
        */

        $smartphones = Category::firstOrCreate(
            ['slug' => 'smartphones'],
            ['name' => 'Смартфоны', 'parent_id' => $electronics->id, 'is_active' => true]
        );

        $laptops = Category::firstOrCreate(
            ['slug' => 'laptops'],
            ['name' => 'Ноутбуки', 'parent_id' => $electronics->id, 'is_active' => true]
        );

        $tvs = Category::firstOrCreate(
            ['slug' => 'tvs'],
            ['name' => 'Телевизоры', 'parent_id' => $electronics->id, 'is_active' => true]
        );

        /*
        |--------------------------------------------------------------------------
        | CLOTHING CHILDREN
        |--------------------------------------------------------------------------
        */

        $mens = Category::firstOrCreate(
            ['slug' => 'mens-clothes'],
            ['name' => 'Мужская одежда', 'parent_id' => $clothing->id, 'is_active' => true]
        );

        $womens = Category::firstOrCreate(
            ['slug' => 'womens-clothes'],
            ['name' => 'Женская одежда', 'parent_id' => $clothing->id, 'is_active' => true]
        );

        $shoes = Category::firstOrCreate(
            ['slug' => 'shoes'],
            ['name' => 'Обувь', 'parent_id' => $clothing->id, 'is_active' => true]
        );

        /*
        |--------------------------------------------------------------------------
        | HOME CHILDREN
        |--------------------------------------------------------------------------
        */

        $furnitureHome = Category::firstOrCreate(
            ['slug' => 'home-furniture'],
            ['name' => 'Мебель для дома', 'parent_id' => $home->id, 'is_active' => true]
        );

        $decor = Category::firstOrCreate(
            ['slug' => 'decor'],
            ['name' => 'Декор', 'parent_id' => $home->id, 'is_active' => true]
        );

        $kitchen = Category::firstOrCreate(
            ['slug' => 'kitchen'],
            ['name' => 'Посуда и кухня', 'parent_id' => $home->id, 'is_active' => true]
        );

        /*
        |--------------------------------------------------------------------------
        | SPORTS CHILDREN
        |--------------------------------------------------------------------------
        */

        $fitness = Category::firstOrCreate(
            ['slug' => 'fitness'],
            ['name' => 'Фитнес', 'parent_id' => $sports->id, 'is_active' => true]
        );

        $tourism = Category::firstOrCreate(
            ['slug' => 'tourism'],
            ['name' => 'Туризм', 'parent_id' => $sports->id, 'is_active' => true]
        );

        /*
        |--------------------------------------------------------------------------
        | AUTO CHILDREN
        |--------------------------------------------------------------------------
        */

        $tires = Category::firstOrCreate(
            ['slug' => 'tires'],
            ['name' => 'Шины и диски', 'parent_id' => $auto->id, 'is_active' => true]
        );

        $oils = Category::firstOrCreate(
            ['slug' => 'oils'],
            ['name' => 'Масла и жидкости', 'parent_id' => $auto->id, 'is_active' => true]
        );

        /*
        |--------------------------------------------------------------------------
        | BOOKS CHILDREN
        |--------------------------------------------------------------------------
        */

        $fiction = Category::firstOrCreate(
            ['slug' => 'fiction'],
            ['name' => 'Художественная литература', 'parent_id' => $books->id, 'is_active' => true]
        );

        $education = Category::firstOrCreate(
            ['slug' => 'education'],
            ['name' => 'Учебная литература', 'parent_id' => $books->id, 'is_active' => true]
        );

        /*
        |--------------------------------------------------------------------------
        | BEAUTY CHILDREN
        |--------------------------------------------------------------------------
        */

        $cosmetics = Category::firstOrCreate(
            ['slug' => 'cosmetics'],
            ['name' => 'Косметика', 'parent_id' => $beauty->id, 'is_active' => true]
        );

        $health = Category::firstOrCreate(
            ['slug' => 'health'],
            ['name' => 'Здоровье', 'parent_id' => $beauty->id, 'is_active' => true]
        );

        /*
        |--------------------------------------------------------------------------
        | KIDS CHILDREN
        |--------------------------------------------------------------------------
        */

        $toys = Category::firstOrCreate(
            ['slug' => 'toys'],
            ['name' => 'Игрушки', 'parent_id' => $kids->id, 'is_active' => true]
        );

        $baby = Category::firstOrCreate(
            ['slug' => 'baby-products'],
            ['name' => 'Товары для новорожденных', 'parent_id' => $kids->id, 'is_active' => true]
        );

        /*
        |--------------------------------------------------------------------------
        | PETS CHILDREN
        |--------------------------------------------------------------------------
        */

        $dog = Category::firstOrCreate(
            ['slug' => 'dog-supplies'],
            ['name' => 'Для собак', 'parent_id' => $pets->id, 'is_active' => true]
        );

        $cat = Category::firstOrCreate(
            ['slug' => 'cat-supplies'],
            ['name' => 'Для кошек', 'parent_id' => $pets->id, 'is_active' => true]
        );

        /*
        |--------------------------------------------------------------------------
        | FURNITURE CHILDREN
        |--------------------------------------------------------------------------
        */

        $office = Category::firstOrCreate(
            ['slug' => 'office-furniture'],
            ['name' => 'Офисная мебель', 'parent_id' => $furniture->id, 'is_active' => true]
        );

        $bedroom = Category::firstOrCreate(
            ['slug' => 'bedroom-furniture'],
            ['name' => 'Спальня', 'parent_id' => $furniture->id, 'is_active' => true]
        );

        /*
        |--------------------------------------------------------------------------
        | ATTRIBUTES FOR EACH CATEGORY
        |--------------------------------------------------------------------------
        */

        // Смартфоны
        $this->addAttributes($smartphones->id, [
            ['name' => 'Бренд', 'type' => 'select', 'options' => ['Apple', 'Samsung', 'Xiaomi', 'Huawei', 'Google'], 'required' => true],
            ['name' => 'Память', 'type' => 'select', 'options' => ['64GB', '128GB', '256GB', '512GB', '1TB'], 'required' => true],
            ['name' => 'Цвет', 'type' => 'select', 'options' => ['Черный', 'Белый', 'Синий', 'Красный', 'Фиолетовый'], 'required' => true],
            ['name' => 'Диагональ экрана', 'type' => 'number', 'options' => null, 'required' => true],
        ]);

        // Ноутбуки
        $this->addAttributes($laptops->id, [
            ['name' => 'Бренд', 'type' => 'select', 'options' => ['Apple', 'Asus', 'Lenovo', 'HP', 'Acer', 'MSI'], 'required' => true],
            ['name' => 'Процессор', 'type' => 'select', 'options' => ['Intel i5', 'Intel i7', 'Intel i9', 'Ryzen 5', 'Ryzen 7'], 'required' => true],
            ['name' => 'Оперативная память', 'type' => 'select', 'options' => ['8GB', '16GB', '32GB', '64GB'], 'required' => true],
        ]);

        // Мужская одежда
        $this->addAttributes($mens->id, [
            ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true],
            ['name' => 'Размер', 'type' => 'select', 'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'], 'required' => true],
            ['name' => 'Цвет', 'type' => 'select', 'options' => ['Черный', 'Белый', 'Серый', 'Синий'], 'required' => true],
            ['name' => 'Материал', 'type' => 'text', 'options' => null, 'required' => true],
        ]);

        // Женская одежда
        $this->addAttributes($womens->id, [
            ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true],
            ['name' => 'Размер', 'type' => 'select', 'options' => ['XS', 'S', 'M', 'L', 'XL'], 'required' => true],
            ['name' => 'Цвет', 'type' => 'select', 'options' => ['Черный', 'Белый', 'Розовый', 'Красный'], 'required' => true],
        ]);

        // Обувь
        $this->addAttributes($shoes->id, [
            ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true],
            ['name' => 'Размер', 'type' => 'select', 'options' => ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45'], 'required' => true],
            ['name' => 'Цвет', 'type' => 'select', 'options' => ['Черный', 'Белый', 'Коричневый', 'Серый'], 'required' => true],
        ]);

        // Мебель для дома
        $this->addAttributes($furnitureHome->id, [
            ['name' => 'Материал', 'type' => 'select', 'options' => ['ДСП', 'МДФ', 'Массив', 'Пластик', 'Металл'], 'required' => true],
            ['name' => 'Цвет', 'type' => 'select', 'options' => ['Белый', 'Дуб', 'Орех', 'Венге', 'Серый'], 'required' => true],
        ]);

        // Декор
        $this->addAttributes($decor->id, [
            ['name' => 'Материал', 'type' => 'select', 'options' => ['Керамика', 'Стекло', 'Дерево', 'Металл', 'Пластик'], 'required' => true],
            ['name' => 'Стиль', 'type' => 'select', 'options' => ['Современный', 'Классический', 'Лофт', 'Минимализм'], 'required' => true],
        ]);

        // Фитнес
        $this->addAttributes($fitness->id, [
            ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true],
            ['name' => 'Тип', 'type' => 'select', 'options' => ['Беговая дорожка', 'Велотренажер', 'Эллипс', 'Силовой тренажер'], 'required' => true],
        ]);

        // Шины
        $this->addAttributes($tires->id, [
            ['name' => 'Сезонность', 'type' => 'select', 'options' => ['Летние', 'Зимние', 'Всесезонные'], 'required' => true],
            ['name' => 'Диаметр', 'type' => 'select', 'options' => ['R13', 'R14', 'R15', 'R16', 'R17', 'R18'], 'required' => true],
        ]);

        // Игрушки
        $this->addAttributes($toys->id, [
            ['name' => 'Возраст', 'type' => 'select', 'options' => ['0+', '1+', '3+', '5+', '7+', '12+'], 'required' => true],
            ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true],
        ]);

        // Для собак
        $this->addAttributes($dog->id, [
            ['name' => 'Вид', 'type' => 'select', 'options' => ['Корм', 'Игрушки', 'Аксессуары', 'Косметика'], 'required' => true],
            ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true],
        ]);

        // Офисная мебель
        $this->addAttributes($office->id, [
            ['name' => 'Материал', 'type' => 'select', 'options' => ['ДСП', 'МДФ', 'Металл', 'Пластик'], 'required' => true],
            ['name' => 'Цвет', 'type' => 'select', 'options' => ['Белый', 'Дуб', 'Венге', 'Серый', 'Черный'], 'required' => true],
        ]);
    }

   private function addAttributes($categoryId, $attributes)
{
    foreach ($attributes as $attr) {
        CategoryAttribute::firstOrCreate(
            [
                'category_id' => $categoryId,
                'name' => $attr['name'],
            ],
            [
                'type' => $attr['type'],
                'options' => is_array($attr['options']) ? json_encode($attr['options']) : $attr['options'],
                'required' => $attr['required'],
            ]
        );
    }
}
}