<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\Category;
use App\Models\CategoryAttribute;

class MarketplaceCategorySeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | ROOT CATEGORIES
        |--------------------------------------------------------------------------
        */

        $electronics = Category::create([
            'name' => 'Электроника',
            'slug' => 'electronics',
            'is_active' => true
        ]);

        $clothes = Category::create([
            'name' => 'Одежда',
            'slug' => 'clothes',
            'is_active' => true
        ]);

        /*
        |--------------------------------------------------------------------------
        | ELECTRONICS CHILDREN
        |--------------------------------------------------------------------------
        */

        $smartphones = Category::create([
            'name' => 'Смартфоны',
            'slug' => 'smartphones',
            'parent_id' => $electronics->id,
            'is_active' => true
        ]);

        $laptops = Category::create([
            'name' => 'Ноутбуки',
            'slug' => 'laptops',
            'parent_id' => $electronics->id,
            'is_active' => true
        ]);

        $fridges = Category::create([
            'name' => 'Холодильники',
            'slug' => 'fridges',
            'parent_id' => $electronics->id,
            'is_active' => true
        ]);

        $tvs = Category::create([
            'name' => 'Телевизоры',
            'slug' => 'tvs',
            'parent_id' => $electronics->id,
            'is_active' => true
        ]);

        /*
        |--------------------------------------------------------------------------
        | CLOTHES CHILDREN
        |--------------------------------------------------------------------------
        */

        $mens = Category::create([
            'name' => 'Мужская одежда',
            'slug' => 'mens-clothes',
            'parent_id' => $clothes->id,
            'is_active' => true
        ]);

        $womens = Category::create([
            'name' => 'Женская одежда',
            'slug' => 'womens-clothes',
            'parent_id' => $clothes->id,
            'is_active' => true
        ]);

        /*
        |--------------------------------------------------------------------------
        | SMARTPHONES ATTRIBUTES
        |--------------------------------------------------------------------------
        */

        CategoryAttribute::insert([

            [
                'category_id' => $smartphones->id,
                'name' => 'Бренд',
                'type' => 'select',
                'options' => json_encode([
                    'Apple',
                    'Samsung',
                    'Xiaomi',
                    'Huawei',
                    'Google'
                ]),
                'required' => true
            ],

            [
                'category_id' => $smartphones->id,
                'name' => 'Память',
                'type' => 'select',
                'options' => json_encode([
                    '64GB',
                    '128GB',
                    '256GB',
                    '512GB',
                    '1TB'
                ]),
                'required' => true
            ],

            [
                'category_id' => $smartphones->id,
                'name' => 'Цвет',
                'type' => 'select',
                'options' => json_encode([
                    'Черный',
                    'Белый',
                    'Синий',
                    'Красный',
                    'Titanium'
                ]),
                'required' => true
            ],

            [
                'category_id' => $smartphones->id,
                'name' => 'Диагональ экрана',
                'type' => 'number',
                'options' => null,
                'required' => false
            ],

            [
                'category_id' => $smartphones->id,
                'name' => 'NFC',
                'type' => 'select',
                'options' => json_encode([
                    'Да',
                    'Нет'
                ]),
                'required' => false
            ],

        ]);

        /*
        |--------------------------------------------------------------------------
        | LAPTOPS ATTRIBUTES
        |--------------------------------------------------------------------------
        */

        CategoryAttribute::insert([

            [
                'category_id' => $laptops->id,
                'name' => 'Бренд',
                'type' => 'select',
                'options' => json_encode([
                    'Apple',
                    'Asus',
                    'Lenovo',
                    'HP',
                    'Acer',
                    'MSI'
                ]),
                'required' => true
            ],

            [
                'category_id' => $laptops->id,
                'name' => 'Процессор',
                'type' => 'select',
                'options' => json_encode([
                    'Intel i5',
                    'Intel i7',
                    'Intel i9',
                    'Ryzen 5',
                    'Ryzen 7'
                ]),
                'required' => true
            ],

            [
                'category_id' => $laptops->id,
                'name' => 'Оперативная память',
                'type' => 'select',
                'options' => json_encode([
                    '8GB',
                    '16GB',
                    '32GB',
                    '64GB'
                ]),
                'required' => true
            ],

            [
                'category_id' => $laptops->id,
                'name' => 'Цвет',
                'type' => 'select',
                'options' => json_encode([
                    'Черный',
                    'Серый',
                    'Белый'
                ]),
                'required' => false
            ],

        ]);

        /*
        |--------------------------------------------------------------------------
        | FRIDGES ATTRIBUTES
        |--------------------------------------------------------------------------
        */

        CategoryAttribute::insert([

            [
                'category_id' => $fridges->id,
                'name' => 'Бренд',
                'type' => 'select',
                'options' => json_encode([
                    'LG',
                    'Samsung',
                    'Bosch',
                    'Haier',
                    'Beko'
                ]),
                'required' => true
            ],

            [
                'category_id' => $fridges->id,
                'name' => 'Объем',
                'type' => 'number',
                'options' => null,
                'required' => true
            ],

            [
                'category_id' => $fridges->id,
                'name' => 'No Frost',
                'type' => 'select',
                'options' => json_encode([
                    'Да',
                    'Нет'
                ]),
                'required' => true
            ],

            [
                'category_id' => $fridges->id,
                'name' => 'Количество камер',
                'type' => 'select',
                'options' => json_encode([
                    '1',
                    '2',
                    '3'
                ]),
                'required' => true
            ],

        ]);

        /*
        |--------------------------------------------------------------------------
        | TV ATTRIBUTES
        |--------------------------------------------------------------------------
        */

        CategoryAttribute::insert([

            [
                'category_id' => $tvs->id,
                'name' => 'Бренд',
                'type' => 'select',
                'options' => json_encode([
                    'Samsung',
                    'LG',
                    'Sony',
                    'Xiaomi'
                ]),
                'required' => true
            ],

            [
                'category_id' => $tvs->id,
                'name' => 'Диагональ',
                'type' => 'select',
                'options' => json_encode([
                    '32',
                    '43',
                    '50',
                    '55',
                    '65',
                    '75'
                ]),
                'required' => true
            ],

            [
                'category_id' => $tvs->id,
                'name' => 'Smart TV',
                'type' => 'select',
                'options' => json_encode([
                    'Да',
                    'Нет'
                ]),
                'required' => true
            ],

        ]);

        /*
        |--------------------------------------------------------------------------
        | MEN CLOTHES ATTRIBUTES
        |--------------------------------------------------------------------------
        */

        CategoryAttribute::insert([

            [
                'category_id' => $mens->id,
                'name' => 'Бренд',
                'type' => 'text',
                'options' => null,
                'required' => true
            ],

            [
                'category_id' => $mens->id,
                'name' => 'Размер',
                'type' => 'select',
                'options' => json_encode([
                    'XS',
                    'S',
                    'M',
                    'L',
                    'XL',
                    'XXL'
                ]),
                'required' => true
            ],

            [
                'category_id' => $mens->id,
                'name' => 'Цвет',
                'type' => 'select',
                'options' => json_encode([
                    'Черный',
                    'Белый',
                    'Серый',
                    'Синий'
                ]),
                'required' => true
            ],

            [
                'category_id' => $mens->id,
                'name' => 'Материал',
                'type' => 'text',
                'options' => null,
                'required' => false
            ],

        ]);

        /*
        |--------------------------------------------------------------------------
        | WOMEN CLOTHES ATTRIBUTES
        |--------------------------------------------------------------------------
        */

        CategoryAttribute::insert([

            [
                'category_id' => $womens->id,
                'name' => 'Бренд',
                'type' => 'text',
                'options' => null,
                'required' => true
            ],

            [
                'category_id' => $womens->id,
                'name' => 'Размер',
                'type' => 'select',
                'options' => json_encode([
                    'XS',
                    'S',
                    'M',
                    'L',
                    'XL'
                ]),
                'required' => true
            ],

            [
                'category_id' => $womens->id,
                'name' => 'Цвет',
                'type' => 'select',
                'options' => json_encode([
                    'Черный',
                    'Белый',
                    'Розовый',
                    'Красный'
                ]),
                'required' => true
            ],

            [
                'category_id' => $womens->id,
                'name' => 'Материал',
                'type' => 'text',
                'options' => null,
                'required' => false
            ],

        ]);
    }
}