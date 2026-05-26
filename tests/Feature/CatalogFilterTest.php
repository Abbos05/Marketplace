<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\CatalogFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_search_sorts_by_price_descending(): void
    {
        $cheap = $this->createListedProduct(['title' => 'Дешёвый товар Alpha', 'min_price' => 100]);
        $expensive = $this->createListedProduct(['title' => 'Дорогой товар Beta', 'min_price' => 900]);

        $response = $this->get('/?search='.urlencode('товар').'&sort=expensive');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Home')
            ->has('mysqlProductsData', 2)
            ->where('mysqlProductsData.0.id', $expensive->id)
            ->where('mysqlProductsData.1.id', $cheap->id)
        );
    }

    public function test_category_sorts_by_price_descending(): void
    {
        $category = Category::query()->create([
            'name' => 'Электроника',
            'slug' => 'electronics-'.fake()->unique()->bothify('####'),
        ]);

        $cheap = $this->createListedProduct([
            'category_id' => $category->id,
            'title' => 'Бюджетный',
            'min_price' => 50,
        ]);
        $expensive = $this->createListedProduct([
            'category_id' => $category->id,
            'title' => 'Премиум',
            'min_price' => 5000,
        ]);

        $this->createListedProduct([
            'title' => 'Другая категория',
            'min_price' => 9999,
        ]);

        $response = $this->get('/category/'.$category->id.'?sort=expensive');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('CategoryPage')
            ->has('products', 2)
            ->where('products.0.id', $expensive->id)
            ->where('products.1.id', $cheap->id)
        );
    }

    public function test_seller_shop_sorts_by_price_descending(): void
    {
        $seller = $this->createSeller();
        $cheap = $this->createListedProduct([
            'seller_id' => $seller->id,
            'title' => 'Дешёвый от продавца',
            'min_price' => 200,
        ]);
        $expensive = $this->createListedProduct([
            'seller_id' => $seller->id,
            'title' => 'Дорогой от продавца',
            'min_price' => 2000,
        ]);

        $response = $this->get('/sellerProfile/'.$seller->id.'?sort=expensive');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('SellerProfile/Seller')
            ->has('products', 2)
            ->where('products.0.id', $expensive->id)
            ->where('products.1.id', $cheap->id)
        );
    }

    public function test_rating_min_filter_on_category(): void
    {
        $category = Category::query()->create([
            'name' => 'Отзывы',
            'slug' => 'reviews-cat-'.fake()->unique()->bothify('####'),
        ]);

        $buyer = User::query()->create([
            'name' => 'Buyer',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        $highRated = $this->createListedProduct([
            'category_id' => $category->id,
            'title' => 'Отличный товар',
            'min_price' => 300,
        ]);
        $lowRated = $this->createListedProduct([
            'category_id' => $category->id,
            'title' => 'Средний товар',
            'min_price' => 400,
        ]);

        Review::query()->create([
            'product_id' => $highRated->id,
            'user_id' => $buyer->id,
            'rating' => 5,
            'comment' => 'Отлично',
            'is_moderated' => true,
        ]);
        Review::query()->create([
            'product_id' => $lowRated->id,
            'user_id' => $buyer->id,
            'rating' => 3,
            'comment' => 'Норм',
            'is_moderated' => true,
        ]);

        $this->get('/category/'.$category->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CategoryPage')
                ->where('showSubcategories', false)
                ->has('products', 2)
            );

        $serviceResult = app(CatalogFilterService::class)->process(
            Request::create('/category/'.$category->id, 'GET', ['rating_min' => '4']),
            fn () => Product::forCatalogPresentation()
                ->where('products.category_id', $category->id)
                ->visibleInCatalog(),
            ['fixed_category_id' => (int) $category->id],
        );
        $this->assertCount(1, $serviceResult['products'], 'Service rating filter should return one product');

        $response = $this->get('/category/'.$category->id.'?rating_min=4');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('CategoryPage')
            ->has('products', 1)
            ->where('products.0.id', $highRated->id)
        );
    }

    public function test_search_defaults_to_relevance_sort(): void
    {
        $this->createListedProduct(['title' => 'Уникальный Zzz товар']);

        $response = $this->get('/?search='.urlencode('Уникальный Zzz'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'relevance')
        );
    }

    public function test_catalog_pagination_returns_has_more(): void
    {
        $category = Category::query()->create([
            'name' => 'Много товаров',
            'slug' => 'many-'.fake()->unique()->bothify('####'),
        ]);

        for ($i = 0; $i < 30; $i++) {
            $this->createListedProduct([
                'category_id' => $category->id,
                'title' => 'Товар '.$i,
                'min_price' => 100 + $i,
            ]);
        }

        $response = $this->get('/category/'.$category->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('products', 24)
            ->where('pagination.has_more', true)
            ->where('pagination.page', 1)
        );
    }

    private function createSeller(): User
    {
        $seller = User::query()->create([
            'name' => 'Seller',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'seller',
        ]);

        SellerProfile::query()->create([
            'user_id' => $seller->id,
            'shop_name' => 'Test Shop',
            'pickup_address' => 'Test address',
        ]);

        return $seller;
    }

    private function createListedProduct(array $overrides = []): Product
    {
        $sellerId = $overrides['seller_id'] ?? null;
        unset($overrides['seller_id']);

        if ($sellerId) {
            $seller = User::query()->find($sellerId);
            if ($seller && ! $seller->sellerProfile) {
                SellerProfile::query()->create([
                    'user_id' => $seller->id,
                    'shop_name' => 'Shop',
                    'pickup_address' => 'Test address',
                ]);
            }
        } else {
            $seller = $this->createSeller();
            $sellerId = $seller->id;
        }

        $category = Category::query()->create([
            'name' => 'Test category',
            'slug' => 'test-category-'.fake()->unique()->bothify('####'),
        ]);

        return Product::query()->create(array_merge([
            'seller_id' => $sellerId,
            'category_id' => $category->id,
            'title' => 'Test product',
            'min_price' => 100,
            'status' => 'approved',
            'is_on_action' => true,
        ], $overrides));
    }
}
