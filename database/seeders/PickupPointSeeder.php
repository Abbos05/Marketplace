<?php

namespace Database\Seeders;

use App\Models\PickupPoint;
use App\Models\Region;
use Illuminate\Database\Seeder;

class PickupPointSeeder extends Seeder
{
    /**
     * Пункты выдачи по городам Иркутской области (привязка к таблице regions).
     */
    public function run(): void
    {
        $byName = Region::query()->pluck('id', 'name');

        $rows = [
            'Иркутск' => [
                ['ПВЗ «Центральный»', 'ул. Ленина, 1', 10],
                ['ПВЗ «Модный квартал»', 'ул. Урицкого, 25', 20],
                ['ПВЗ «130-й квартал»', 'ул. 3 Июля, 7', 30],
                ['ПВЗ «Радужный»', 'мкр. Радужный, стр. 12', 40],
            ],
            'Ангарск' => [
                ['ПВЗ «Ангарск-Центр»', 'пр-т Ленина, 42', 10],
                ['ПВЗ «Китай-город»', 'ул. Ленина, 105', 20],
                ['ПВЗ «ЗАТЭ»', 'ул. Чайковского, 18', 30],
            ],
            'Братск' => [
                ['ПВЗ «Братск-Север»', 'б-р Пионерский, 4', 10],
                ['ПВЗ «Энергетик»', 'ул. Энергетиков, 33', 20],
                ['ПВЗ «Центральный»', 'ул. Ленина, 56', 30],
            ],
            'Шелехов' => [
                ['ПВЗ «Шелехов»', 'ул. 5-й квартал, 14', 10],
                ['ПВЗ «АЗС Южная»', 'ул. Транспортная, 2', 20],
            ],
            'Усолье-Сибирское' => [
                ['ПВЗ «Усолье»', 'ул. Ленина, 88', 10],
                ['ПВЗ «Сибирь»', 'ул. Красноармейская, 15', 20],
            ],
        ];

        foreach ($rows as $city => $points) {
            $regionId = $byName[$city] ?? null;
            if ($regionId === null) {
                continue;
            }
            foreach ($points as [$title, $address, $sort]) {
                PickupPoint::query()->updateOrCreate(
                    [
                        'title' => $title,
                        'address' => $address,
                        'region_id' => $regionId,
                    ],
                    [
                        'is_active' => true,
                        'sort_order' => $sort,
                    ]
                );
            }
        }
    }
}
