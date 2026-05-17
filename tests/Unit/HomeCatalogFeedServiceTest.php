<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\HomeCatalogFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HomeCatalogFeedServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_orders_new_then_popular_then_random_without_duplicates(): void
    {
        $seller = User::query()->create([
            'name' => 'Seller',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'seller',
        ]);

        $category = Category::query()->create([
            'name' => 'Cat',
            'slug' => 'cat-'.fake()->unique()->bothify('####'),
        ]);

        $old = $this->makeProduct($seller->id, $category->id, [
            'title' => 'Old',
            'sales_count' => 100,
            'created_at' => '2024-01-01 10:00:00',
            'updated_at' => '2024-01-01 10:00:00',
        ]);

        $popular = $this->makeProduct($seller->id, $category->id, [
            'title' => 'Popular',
            'sales_count' => 50,
            'created_at' => '2024-06-01 10:00:00',
            'updated_at' => '2024-06-01 10:00:00',
        ]);

        $newest = $this->makeProduct($seller->id, $category->id, [
            'title' => 'Newest',
            'sales_count' => 0,
            'created_at' => '2026-05-01 10:00:00',
            'updated_at' => '2026-05-01 10:00:00',
        ]);

        $filler = $this->makeProduct($seller->id, $category->id, [
            'title' => 'Filler',
            'sales_count' => 1,
            'created_at' => '2025-01-01 10:00:00',
            'updated_at' => '2025-01-01 10:00:00',
        ]);

        config([
            'marketplace.home_feed.limit' => 4,
            'marketplace.home_feed.new_count' => 1,
            'marketplace.home_feed.popular_count' => 1,
        ]);

        $feed = app(HomeCatalogFeedService::class)->build();
        $ids = $feed->pluck('id')->all();

        $this->assertCount(4, $ids);
        $this->assertSame($newest->id, $ids[0]);
        $this->assertSame($old->id, $ids[1], 'Highest sales_count among remaining should be second');
        $this->assertCount(4, array_unique($ids));
        $this->assertContains($filler->id, $ids);
        $this->assertContains($popular->id, $ids);
    }

    private function makeProduct(int $sellerId, int $categoryId, array $overrides): Product
    {
        $timestamps = array_intersect_key($overrides, array_flip(['created_at', 'updated_at']));
        $attributes = array_diff_key($overrides, $timestamps);

        $product = Product::query()->create(array_merge([
            'seller_id' => $sellerId,
            'category_id' => $categoryId,
            'title' => 'Product',
            'min_price' => 100,
            'status' => 'approved',
            'is_on_action' => true,
            'views_count' => 0,
            'sales_count' => 0,
        ], $attributes));

        if ($timestamps !== []) {
            Product::query()->whereKey($product->id)->update($timestamps);
            $product->refresh();
        }

        return $product;
    }
}
