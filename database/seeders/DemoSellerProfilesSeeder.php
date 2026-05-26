<?php

namespace Database\Seeders;

use App\Models\SellerProfile;
use Illuminate\Database\Seeder;

class DemoSellerProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            6 => [
                'shop_name' => 'ТехноМаркет Иркутск',
                'description' => 'Официальный дилер электроники, одежды и товаров для дома. Доставка по Иркутской области, гарантия производителя.',
                'inn' => '3808123456',
                'legal_address' => '664003, г. Иркутск, ул. Ленина, 14, офис 201',
                'pickup_address' => 'г. Иркутск, ул. Урицкого, 25, ПВЗ «Модный квартал»',
                'rating' => 4.85,
                'total_sales' => 0,
                'working_hours' => [
                    'mon' => '09:00-20:00',
                    'tue' => '09:00-20:00',
                    'wed' => '09:00-20:00',
                    'thu' => '09:00-20:00',
                    'fri' => '09:00-20:00',
                    'sat' => '10:00-18:00',
                    'sun' => 'выходной',
                ],
            ],
            7 => [
                'shop_name' => 'Универсам «Сибирь»',
                'description' => 'Книги, косметика, детские товары, зоотовары и мебель. Работаем с 2018 года, более 5000 довольных клиентов.',
                'inn' => '3808987654',
                'legal_address' => '664007, г. Иркутск, ул. 3 Июля, 7, стр. 2',
                'pickup_address' => 'г. Иркутск, ул. 3 Июля, 7, ПВЗ «130-й квартал»',
                'rating' => 4.72,
                'total_sales' => 0,
                'working_hours' => [
                    'mon' => '08:00-21:00',
                    'tue' => '08:00-21:00',
                    'wed' => '08:00-21:00',
                    'thu' => '08:00-21:00',
                    'fri' => '08:00-21:00',
                    'sat' => '09:00-19:00',
                    'sun' => '10:00-17:00',
                ],
            ],
        ];

        foreach ($profiles as $userId => $data) {
            SellerProfile::query()->updateOrCreate(
                ['user_id' => $userId],
                $data
            );
        }
    }
}
