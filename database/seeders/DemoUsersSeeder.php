<?php

namespace Database\Seeders;

use App\Models\PickupPoint;
use App\Models\PickupPointStaff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Демо-аккаунты (пароль = часть email до @).
 *
 * admin@gmail.com / admin          — главный админ (id 1, из миграции)
 * admin2@gmail.com / admin2
 * mod1@gmail.com / mod1 … mod3@gmail.com / mod3
 * seller1@gmail.com / seller1, seller2@gmail.com / seller2
 * buyer1@gmail.com / buyer1, buyer2@gmail.com / buyer2
 * pvz1@gmail.com / pvz1 … pvz5@gmail.com / pvz5
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            [
                'id' => 2,
                'email' => 'admin2@gmail.com',
                'password' => Hash::make('admin2'),
                'name' => 'Админ',
                'last_name' => 'Запасной',
                'role' => 'admin',
                'phone' => '79648111105',
            ],
            [
                'id' => 3,
                'email' => 'mod1@gmail.com',
                'password' => Hash::make('mod1'),
                'name' => 'Анна',
                'last_name' => 'Модерова',
                'role' => 'moderator',
                'phone' => '79000000003',
            ],
            [
                'id' => 4,
                'email' => 'mod2@gmail.com',
                'password' => Hash::make('mod2'),
                'name' => 'Игорь',
                'last_name' => 'Контролев',
                'role' => 'moderator',
                'phone' => '79000000004',
            ],
            [
                'id' => 5,
                'email' => 'mod3@gmail.com',
                'password' => Hash::make('mod3'),
                'name' => 'Елена',
                'last_name' => 'Проверова',
                'role' => 'moderator',
                'phone' => '79000000005',
            ],
            [
                'id' => 6,
                'email' => 'seller1@gmail.com',
                'password' => Hash::make('seller1'),
                'name' => 'Михаил',
                'last_name' => 'Торговцев',
                'role' => 'seller',
                'phone' => '79000000006',
            ],
            [
                'id' => 7,
                'email' => 'seller2@gmail.com',
                'password' => Hash::make('seller2'),
                'name' => 'Ольга',
                'last_name' => 'Маркетова',
                'role' => 'seller',
                'phone' => '79000000007',
            ],
            [
                'id' => 8,
                'email' => 'buyer1@gmail.com',
                'password' => Hash::make('buyer1'),
                'name' => 'Дмитрий',
                'last_name' => 'Покупателев',
                'role' => 'user',
                'phone' => '79000000008',
            ],
            [
                'id' => 9,
                'email' => 'buyer2@gmail.com',
                'password' => Hash::make('buyer2'),
                'name' => 'Светлана',
                'last_name' => 'Заказова',
                'role' => 'user',
                'phone' => '79000000009',
            ],
            [
                'id' => 10,
                'email' => 'pvz1@gmail.com',
                'password' => Hash::make('pvz1'),
                'name' => 'Павел',
                'last_name' => 'ПВЗ-1',
                'role' => 'pvz',
                'phone' => '79000000010',
            ],
            [
                'id' => 11,
                'email' => 'pvz2@gmail.com',
                'password' => Hash::make('pvz2'),
                'name' => 'Ирина',
                'last_name' => 'ПВЗ-2',
                'role' => 'pvz',
                'phone' => '79000000011',
            ],
            [
                'id' => 12,
                'email' => 'pvz3@gmail.com',
                'password' => Hash::make('pvz3'),
                'name' => 'Роман',
                'last_name' => 'ПВЗ-3',
                'role' => 'pvz',
                'phone' => '79000000012',
            ],
            [
                'id' => 13,
                'email' => 'pvz4@gmail.com',
                'password' => Hash::make('pvz4'),
                'name' => 'Мария',
                'last_name' => 'ПВЗ-4',
                'role' => 'pvz',
                'phone' => '79000000013',
            ],
            [
                'id' => 14,
                'email' => 'pvz5@gmail.com',
                'password' => Hash::make('pvz5'),
                'name' => 'Андрей',
                'last_name' => 'ПВЗ-5',
                'role' => 'pvz',
                'phone' => '79000000014',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('users')->updateOrInsert(
                ['id' => $row['id']],
                array_merge($row, [
                    'avatar' => null,
                    'is_active' => true,
                    'is_blocked' => false,
                    'newPassw' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        $pickupIds = PickupPoint::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(5)
            ->pluck('id')
            ->values();

        if ($pickupIds->isEmpty()) {
            return;
        }

        // Покупатели из демо-набора получают выбранный ПВЗ по умолчанию.
        DB::table('users')->where('id', 8)->update(['default_pickup_point_id' => $pickupIds->get(0)]);
        DB::table('users')->where('id', 9)->update(['default_pickup_point_id' => $pickupIds->get(1, $pickupIds->get(0))]);

        // 5 сотрудников ПВЗ: каждому назначаем свой пункт выдачи (один approved на точку).
        for ($i = 0; $i < 5; $i++) {
            $userId = 10 + $i;
            $pickupId = $pickupIds->get($i, $pickupIds->last());

            DB::table('users')->where('id', $userId)->update([
                'default_pickup_point_id' => $pickupId,
            ]);

            PickupPointStaff::query()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'pickup_point_id' => $pickupId,
                    'type' => PickupPointStaff::TYPE_JOIN,
                    'status' => PickupPointStaff::STATUS_APPROVED,
                    'contact_name' => 'Сотрудник ПВЗ #'.($i + 1),
                    'contact_phone' => '7900000000'.($i + 1),
                    'consent_accepted_at' => $now,
                    'reviewed_by' => 1,
                    'reviewed_at' => $now,
                    'reject_reason' => null,
                ]
            );
        }
    }
}
