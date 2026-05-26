<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\CommissionRate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\OrderLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MarketplaceCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_stores_pending_category_commission_snapshot(): void
    {
        [$buyer, $product, $variant, $pickup] = $this->createSaleFixture(percent: 15, fixedAmount: 2);

        Cart::query()->create([
            'user_id' => $buyer->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $this->actingAs($buyer)->post(route('order.create'), [
            'pickup_point_id' => $pickup->id,
            'items' => [
                ['cart_id' => Cart::query()->where('user_id', $buyer->id)->value('id'), 'quantity' => 2],
            ],
        ])->assertRedirect(route('profile.orders'));

        $this->assertDatabaseHas('order_items', [
            'variant_id' => $variant->id,
            'seller_id' => $product->seller_id,
            'quantity' => 2,
            'commission_percent' => 15,
            'commission_fixed_amount' => 2,
            'commission_amount' => 34,
            'seller_payout_amount' => 166,
            'commission_status' => 'pending',
        ]);
    }

    public function test_paid_order_does_not_finalize_commission_before_issued(): void
    {
        [$buyer, $product, $variant] = $this->createSaleFixture(percent: 15, fixedAmount: 2);
        $order = $this->createOrderWithItem($buyer, $product, $variant, Order::STATUS_NEW, 'paid');

        app(OrderLedgerService::class)->finalizeCommission($order);

        $this->assertSame('pending', $order->items()->first()->commission_status);
        $this->assertDatabaseMissing('transactions', [
            'order_id' => $order->id,
            'type' => Transaction::TYPE_PAYMENT,
        ]);
    }

    public function test_issued_paid_order_finalizes_commission_and_records_net_ledger(): void
    {
        [$buyer, $product, $variant] = $this->createSaleFixture(percent: 15, fixedAmount: 2);
        $order = $this->createOrderWithItem($buyer, $product, $variant, Order::STATUS_ISSUED, 'paid');

        app(OrderLedgerService::class)->finalizeCommission($order);

        $item = $order->items()->first();
        $this->assertSame('finalized', $item->commission_status);
        $this->assertNotNull($item->commission_finalized_at);

        $this->assertDatabaseHas('transactions', [
            'order_id' => $order->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $product->seller_id,
            'product_id' => $product->id,
            'amount' => 200,
            'gross_amount' => 200,
            'commission_amount' => 34,
            'seller_payout_amount' => 166,
            'type' => Transaction::TYPE_PAYMENT,
            'status' => 'completed',
        ]);
    }

    public function test_canceled_order_reverses_unfinalized_commission(): void
    {
        [$buyer, $product, $variant] = $this->createSaleFixture(percent: 15, fixedAmount: 2);
        $order = $this->createOrderWithItem($buyer, $product, $variant, Order::STATUS_CANCELED, 'paid');

        app(OrderLedgerService::class)->reverseCommission($order);

        $this->assertSame('reversed', $order->items()->first()->commission_status);
    }

    public function test_category_without_rate_uses_default_ten_percent(): void
    {
        $seller = $this->createUser('seller');
        $category = Category::query()->create([
            'name' => 'Default rate category',
            'slug' => 'default-rate-category',
        ]);
        $product = Product::query()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Default commission product',
            'min_price' => 100,
            'status' => 'approved',
        ]);

        $snapshot = app(CommissionService::class)->calculateForProduct($product, 100, 1);

        $this->assertSame(10.0, $snapshot['percent']);
        $this->assertSame(10.0, $snapshot['commission']);
        $this->assertSame(90.0, $snapshot['seller_payout']);
    }

    /**
     * @return array{0: User, 1: Product, 2: ProductVariant, 3?: PickupPoint}
     */
    private function createSaleFixture(float $percent, float $fixedAmount): array
    {
        $seller = $this->createUser('seller');
        $buyer = $this->createUser('user', [
            'phone' => '7999000'.random_int(1000, 9999),
            'email_verified_at' => now(),
        ]);
        $category = Category::query()->create([
            'name' => 'Commission category',
            'slug' => 'commission-category-'.strtolower(fake()->unique()->bothify('????##')),
        ]);
        CommissionRate::query()->create([
            'category_id' => $category->id,
            'percent' => $percent,
            'fixed_amount' => $fixedAmount,
        ]);
        $product = Product::query()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Commission product',
            'min_price' => 100,
            'status' => 'approved',
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'options' => ['size' => 'M'],
            'price' => 100,
            'stock' => 10,
            'is_active' => true,
        ]);
        $pickup = PickupPoint::query()->create([
            'title' => 'PVZ',
            'address' => 'Test address',
            'region_id' => 1,
            'is_active' => true,
        ]);

        return [$buyer, $product, $variant, $pickup];
    }

    private function createUser(string $role, array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
            'is_blocked' => false,
        ], $overrides));
    }

    private function createOrderWithItem(
        User $buyer,
        Product $product,
        ProductVariant $variant,
        string $status,
        string $paymentStatus,
    ): Order {
        $order = Order::query()->create([
            'number' => fake()->unique()->numberBetween(10000, 99999),
            'order_code' => strtoupper(fake()->unique()->bothify('????####')),
            'buyer_id' => $buyer->id,
            'status' => $status,
            'total' => 200,
            'payment_status' => $paymentStatus,
            'delivery_method' => 'pvz',
        ]);

        $snapshot = app(CommissionService::class)->calculateForProduct($product, 100, 2);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'variant_id' => $variant->id,
            'seller_id' => $product->seller_id,
            'quantity' => 2,
            'price_at_purchase' => 100,
            'commission_percent' => $snapshot['percent'],
            'commission_fixed_amount' => $snapshot['fixed_amount'],
            'commission_amount' => $snapshot['commission'],
            'seller_payout_amount' => $snapshot['seller_payout'],
            'commission_status' => 'pending',
        ]);

        return $order->fresh('items');
    }
}
