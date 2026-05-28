<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\ProductViewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductViewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_view_for_variant_and_product_once_per_session(): void
    {
        $seller = User::query()->create([
            'name' => 'Seller',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'seller',
        ]);

        SellerProfile::query()->create([
            'user_id' => $seller->id,
            'shop_name' => 'Shop',
            'inn' => '1234567890',
            'legal_address' => 'Address',
            'pickup_address' => 'Pickup address',
        ]);

        $buyer = User::query()->create([
            'name' => 'Buyer',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        $category = Category::query()->create([
            'name' => 'Cat',
            'slug' => 'cat-'.fake()->unique()->bothify('####'),
        ]);

        $product = Product::query()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Phone',
            'min_price' => 100,
            'status' => 'approved',
            'is_on_action' => true,
            'sales_count' => 0,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => '00000001',
            'options' => ['Цвет' => 'Чёрный'],
            'price' => 100,
            'stock' => 5,
            'is_active' => true,
            'views_count' => 0,
        ]);

        $service = app(ProductViewService::class);

        $service->record($variant, $buyer);
        $service->record($variant, $buyer);

        $variant->refresh();
        $product->refresh();

        $this->assertSame(1, $variant->views_count);
    }
}
