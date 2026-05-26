<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesCatalogRecommendations;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupPoint;
use App\Models\Promocode;
use App\Models\PromocodeUsage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Transaction;
use App\Services\CommissionService;
use App\Services\OrderLedgerService;
use App\Services\OrderNotificationService;
use App\Services\ReviewImageService;
use App\Services\StripeRefundService;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrderController extends Controller
{
    use PreparesCatalogRecommendations;

    public function index()
    {
        $user = auth()->user();
        $dailyPickupCode = $user->ensureDailyPickupCode();

        $orders = Order::with('items.variant.product')
            ->where('buyer_id', $user->id)
            ->latest()
            ->get();

        $excludeProductIds = $orders
            ->flatMap(fn (Order $order) => $order->items->pluck('variant.product_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $LikeProducts = $this->catalogRecommendations([
            'exclude_product_ids' => $excludeProductIds,
        ]);

        return Inertia::render('Profile/Orders', [
            'dailyPickupCode' => $dailyPickupCode,
            'LikeProducts' => $LikeProducts,
            'orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'number' => $order->number,
                    'order_code' => $order->order_code,
                    'status' => $order->status,
                    'total' => (float) $order->total,
                    'delivery_address' => $order->delivery_address,
                    'delivery_method' => $order->delivery_method,
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at->format('d.m.Y H:i'),
                    'updated_at' => $order->updated_at->format('d.m.Y'),
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'quantity' => $item->quantity,
                            'price_at_purchase' => (float) $item->price_at_purchase,
                            'variant' => [
                                'id' => $item->variant->id ?? null,
                                'price' => $item->variant->price ?? 0,
                                'product' => [
                                    'id' => $item->variant->product->id ?? null,
                                    'title' => $item->variant->product->title ?? 'Товар',
                                    'image' => $item->variant->product->image ?? '/img/products/default.png',
                                ]
                            ]
                        ];
                    })
                ];
            })
        ]);
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $items = $request->items;

        if ($user->is_blocked) {
            return back()->with('error', 'Аккаунт заблокирован. Оформление заказов недоступно.');
        }

        if (! $user->phone) {
            return redirect()->route('profile')->with('error', 'Подтвердите номер телефона в профиле, чтобы оформить заказ.');
        }

        if (!$items || count($items) === 0) {
            return back()->with('error', 'Нет товаров');
        }

        $pickupPointId = $request->input('pickup_point_id') ?? $user->default_pickup_point_id;
        if (! $pickupPointId) {
            return back()->with('error', 'Укажите пункт выдачи в профиле или при оформлении заказа.');
        }

        $pickup = PickupPoint::query()->active()->whereKey($pickupPointId)->first();
        if (! $pickup) {
            return back()->with('error', 'Выбранный пункт выдачи недоступен. Выберите другой.');
        }

        DB::beginTransaction();

        // Генерация номера заказа
        $number = time() . '-' . rand(1000, 9999);
        $orderCode = strtoupper(substr(md5(uniqid()), 0, 10));

        $order = Order::create([
            'number' => $number,
            'order_code' => $orderCode,
            'buyer_id' => $user->id,
            'pickup_point_id' => $pickup->id,
            'region_id' => $pickup->region_id,
            'delivery_address' => $pickup->snapshotAddress(),
            'status' => Order::STATUS_NEW,
            'total' => 0,
            'payment_status' => 'pending',
            'delivery_method' => 'pvz',
        ]);

        $total = 0;
        $orderItemsData = [];
        $commissionService = app(CommissionService::class);

        foreach ($items as $item) {
            $cartItem = null;
            $quantity = 0;

            if (isset($item['cart_id'])) {
                $cartItem = Cart::with('variant.product')
                    ->where('id', $item['cart_id'])
                    ->where('user_id', $user->id)
                    ->first();

                if (! $cartItem) {
                    DB::rollBack();

                    return back()->with('error', 'Позиция корзины не найдена или уже оформлена.');
                }

                $quantity = (int) ($item['quantity'] ?? $cartItem->quantity);
                if ($quantity < 1) {
                    $quantity = $cartItem->quantity;
                }

                $variant = ProductVariant::query()
                    ->with('product')
                    ->whereKey($cartItem->variant_id)
                    ->lockForUpdate()
                    ->first();
            } else {
                $quantity = (int) ($item['quantity'] ?? 1);
                if ($quantity < 1) {
                    $quantity = 1;
                }

                $variant = ProductVariant::query()
                    ->with('product')
                    ->whereKey($item['variant_id'])
                    ->lockForUpdate()
                    ->first();
            }

            if (! $variant || ! $variant->is_active) {
                DB::rollBack();

                return back()->with('error', 'Товар недоступен для заказа.');
            }

            if (! $variant->product?->isPurchasable()) {
                DB::rollBack();

                return back()->with(
                    'error',
                    $variant->product?->storefrontBlockMessage() ?? 'Товар недоступен для заказа.'
                );
            }

            if ($variant->stock < $quantity) {
                DB::rollBack();

                return back()->with('error', 'Недостаточно товара на складе: «'.($variant->product->title ?? 'Товар').'». Доступно: '.$variant->stock.' шт.');
            }

            $price = (float) $variant->price;
            $variantId = $variant->id;
            $sellerId = $variant->product->seller_id;
            $commission = $commissionService->calculateForProduct($variant->product, $price, $quantity);

            $total += $price * $quantity;

            $orderItemsData[] = [
                'seller_id' => $sellerId,
                'price' => $price,
                'quantity' => $quantity,
            ];

            OrderItem::create([
                'order_id' => $order->id,
                'variant_id' => $variantId,
                'seller_id' => $sellerId,
                'quantity' => $quantity,
                'price_at_purchase' => $price,
                'commission_percent' => $commission['percent'],
                'commission_fixed_amount' => $commission['fixed_amount'],
                'commission_amount' => $commission['commission'],
                'seller_payout_amount' => $commission['seller_payout'],
                'commission_status' => 'pending',
            ]);

            $variant->decrement('stock', $quantity);

            $productModel = Product::query()->whereKey($variant->product_id)->lockForUpdate()->first();
            if ($productModel) {
                $productModel->increment('sales_count', $quantity);
            }

            if ($cartItem) {
                $cartItem->delete();
            }
        }

        // Apply promo code if provided
        $discount = 0;
        $promoCode = $request->input('promo_code');

        if ($promoCode) {
            $promo = Promocode::where('code', strtoupper(trim($promoCode)))->first();

            if ($promo && $promo->isValid()) {
                $usedCount     = $promo->usages()->count();
                $userUsedCount = $promo->usages()->where('user_id', $user->id)->count();

                $limitOk    = $promo->usage_limit    === null || $usedCount     < $promo->usage_limit;
                $perUserOk  = $promo->usage_per_user === null || $userUsedCount < $promo->usage_per_user;

                if ($limitOk && $perUserOk) {
                    // Sum seller's items subtotal for discount calculation
                    $sellerSubtotal = collect($orderItemsData ?? [])
                        ->where('seller_id', $promo->seller_id)
                        ->sum(fn($i) => $i['price'] * $i['quantity']);

                    $minOk = $promo->min_order_amount === null || $sellerSubtotal >= $promo->min_order_amount;

                    if ($minOk && $sellerSubtotal > 0) {
                        if ($promo->discount_type === 'percent') {
                            $discount = round($sellerSubtotal * $promo->discount_value / 100, 2);
                        } else {
                            $discount = min((float) $promo->discount_value, $sellerSubtotal);
                        }

                        PromocodeUsage::create([
                            'promocode_id'    => $promo->id,
                            'user_id'         => $user->id,
                            'order_id'        => $order->id,
                            'discount_applied'=> $discount,
                        ]);
                    }
                }
            }
        }

        $order->update([
            'total'    => max(0, $total - $discount),
            'discount' => $discount,
        ]);

        DB::commit();

        app(OrderNotificationService::class)->notifyCreated($order->fresh());

        return redirect()->route('profile.orders')->with('success', 'Заказ оформлен!');
    }
    public function show(Order $order, Request $request)
    {
        if ($order->buyer_id !== auth()->id()) {
            abort(403);
        }

        if ($this->shouldOfferRefundCheckout($order) && ! $request->boolean('view')) {
            return redirect()->route('order.refund.checkout', $order);
        }

        $order->load([
            'items.variant.product.seller',
            'items.review.images',
            'pickupPoint.region',
            'region',
        ]);

        $excludeProductIds = $order->items
            ->pluck('variant.product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $LikeProducts = $this->catalogRecommendations([
            'exclude_product_ids' => $excludeProductIds,
        ]);

        $imageService = app(ReviewImageService::class);
        $orderPayload = $order->toArray();
        $orderPayload['items'] = $order->items->map(function (OrderItem $item) use ($order, $imageService) {
            $row = $item->toArray();
            $review = $item->review;
            $row['can_review'] = $this->orderItemCanReview($order, $item, $review);
            $row['review_unavailable_reason'] = $this->orderItemReviewUnavailableReason($order, $item, $review);
            $row['review'] = $review ? $this->formatOrderItemReview($review, $imageService) : null;

            return $row;
        })->values()->all();

        return Inertia::render('Profile/OrderShow', [
            'order' => $orderPayload,
            'deliveryTrack' => $this->buildDeliveryTrackPayload($order),
            'documents' => $this->buildOrderDocumentsPayload($order),
            'LikeProducts' => $LikeProducts,
        ]);
    }

    /**
     * @return array{receipt: string, payment: ?string, refund: ?string, cancel: ?string}
     */
    private function buildOrderDocumentsPayload(Order $order): array
    {
        $hasType = static fn (string $type) => Transaction::query()
            ->where('order_id', $order->id)
            ->where('type', $type)
            ->exists();

        return [
            'receipt' => route('order.receipt', $order),
            'payment' => ($hasType('payment') || $order->payment_status === 'paid')
                ? route('order.document', [$order, 'payment'])
                : null,
            'refund' => ($hasType('refund') || $order->payment_status === 'refunded')
                ? route('order.document', [$order, 'refund'])
                : null,
            'cancel' => ($hasType('cancel') || $order->status === Order::STATUS_CANCELED)
                ? route('order.document', [$order, 'cancel'])
                : null,
        ];
    }

    /**
     * Данные для блока «Детали доставки» (таймлайн в духе маркетплейсов).
     *
     * @return array{summary: string, destination: string, region: ?string, method_label: string, eta_hint: string, steps: list<array<string, mixed>>}
     */
    private function buildDeliveryTrackPayload(Order $order): array
    {
        $fmt = static function ($value): ?string {
            if ($value === null) {
                return null;
            }

            return Carbon::parse($value)->locale('ru')->translatedFormat('d MMMM yyyy, HH:mm');
        };

        $destination = trim((string) ($order->delivery_address ?: '')) ?: 'Адрес доставки уточняется';
        $regionName = $order->pickupPoint?->region?->name
            ?? $order->region?->name;
        $methodLabel = match ($order->delivery_method) {
            'courier' => 'Курьер',
            'post' => 'Почта',
            default => 'Пункт выдачи',
        };

        $sellers = $order->items
            ->map(fn (OrderItem $i) => $i->variant?->product?->seller?->name)
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');
        $originDetail = $sellers !== ''
            ? 'Продавец: '.$sellers
            : 'Заказ сформирован на маркетплейсе';

        $paid = $order->payment_status === 'paid';
        $st = $order->status;

        $summary = match ($st) {
            Order::STATUS_CANCELED => 'Заказ отменён — доставка не выполняется',
            Order::STATUS_REFUSED => 'Отказ от получения в пункте выдачи',
            Order::STATUS_DELIVERED => 'В пункте выдачи — можно забирать',
            Order::STATUS_ISSUED => 'Выдан покупателю',
            Order::STATUS_INTRANSIT => 'В пути в пункт выдачи',
            Order::STATUS_NEW => $paid
                ? 'Продавец готовит заказ к отправке'
                : 'Ожидаем оплату — после оплаты заказ перейдёт к продавцу',
            default => 'Статус доставки обновляется',
        };

        $etaHint = match ($st) {
            Order::STATUS_DELIVERED => 'Заберите заказ в пункте выдачи по коду.',
            Order::STATUS_ISSUED => 'Заказ получен. Можно оставить отзыв в течение 90 дней.',
            Order::STATUS_INTRANSIT => 'Точная дата прибытия в пункт зависит от продавца и службы доставки — следите за статусом.',
            Order::STATUS_NEW => $paid
                ? 'Обычно отправка в течение 1–3 рабочих дней после оплаты (зависит от продавца).'
                : 'После оплаты продавец получит заказ в работу.',
            Order::STATUS_CANCELED => '',
            Order::STATUS_REFUSED => '',
            default => '',
        };

        $steps = [];

        $push = static function (array &$steps, string $id, string $title, string $detail, ?string $meta, string $state): void {
            $steps[] = [
                'id' => $id,
                'title' => $title,
                'detail' => $detail,
                'meta' => $meta,
                'state' => $state,
            ];
        };

        if ($st === Order::STATUS_CANCELED) {
            $push($steps, 'placed', 'Заказ оформлен', $originDetail, $fmt($order->created_at), 'done');
            $push($steps, 'cancel', 'Заказ отменён', 'Отмена до отправки или по решению сервиса', $fmt($order->updated_at), 'active');

            return [
                'summary' => $summary,
                'destination' => $destination,
                'region' => $regionName,
                'method_label' => $methodLabel,
                'eta_hint' => $etaHint,
                'steps' => $steps,
            ];
        }

        if ($st === Order::STATUS_REFUSED) {
            $push($steps, 'placed', 'Заказ оформлен', $originDetail, $fmt($order->created_at), 'done');
            if ($paid) {
                $push($steps, 'confirm', 'Оплата и сборка', 'Заказ подтверждён продавцом', $fmt($order->created_at), 'done');
            }
            $push($steps, 'ship', 'Доставка в пункт выдачи', $destination, $fmt($order->updated_at), 'done');
            $push($steps, 'refuse', 'Отказ от получения', 'Покупатель не забрал заказ в пункте выдачи', $fmt($order->updated_at), 'active');

            return [
                'summary' => $summary,
                'destination' => $destination,
                'region' => $regionName,
                'method_label' => $methodLabel,
                'eta_hint' => $etaHint,
                'steps' => $steps,
            ];
        }

        // Нормальный поток NEW → INTRANSIT → DELIVERED
        $push($steps, 'placed', 'Заказ оформлен', $originDetail, $fmt($order->created_at), 'done');

        if ($paid) {
            $paidAt = $order->updated_at && $order->created_at
                && $order->updated_at->gt($order->created_at->copy()->addMinute())
                ? $fmt($order->updated_at)
                : $fmt($order->created_at);
            $push($steps, 'confirm', 'Оплата получена', 'Продавец собирает заказ', $paidAt, 'done');
        } else {
            $push($steps, 'pay_wait', 'Ожидание оплаты', 'После оплаты продавец начнёт сборку', null, $st === Order::STATUS_NEW ? 'active' : 'pending');
        }

        if ($st === Order::STATUS_NEW && $paid) {
            $push($steps, 'prep', 'Сборка и отправка', 'Заказ у продавца, ожидается передача в доставку', $fmt($order->updated_at), 'active');
            $push($steps, 'transit', 'В пути в пункт выдачи', $destination, null, 'pending');
            $push($steps, 'pvz', 'Пункт выдачи', 'Прибытие и выдача по коду', null, 'pending');
        } elseif ($st === Order::STATUS_NEW && ! $paid) {
            $push($steps, 'prep', 'Сборка и отправка', 'Начнётся после оплаты', null, 'pending');
            $push($steps, 'transit', 'В пути в пункт выдачи', $destination, null, 'pending');
            $push($steps, 'pvz', 'Пункт выдачи', 'Прибытие и выдача по коду', null, 'pending');
        } elseif ($st === Order::STATUS_INTRANSIT) {
            $push($steps, 'prep', 'Сборка завершена', 'Заказ передан в доставку', $fmt($order->updated_at), 'done');
            $push($steps, 'transit', 'В пути в пункт выдачи', $destination, $fmt($order->updated_at), 'active');
            $push($steps, 'pvz', 'В пункте выдачи', 'Ожидаем поступление', null, 'pending');
            $push($steps, 'issued', 'Выдан покупателю', 'Получение по коду', null, 'pending');
        } elseif ($st === Order::STATUS_DELIVERED) {
            $push($steps, 'prep', 'Сборка и отправка', 'Заказ передан в доставку', $fmt($order->created_at), 'done');
            $push($steps, 'transit', 'В пути в пункт выдачи', $destination, $fmt($order->updated_at), 'done');
            $push($steps, 'pvz', 'В пункте выдачи', 'Можно забрать по коду получения', $fmt($order->updated_at), 'active');
            $push($steps, 'issued', 'Выдан покупателю', 'Подтверждение выдачи', null, 'pending');
        } else { // ISSUED
            $push($steps, 'prep', 'Сборка и отправка', 'Заказ передан в доставку', $fmt($order->created_at), 'done');
            $push($steps, 'transit', 'В пути в пункт выдачи', $destination, $fmt($order->updated_at), 'done');
            $push($steps, 'pvz', 'В пункте выдачи', 'Заказ поступил в пункт', $fmt($order->updated_at), 'done');
            $push($steps, 'issued', 'Выдан покупателю', 'Товар получен', $fmt($order->updated_at), 'active');
        }

        return [
            'summary' => $summary,
            'destination' => $destination,
            'region' => $regionName,
            'method_label' => $methodLabel,
            'eta_hint' => $etaHint,
            'steps' => $steps,
        ];
    }

    public function cancel(Order $order)
    {
        if ($order->buyer_id !== auth()->id()) {
            abort(403);
        }

        if ($order->status === Order::STATUS_NEW) {
            $order->load('items.variant.product');

            foreach ($order->items as $item) {
                $variant = $item->variant;
                if (! $variant) {
                    continue;
                }

                $variant->increment('stock', $item->quantity);

                $productModel = $variant->product;
                if ($productModel && $productModel->sales_count > 0) {
                    $decrementBy = min((int) $item->quantity, (int) $productModel->sales_count);
                    $productModel->decrement('sales_count', $decrementBy);
                }
            }

            $order->update(['status' => Order::STATUS_CANCELED]);
            app(OrderLedgerService::class)->reverseCommission($order->fresh());
            $order->refresh();

            if ($order->payment_status === 'pending') {
                $order->update(['payment_status' => 'failed']);
                app(OrderLedgerService::class)->recordCancel($order);
            }

            app(OrderNotificationService::class)->notifyStatusChange($order->fresh(), Order::STATUS_CANCELED);

            if ($this->shouldOfferRefundCheckout($order)) {
                return redirect()
                    ->route('order.refund.checkout', $order)
                    ->with('success', 'Заказ отменён. Подтвердите возврат средств на карту.');
            }

            return back()->with('success', 'Заказ отменён');
        }

        return back()->with('error', 'Отменить можно только новый заказ');
    }

    /**
     * Страница подтверждения возврата (как окно оплаты Stripe).
     */
    public function refundCheckout(Order $order)
    {
        if ($order->buyer_id !== auth()->id()) {
            abort(403);
        }

        if ($order->payment_status === 'refunded') {
            return redirect()->route('order.show', $order)
                ->with('success', 'Возврат по этому заказу уже оформлен.');
        }

        if ($order->payment_status !== 'paid') {
            return redirect()->route('order.show', $order)
                ->with('error', 'Возврат доступен только для оплаченных заказов.');
        }

        if (! in_array($order->status, [Order::STATUS_CANCELED, Order::STATUS_REFUSED], true)) {
            return redirect()->route('order.show', $order)
                ->with('error', 'Сначала отмените заказ или оформите отказ от получения.');
        }

        return Inertia::render('Profile/RefundCheckout', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'total' => (float) $order->total,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
            ],
        ]);
    }

    /**
     * Подтверждение возврата со страницы checkout.
     */
    public function refundComplete(Order $order)
    {
        if ($order->buyer_id !== auth()->id()) {
            abort(403);
        }

        if ($order->payment_status === 'refunded') {
            return redirect()->route('order.show', $order)
                ->with('success', 'Возврат уже был оформлен ранее.');
        }

        if ($order->payment_status !== 'paid') {
            return redirect()->route('order.show', $order)
                ->with('error', 'Заказ не оплачен — возврат не требуется.');
        }

        if (! in_array($order->status, [Order::STATUS_CANCELED, Order::STATUS_REFUSED], true)) {
            return redirect()->route('order.show', $order)
                ->with('error', 'Возврат недоступен для текущего статуса заказа.');
        }

        $result = app(StripeRefundService::class)->refundOrder($order, 'buyer_refund_checkout');

        if ($result['ok']) {
            app(OrderNotificationService::class)->notifyRefunded($order->fresh());
        }

        return redirect()->route('order.show', ['order' => $order, 'view' => 1])
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Прямой возврат (редирект на страницу подтверждения).
     */
    public function refund(Order $order)
    {
        if ($order->buyer_id !== auth()->id()) {
            abort(403);
        }

        return redirect()->route('order.refund.checkout', $order);
    }

    private function shouldOfferRefundCheckout(Order $order): bool
    {
        return $order->payment_status === 'paid'
            && in_array($order->status, [Order::STATUS_CANCELED, Order::STATUS_REFUSED], true);
    }

    private function orderItemCanReview(Order $order, OrderItem $item, ?Review $review): bool
    {
        if ($review) {
            return $order->status === Order::STATUS_ISSUED
                && $order->updated_at->diffInDays(now()) <= 90;
        }

        return $order->status === Order::STATUS_ISSUED
            && $order->updated_at->diffInDays(now()) <= 90;
    }

    private function orderItemReviewUnavailableReason(Order $order, OrderItem $item, ?Review $review): ?string
    {
        if ($review) {
            if ($order->status !== Order::STATUS_ISSUED) {
                return null;
            }
            if ($order->updated_at->diffInDays(now()) > 90) {
                return 'Срок для редактирования отзыва истёк (90 дней)';
            }

            return null;
        }

        if ($order->status !== Order::STATUS_ISSUED) {
            return 'Отзыв можно оставить после получения заказа';
        }

        if ($order->updated_at->diffInDays(now()) > 90) {
            return 'Срок для отзыва истёк (90 дней)';
        }

        return null;
    }

    private function formatOrderItemReview(Review $review, ReviewImageService $imageService): array
    {
        $moderationStatus = 'pending';
        if ($review->trashed() || ($review->moderation_comment && ! $review->is_moderated)) {
            $moderationStatus = 'rejected';
        } elseif ($review->is_moderated) {
            $moderationStatus = 'published';
        }

        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
            'is_moderated' => (bool) $review->is_moderated,
            'moderation_status' => $moderationStatus,
            'moderation_comment' => $review->moderation_comment,
            'images' => $imageService->mapImagesForFrontend($review->images),
        ];
    }
}