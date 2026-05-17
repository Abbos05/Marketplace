<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CatalogFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogTextSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_product_by_title_with_variant_suffix_in_query(): void
    {
        $product = $this->createListedProduct(['title' => 'Одежда для детей']);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'options' => ['Вариант' => 'Стандарт'],
            'price' => 500,
            'stock' => 5,
            'is_active' => true,
        ]);

        $response = $this->get('/?search='.urlencode('Одежда для детей — Стандарт'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Home')
            ->has('mysqlNftsData', 1)
            ->where('mysqlNftsData.0.id', $product->id)
        );
    }

    public function test_nonsense_search_returns_no_products(): void
    {
        $this->createListedProduct(['title' => 'Одежда для детей']);
        $this->createListedProduct(['title' => 'Смартфон Samsung']);

        $response = $this->get('/?search='.urlencode('хахахаыыыzzzqqq'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('mysqlNftsData', [])
            ->where('total', 0)
        );
    }

    public function test_search_finds_product_by_title_and_variant_color(): void
    {
        $product = $this->createListedProduct(['title' => 'ТВ и медиа']);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'options' => ['Цвет' => 'Чёрный'],
            'price' => 15000,
            'stock' => 3,
            'is_active' => true,
        ]);

        $request = Request::create('/', 'GET', ['search' => 'ТВ и медиа Черный']);
        $result = app(CatalogFilterService::class)->process(
            $request,
            fn () => Product::forCatalogPresentation()->visibleInCatalog(),
            ['search_fields' => ['title', 'short_description']],
        );
        $this->assertCount(1, $result['products'], 'Service search should match title + color');

        $response = $this->get('/?search='.urlencode('ТВ и медиа Черный'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Home')
            ->has('mysqlNftsData', 1)
            ->where('mysqlNftsData.0.id', $product->id)
        );
    }

    public function test_search_finds_product_by_card_style_title_with_middot_specs(): void
    {
        $product = $this->createListedProduct(['title' => 'ТВ и медиа']);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'options' => ['Память' => '512GB', 'Цвет' => 'Чёрный'],
            'price' => 15000,
            'stock' => 3,
            'is_active' => true,
        ]);

        foreach ([
            'ТВ и медиа · 512GB · Черный',
            'ТВ и медиа 512GB Черный',
            'ТВ и медиа · Черный',
            'ТВ и медиа · 512GB',
        ] as $search) {
            $response = $this->get('/?search='.urlencode($search));
            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('mysqlNftsData', 1)
                ->where('mysqlNftsData.0.id', $product->id)
            );
        }
    }

    public function test_card_style_search_does_not_match_unrelated_products_with_same_memory(): void
    {
        $target = $this->createListedProduct(['title' => 'Периферия и аксессуары']);
        ProductVariant::query()->create([
            'product_id' => $target->id,
            'options' => ['Память' => '128GB', 'Цвет' => 'Белый'],
            'price' => 5000,
            'stock' => 2,
            'is_active' => true,
        ]);

        $other = $this->createListedProduct(['title' => 'USB-хаб универсальный']);
        ProductVariant::query()->create([
            'product_id' => $other->id,
            'options' => ['Память' => '128GB', 'Цвет' => 'Чёрный'],
            'price' => 900,
            'stock' => 5,
            'is_active' => true,
        ]);

        $result = app(CatalogFilterService::class)->process(
            Request::create('/', 'GET', ['search' => 'Периферия и аксессуары · 128GB · Белый']),
            fn () => Product::forCatalogPresentation()->visibleInCatalog(),
            ['search_fields' => ['title', 'short_description']],
        );

        $this->assertCount(1, $result['products']);
        $this->assertSame($target->id, $result['products']->first()->id);
    }

    public function test_search_finds_product_by_full_realistic_title(): void
    {
        $product = $this->createListedProduct(['title' => 'Maybelline Lash Sensational Sky High']);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'options' => ['Объём' => '30 мл'],
            'price' => 890,
            'stock' => 10,
            'is_active' => true,
        ]);

        foreach ([
            'Maybelline Lash Sensational Sky High',
            'Maybelline Lash Sensational Sky High 30 мл',
            'Maybelline Lash Sensational Sky High · 30 мл',
            'Lash Sensational',
        ] as $search) {
            $response = $this->get('/?search='.urlencode($search));
            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('mysqlNftsData', 1)
                ->where('mysqlNftsData.0.id', $product->id)
            );
        }
    }

    public function test_search_finds_product_by_variant_segment(): void
    {
        $product = $this->createListedProduct(['title' => 'Куртка зимняя']);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'options' => ['Размер' => 'Стандарт'],
            'price' => 900,
            'stock' => 2,
            'is_active' => true,
        ]);

        $response = $this->get('/?search='.urlencode('Куртка — Стандарт'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Home')
            ->has('mysqlNftsData', 1)
            ->where('mysqlNftsData.0.id', $product->id)
        );
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
