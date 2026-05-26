<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            CommissionRatesSeeder::class,
            PickupPointSeeder::class,
            DemoUsersSeeder::class,
            DemoSellerProfilesSeeder::class,
            DemoCatalogSeeder::class,
            DemoPromotionSeeder::class,
            HomeSlidesSeeder::class,
            DemoCommerceSeeder::class,
        ]);
    }
}
