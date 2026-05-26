<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ArticleNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ArticleSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_sku_is_assigned_on_create(): void
    {
        $product = $this->createListedProduct();
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'options' => ['size' => 'M'],
            'price' => 500,
            'stock' => 5,
            'is_active' => true,
        ]);

        $variant->refresh();
        $expected = app(ArticleNumberService::class)->format($variant->id);

        $this->assertSame($expected, $variant->sku);
        $this->assertStringStartsWith('000', $variant->sku);
    }

    public function test_article_route_redirects_to_product_with_variant(): void
    {
        $product = $this->createListedProduct();
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'options' => ['color' => 'Red'],
            'price' => 900,
            'stock' => 3,
            'is_active' => true,
        ]);
        $variant->refresh();

        $this->get('/article/'.$variant->sku)
            ->assertRedirect(route('product.show', [
                'product' => $product->id,
                'variant' => $variant->id,
            ]));
    }

    public function test_home_search_by_article_redirects_to_specific_variant(): void
    {
        $product = $this->createListedProduct(['title' => 'Unique Zephyr Lamp']);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'options' => ['size' => 'L'],
            'price' => 1200,
            'stock' => 2,
            'is_active' => true,
        ]);
        $variant->refresh();

        $this->get('/?search='.urlencode($variant->sku))
            ->assertRedirect(route('product.show', [
                'product' => $product->id,
                'variant' => $variant->id,
            ]));
    }

    private function createListedProduct(array $overrides = []): Product
    {
        $seller = User::query()->create([
            'name' => 'Seller',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'seller',
        ]);

        $category = Category::query()->create([
            'name' => 'Test category',
            'slug' => 'test-category-'.fake()->unique()->bothify('####'),
        ]);

        return Product::query()->create(array_merge([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Test product',
            'min_price' => 100,
            'status' => 'approved',
            'is_on_action' => true,
        ], $overrides));
    }
}
