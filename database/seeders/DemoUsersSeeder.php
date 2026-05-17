<?php

namespace Database\Seeders;

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
                'phone' => '79001000002',
            ],
            [
                'id' => 3,
                'email' => 'mod1@gmail.com',
                'password' => Hash::make('mod1'),
                'name' => 'Анна',
                'last_name' => 'Модерова',
                'role' => 'moderator',
                'phone' => '79001000003',
            ],
            [
                'id' => 4,
                'email' => 'mod2@gmail.com',
                'password' => Hash::make('mod2'),
                'name' => 'Игорь',
                'last_name' => 'Контролев',
                'role' => 'moderator',
                'phone' => '79001000004',
            ],
            [
                'id' => 5,
                'email' => 'mod3@gmail.com',
                'password' => Hash::make('mod3'),
                'name' => 'Елена',
                'last_name' => 'Проверова',
                'role' => 'moderator',
                'phone' => '79001000005',
            ],
            [
                'id' => 6,
                'email' => 'seller1@gmail.com',
                'password' => Hash::make('seller1'),
                'name' => 'Михаил',
                'last_name' => 'Торговцев',
                'role' => 'seller',
                'phone' => '79001000006',
            ],
            [
                'id' => 7,
                'email' => 'seller2@gmail.com',
                'password' => Hash::make('seller2'),
                'name' => 'Ольга',
                'last_name' => 'Маркетова',
                'role' => 'seller',
                'phone' => '79001000007',
            ],
            [
                'id' => 8,
                'email' => 'buyer1@gmail.com',
                'password' => Hash::make('buyer1'),
                'name' => 'Дмитрий',
                'last_name' => 'Покупателев',
                'role' => 'user',
                'phone' => '79001000008',
            ],
            [
                'id' => 9,
                'email' => 'buyer2@gmail.com',
                'password' => Hash::make('buyer2'),
                'name' => 'Светлана',
                'last_name' => 'Заказова',
                'role' => 'user',
                'phone' => '79001000009',
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
    }
}
