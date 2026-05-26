<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SearchSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestions_require_at_least_two_characters(): void
    {
        $this->getJson('/api/catalog/search-suggestions?q=s')
            ->assertOk()
            ->assertJsonPath('suggestions', []);
    }

    public function test_suggestions_return_only_product_titles(): void
    {
        $product = $this->createListedProduct(['title' => 'Смартфон Samsung Galaxy S24']);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'options' => ['Память' => '256GB', 'Цвет' => 'Чёрный'],
            'price' => 75000,
            'stock' => 4,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/catalog/search-suggestions?q=sam');

        $response->assertOk();
        $response->assertJsonStructure([
            'query',
            'suggestions' => [
                '*' => ['type', 'text'],
            ],
        ]);

        $rows = collect($response->json('suggestions'));
        $this->assertTrue($rows->every(fn (array $row) => ($row['type'] ?? '') === 'query'));
        $this->assertContains('Смартфон Samsung Galaxy S24', $rows->pluck('text')->all());
        $this->assertTrue($rows->every(fn (array $row) => ! array_key_exists('product_id', $row)));
    }

    public function test_suggestions_include_title_starting_with_query(): void
    {
        $this->createListedProduct(['title' => 'Чехол для Samsung']);
        $this->createListedProduct(['title' => 'Samsung Galaxy A55']);

        $response = $this->getJson('/api/catalog/search-suggestions?q=Samsung');

        $response->assertOk();
        $texts = collect($response->json('suggestions'))->pluck('text')->all();
        $this->assertContains('Samsung Galaxy A55', $texts);
        $this->assertStringStartsWith('Samsung', (string) ($texts[0] ?? ''));
    }

    private function createListedProduct(array $overrides = []): Product
    {
        $seller = User::query()->create([
            'name' => 'Seller',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'seller',
        ]);

        SellerProfile::query()->create([
            'user_id' => $seller->id,
            'shop_name' => 'Test shop',
            'pickup_address' => 'Test address',
        ]);

        $category = Category::query()->create([
            'name' => 'Test category',
            'slug' => 'test-category-'.fake()->unique()->bothify('####'),
            'is_active' => true,
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
