<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\Category;
use App\Models\CategoryAttribute;

class CategoryAttributeSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Электроника
        |--------------------------------------------------------------------------
        */

        $electronics = Category::where('slug', 'electronics')->first();

        if ($electronics) {

            CategoryAttribute::insert([

                [
                    'category_id' => $electronics->id,
                    'name' => 'Бренд',
                    'type' => 'text',
                    'options' => null,
                    'required' => true,
                ],

                [
                    'category_id' => $electronics->id,
                    'name' => 'Модель',
                    'type' => 'text',
                    'options' => null,
                    'required' => true,
                ],

                [
                    'category_id' => $electronics->id,
                    'name' => 'Память',
                    'type' => 'select',
                    'options' => json_encode([
                        '64GB',
                        '128GB',
                        '256GB',
                        '512GB',
                        '1TB'
                    ]),
                    'required' => true,
                ],

                [
                    'category_id' => $electronics->id,
                    'name' => 'Цвет',
                    'type' => 'select',
                    'options' => json_encode([
                        'Черный',
                        'Белый',
                        'Синий',
                        'Красный'
                    ]),
                    'required' => true,
                ],

                [
                    'category_id' => $electronics->id,
                    'name' => 'Диагональ экрана',
                    'type' => 'number',
                    'options' => null,
                    'required' => false,
                ],

            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Одежда
        |--------------------------------------------------------------------------
        */

        $clothes = Category::where('slug', 'clothes')->first();

        if ($clothes) {

            CategoryAttribute::insert([

                [
                    'category_id' => $clothes->id,
                    'name' => 'Бренд',
                    'type' => 'text',
                    'options' => null,
                    'required' => true,
                ],

                [
                    'category_id' => $clothes->id,
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
                    'required' => true,
                ],

                [
                    'category_id' => $clothes->id,
                    'name' => 'Материал',
                    'type' => 'text',
                    'options' => null,
                    'required' => false,
                ],

                [
                    'category_id' => $clothes->id,
                    'name' => 'Пол',
                    'type' => 'select',
                    'options' => json_encode([
                        'Мужской',
                        'Женский',
                        'Унисекс'
                    ]),
                    'required' => true,
                ],

            ]);
        }
    } }