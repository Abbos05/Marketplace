<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PvzAccrual;
use App\Models\Review;
use App\Models\ReviewVote;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\OrderLedgerService;
use App\Services\PvzFeeCalculator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoCommerceSeeder extends Seeder
{
    public function run(): void
    {
        $pickup = PickupPoint::query()->active()->first();
        $pickupId = $pickup?->id;
        $regionId = $pickup?->region_id;

        $buyers = [8, 9];
        $pvzOperator = User::query()->where('email', 'pvz1@gmail.com')->first();
        $products = Product::query()->with('variants')->where('status', 'approved')->get();

        if ($products->isEmpty()) {
            return;
        }

        $orderConfigs = [
            ['buyer_id' => 8, 'status' => Order::STATUS_DELIVERED, 'payment_status' => 'paid', 'days_ago' => 14],
            ['buyer_id' => 8, 'status' => Order::STATUS_INTRANSIT, 'payment_status' => 'paid', 'days_ago' => 5],
            ['buyer_id' => 8, 'status' => Order::STATUS_NEW, 'payment_status' => 'paid', 'days_ago' => 2],
            ['buyer_id' => 8, 'status' => Order::STATUS_NEW, 'payment_status' => 'paid', 'days_ago' => 1],
            ['buyer_id' => 9, 'status' => Order::STATUS_ISSUED, 'payment_status' => 'paid', 'days_ago' => 45],
            ['buyer_id' => 9, 'status' => Order::STATUS_ISSUED, 'payment_status' => 'paid', 'days_ago' => 20],
            ['buyer_id' => 8, 'status' => Order::STATUS_ISSUED, 'payment_status' => 'paid', 'days_ago' => 12],
            ['buyer_id' => 9, 'status' => Order::STATUS_INTRANSIT, 'payment_status' => 'paid', 'days_ago' => 4],
            ['buyer_id' => 9, 'status' => Order::STATUS_INTRANSIT, 'payment_status' => 'paid', 'days_ago' => 3],
            ['buyer_id' => 9, 'status' => Order::STATUS_NEW, 'payment_status' => 'pending', 'days_ago' => 0],
        ];

        $productIndex = 0;
        $issuedOrders = [];

        foreach ($orderConfigs as $cfg) {
            $items = $this->pickOrderItems($products, $productIndex, 2);
            $productIndex += 2;

            if ($items === []) {
                continue;
            }

            $total = collect($items)->sum(fn ($i) => $i['price_at_purchase'] * $i['quantity']);
            $created = Carbon::now()->subDays($cfg['days_ago']);

            $order = Order::query()->create([
                'number' => Str::upper(Str::random(8)),
                'order_code' => Str::upper(Str::random(10)),
                'buyer_id' => $cfg['buyer_id'],
                'pickup_point_id' => $pickupId,
                'region_id' => $regionId,
                'status' => $cfg['status'],
                'total' => $total,
                'discount' => 0,
                'delivery_address' => $pickup?->address ?? 'г. Иркутск, ПВЗ',
                'payment_status' => $cfg['payment_status'],
                'delivery_method' => 'pvz',
                'payment_method' => 'card',
                'created_at' => $created,
                'updated_at' => $created,
            ]);

            $isFinalizedSale = $cfg['status'] === Order::STATUS_ISSUED
                && $cfg['payment_status'] === 'paid';

            foreach ($items as $item) {
                OrderItem::query()->create(array_merge($item, [
                    'order_id' => $order->id,
                    'commission_status' => $isFinalizedSale ? 'finalized' : ($item['commission_status'] ?? 'pending'),
                    'commission_finalized_at' => $isFinalizedSale ? $created : null,
                    'created_at' => $created,
                    'updated_at' => $created,
                ]));
            }

            if (in_array($cfg['status'], [Order::STATUS_DELIVERED, Order::STATUS_ISSUED], true)) {
                $issuedOrders[] = ['order' => $order, 'buyer_id' => $cfg['buyer_id'], 'items' => $items];
            }

            if ($cfg['payment_status'] === 'paid') {
                $ledger = app(OrderLedgerService::class);
                if ($isFinalizedSale) {
                    $ledger->finalizeCommission($order->fresh());
                } else {
                    $ledger->recordPayment($order);
                }
            }

            if ($cfg['status'] === Order::STATUS_ISSUED && $pvzOperator && $pickupId) {
                $issuedAt = $created->copy()->addHours(3);
                $order->update([
                    'issued_by_user_id' => $pvzOperator->id,
                    'issued_at' => $issuedAt,
                    'updated_at' => $issuedAt,
                ]);

                $orderTotal = (float) $order->total;
                PvzAccrual::query()->firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'pickup_point_id' => $pickupId,
                        'user_id' => $pvzOperator->id,
                        'amount' => PvzFeeCalculator::forOrderTotal($orderTotal),
                        'order_total' => $orderTotal,
                        'type' => PvzAccrual::TYPE_ISSUED,
                        'period' => $issuedAt->format('Y-m'),
                        'created_at' => $issuedAt,
                        'updated_at' => $issuedAt,
                    ],
                );
            }
        }

        $this->seedReviews($issuedOrders);
        $this->seedFavorites($buyers, $products);
        $this->syncSalesCounters();
    }

    /** @return list<array> */
    private function pickOrderItems($products, int $startIndex, int $count): array
    {
        $items = [];
        $list = $products->values();
        $commissionService = app(CommissionService::class);

        for ($i = 0; $i < $count; $i++) {
            $product = $list[($startIndex + $i) % $list->count()] ?? null;
            if (! $product) {
                break;
            }
            $variant = $product->variants->first();
            if (! $variant) {
                continue;
            }
            $qty = rand(1, 2);
            $price = (float) $variant->price;
            $commission = $commissionService->calculateForProduct($product, $price, $qty);

            $items[] = [
                'variant_id' => $variant->id,
                'seller_id' => $product->seller_id,
                'quantity' => $qty,
                'price_at_purchase' => $price,
                'commission_percent' => $commission['percent'],
                'commission_fixed_amount' => $commission['fixed_amount'],
                'commission_amount' => $commission['commission'],
                'seller_payout_amount' => $commission['seller_payout'],
                'commission_status' => 'pending',
            ];
        }

        return $items;
    }

    private function seedReviews(array $issuedOrders): void
    {
        $voterPool = User::query()->pluck('id')->all();

        $comments = [
            'Отличное качество, рекомендую!',
            'Доставка быстрая, товар как на фото.',
            'Всё понравилось, закажу ещё.',
            'Хороший продавец, упаковка надёжная.',
            'Соответствует описанию, доволен покупкой.',
        ];

        foreach ($issuedOrders as $idx => $row) {
            $order = $row['order'];
            foreach ($row['items'] as $item) {
                $variant = ProductVariant::query()->find($item['variant_id']);
                if (! $variant) {
                    continue;
                }

                $review = Review::query()->create([
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'user_id' => $row['buyer_id'],
                    'order_id' => $order->id,
                    'rating' => rand(4, 5),
                    'comment' => $comments[($idx + $variant->id) % count($comments)],
                    'is_moderated' => true,
                    'likes_count' => 0,
                    'dislikes_count' => 0,
                    'created_at' => $order->created_at->copy()->addDays(rand(1, 5)),
                    'updated_at' => now(),
                ]);

                $this->seedReviewVotes($review, (int) $row['buyer_id'], $voterPool);
            }
        }
    }

    /** @param list<int> $voterPool */
    private function seedReviewVotes(Review $review, int $authorId, array $voterPool): void
    {
        $candidates = collect($voterPool)
            ->reject(fn (int $id) => $id === $authorId)
            ->shuffle()
            ->values();

        $maxVotes = $candidates->count();
        if ($maxVotes === 0) {
            return;
        }

        $likesTarget = rand(3, min(12, $maxVotes));
        $dislikesTarget = rand(0, min(4, max(0, $maxVotes - $likesTarget)));

        foreach ($candidates->take($likesTarget) as $userId) {
            ReviewVote::query()->create([
                'user_id' => $userId,
                'review_id' => $review->id,
                'vote' => ReviewVote::VOTE_HELPFUL,
            ]);
        }

        foreach ($candidates->slice($likesTarget)->take($dislikesTarget) as $userId) {
            ReviewVote::query()->create([
                'user_id' => $userId,
                'review_id' => $review->id,
                'vote' => ReviewVote::VOTE_UNHELPFUL,
            ]);
        }

        ReviewVote::syncReviewCounts($review);
    }

    private function seedFavorites(array $buyerIds, $products): void
    {
        $now = now();

        foreach ($buyerIds as $buyerId) {
            $picked = $products->shuffle()->take(5);
            foreach ($picked as $product) {
                $variantId = $product->variants->first()?->id;

                DB::table('favorites')->insertOrIgnore([
                    'user_id' => $buyerId,
                    'product_id' => $product->id,
                    'variant_id' => $variantId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function syncSalesCounters(): void
    {
        foreach (Product::query()->get() as $product) {
            $sold = OrderItem::query()
                ->whereHas('order', fn ($q) => $q->whereIn('status', [
                    Order::STATUS_ISSUED,
                    Order::STATUS_DELIVERED,
                    Order::STATUS_INTRANSIT,
                    Order::STATUS_NEW,
                ]))
                ->whereHas('variant', fn ($q) => $q->where('product_id', $product->id))
                ->sum('quantity');

            if ($sold > 0) {
                $product->update(['sales_count' => max($product->sales_count, (int) $sold)]);
            }
        }

        foreach ([6, 7] as $sellerId) {
            $total = OrderItem::query()
                ->where('seller_id', $sellerId)
                ->whereHas('order', fn ($q) => $q->whereNotIn('status', [Order::STATUS_CANCELED, Order::STATUS_REFUSED]))
                ->sum('quantity');

            SellerProfile::query()->where('user_id', $sellerId)->update([
                'total_sales' => $total,
            ]);
        }
    }
}
