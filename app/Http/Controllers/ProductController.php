<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\PickupPoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReviewVote;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class ProductController extends Controller
{
    /**
     * Товары текущего продавца (кабинет).
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $sort   = $request->query('sort', 'newest');

        $query = Product::query()
            ->where('seller_id', Auth::id())
            ->with([
                'category',
                'images' => fn ($q) => $q->whereNull('variant_id'),
                'variants.images',
            ]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        match ($sort) {
            'oldest'     => $query->orderBy('id'),
            'price_asc'  => $query->orderBy('min_price'),
            'price_desc' => $query->orderByDesc('min_price'),
            'title'      => $query->orderBy('title'),
            default      => $query->orderByDesc('id'),
        };

        $products = $query->withCount('variants')->get()->map(function (Product $p) {
            $totalStock = $p->variants()->sum('stock');
            return [
                'id'             => $p->id,
                'title'          => $p->title,
                'category'       => ['name' => $p->category?->name],
                'min_price'      => (float) $p->min_price,
                'status'              => $p->status,
                'moderation_comment'  => $p->moderation_comment,
                'is_listed'           => (bool) $p->is_on_action,
                'variants_count' => (int) $p->variants_count,
                'total_stock'    => (int) $totalStock,
                'main_image'     => $p->resolveListingImageUrl(),
                'created_at'     => $p->created_at?->format('d.m.Y'),
            ];
        });

        $counts = Product::where('seller_id', Auth::id())
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return Inertia::render('Seller/Products/Index', [
            'products'    => $products,
            'statusCounts'=> $counts,
            'filters'     => ['status' => $status, 'sort' => $sort],
        ]);
    }

    public function show(Product $product, Request $request)
    {
        $user = Auth::user();
        $blockReason = $product->storefrontBlockReason();

        if ($blockReason !== null) {
            $canPreview = $user && (
                (int) $user->id === (int) $product->seller_id || $user->isStaff()
            );

            if (! $canPreview) {
                return Inertia::render('Product/Unavailable', [
                    'reason' => $blockReason,
                    'message' => $product->storefrontBlockMessage(),
                    'title' => $product->title,
                ]);
            }
        }

        $product->load([
            'seller.sellerProfile',
            'images' => fn ($q) => $q->whereNull('variant_id')->orderByDesc('is_main')->orderBy('sort_order'),
            'attributeValues.attribute',
        ]);

        $variants = $product->variants()
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderByDesc('is_main')->orderBy('sort_order')])
            ->orderBy('price')
            ->get();

        if ($variants->isEmpty()) {
            $created = $product->variants()->create([
                'options' => ['Вариант' => 'Стандарт'],
                'price' => $product->min_price,
                'stock' => 0,
                'is_active' => true,
            ]);
            $created->load(['images' => fn ($q) => $q->orderByDesc('is_main')->orderBy('sort_order')]);
            $variants = collect([$created]);
        }

        $galleryUrls = $product->images
            ->map(fn ($img) => Product::normalizeListingUrl($img->url))
            ->filter()
            ->values()
            ->all();

        if ($galleryUrls === []) {
            $galleryUrls = ['/img/products/default.png'];
        }

        $buildGalleryForVariant = function (ProductVariant $v) use ($galleryUrls): array {
            $fromVariant = $v->images
                ->map(fn ($img) => Product::normalizeListingUrl($img->url))
                ->filter()
                ->values()
                ->all();

            $merged = array_values(array_unique(array_merge($fromVariant, $galleryUrls)));

            return $merged !== [] ? $merged : ['/img/products/default.png'];
        };

        $favoriteVariantIds = [];
        $hasProductFavorite = false;
        $purchasable = $product->isPurchasable();

        if ($user) {
            $favoriteRows = DB::table('favorites')
                ->where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->get(['variant_id']);

            $favoriteVariantIds = $favoriteRows
                ->pluck('variant_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
            $hasProductFavorite = $favoriteRows->contains(fn ($row) => $row->variant_id === null);
        }

        $commissionService = app(CommissionService::class);

        $rows = [];
        foreach ($variants as $v) {
            $variantGallery = $buildGalleryForVariant($v);
            $variantPrice = (float) $v->price;
            $variantOld = $v->old_price !== null ? (float) $v->old_price : null;
            $showOld = $variantOld !== null && $variantOld > $variantPrice;
            $commission = $commissionService->calculateForProduct($product, $variantPrice, 1);

            $inCart = false;
            $hasOrdered = false;
            $existingOrderId = null;
            if ($user && $purchasable) {
                $inCart = Cart::where('user_id', $user->id)
                    ->where('variant_id', $v->id)
                    ->exists();
                $hasOrdered = OrderItem::where('variant_id', $v->id)
                    ->whereHas('order', function ($query) use ($user) {
                        $query->where('buyer_id', $user->id)
                            ->whereNotIn('status', [Order::STATUS_ISSUED, Order::STATUS_CANCELED, Order::STATUS_REFUSED]);
                    })
                    ->exists();
                $existingOrderId = OrderItem::where('variant_id', $v->id)
                    ->whereHas('order', function ($query) use ($user) {
                        $query->where('buyer_id', $user->id)
                            ->whereNotIn('status', [Order::STATUS_ISSUED, Order::STATUS_CANCELED, Order::STATUS_REFUSED]);
                    })
                    ->value('order_id');
            }

            $rows[] = [
                'id' => $v->id,
                'label' => $v->displayLabel(),
                'sku' => $v->sku,
                'price' => $variantPrice,
                'old_price' => $showOld ? $variantOld : null,
                'discount_label_percent' => $showOld
                    ? (int) round((($variantOld - $variantPrice) / $variantOld) * 100)
                    : null,
                'stock' => (int) $v->stock,
                'gallery' => $variantGallery,
                'image' => $variantGallery[0] ?? null,
                'in_cart' => $inCart,
                'is_favorite' => in_array((int) $v->id, $favoriteVariantIds, true),
                'has_ordered' => $hasOrdered,
                'existing_order_id' => $existingOrderId,
                'purchase_breakdown' => [
                    'quantity' => 1,
                    'item_price' => $variantPrice,
                    'payable_total' => $variantPrice,
                    'buyer_service_fee' => 0.0,
                    'platform_commission_percent' => $commission['percent'],
                    'platform_keeps_from_seller' => $commission['commission'],
                    'seller_receives' => $commission['seller_payout'],
                ],
            ];
        }

        $requestedVariantId = (int) $request->query('variant', 0);
        $selectedRow = collect($rows)->firstWhere('id', $requestedVariantId) ?? $rows[0];
        $selectedVariant = $variants->firstWhere('id', $selectedRow['id']);

        $mainImage = $selectedRow['image'] ?? null;

        $baseSpecs = $product->attributeValues
            ->map(fn ($av) => [
                'name' => $av->attribute?->name ?? 'Характеристика',
                'value' => (string) ($av->value ?? ''),
            ])
            ->filter(fn ($row) => $row['value'] !== '')
            ->values()
            ->all();

        $variantOptions = [];
        if ($selectedVariant) {
            $opts = $selectedVariant->options;
            if (is_array($opts)) {
                foreach ($opts as $key => $val) {
                    if (is_string($val) && $val !== '') {
                        $variantOptions[] = [
                            'name' => is_string($key) ? $key : 'Параметр',
                            'value' => $val,
                        ];
                    }
                }
            }
        }

        $specs = array_merge($baseSpecs, $variantOptions);

        $variantLabel = $selectedVariant?->displayLabel() ?? '';
        $displayTitle = $product->title;
        if ($variantLabel !== '' && $variantLabel !== 'Вариант #'.$selectedRow['id']) {
            $displayTitle = $product->title.' — '.$variantLabel;
        }

        $reviews = $product->reviews()
            ->where('is_moderated', true)
            ->with('user:id,name,avatar')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $userVotes = [];
        if ($user) {
            $userVotes = ReviewVote::query()
                ->where('user_id', $user->id)
                ->whereIn('review_id', $reviews->pluck('id'))
                ->pluck('vote', 'review_id')
                ->all();
        }

        $reviewsAvg = $product->reviews()->where('is_moderated', true)->avg('rating');
        $reviewsCount = $product->reviews()->where('is_moderated', true)->count();

        $reviewsList = $reviews->map(fn ($r) => [
            'id' => $r->id,
            'rating' => (int) $r->rating,
            'comment' => $r->comment,
            'created_at' => $r->created_at?->format('d.m.Y'),
            'likes_count' => (int) ($r->likes_count ?? 0),
            'dislikes_count' => (int) ($r->dislikes_count ?? 0),
            'user_vote' => $userVotes[$r->id] ?? null,
            'user_name' => $r->user?->name ?? 'Покупатель',
            'user_avatar' => $r->user?->avatar ? Product::normalizeListingUrl($r->user->avatar) : null,
        ])->values()->all();

        $isFavorite = (bool) ($selectedRow['is_favorite'] ?? false);
        if (! $isFavorite && $variants->count() === 1) {
            $isFavorite = $hasProductFavorite;
        }

        $seller = $product->seller;

        $product->variant_id = $selectedRow['id'];
        $product->variant_price = $selectedRow['price'];
        $product->variant_old_price = $selectedRow['old_price'];
        $product->variant_stock = $selectedRow['stock'];
        $product->in_cart = $selectedRow['in_cart'];
        $product->is_favorite = $isFavorite;
        $product->favorite_variant_ids = $favoriteVariantIds;
        $product->image = $mainImage;
        $product->gallery = $selectedRow['gallery'];
        $product->specs = $specs;
        $product->display_title = $displayTitle;
        $product->variant_label = $variantLabel;
        $product->variant_sku = $selectedRow['sku'] ?? null;
        $product->variant_options = $variantOptions;
        $product->reviews_list = $reviewsList;
        $product->reviews_avg_rating = $reviewsAvg !== null ? round((float) $reviewsAvg, 1) : null;
        $product->reviews_total = (int) $reviewsCount;
        $product->discount_label_percent = $selectedRow['discount_label_percent'];
        $product->purchase_breakdown = $selectedRow['purchase_breakdown'];
        $product->variants_catalog = $rows;
        $product->selected_variant_id = $selectedRow['id'];

        $lead = trim((string) ($product->short_description ?? ''));
        if ($lead === '') {
            $plain = trim(strip_tags((string) ($product->description ?? '')));
            $lead = $plain !== '' ? Str::limit($plain, 720, '…') : '';
        }
        $product->setAttribute('lead_text', $lead);

        $sellerPayload = $seller ? [
            'id' => $seller->id,
            'name' => $seller->sellerProfile?->shop_name ?? $seller->name,
            'avatar' => $seller->avatar ? Product::normalizeListingUrl($seller->avatar) : null,
            'rating' => $seller->sellerProfile ? (float) $seller->sellerProfile->rating : null,
            'total_sales' => (int) ($seller->sellerProfile?->total_sales ?? 0),
            'verified' => (bool) $seller->sellerProfile,
        ] : null;

        $product->unsetRelation('images');
        $product->unsetRelation('attributeValues');
        $product->unsetRelation('seller');

        $pickupPoints = PickupPoint::query()
            ->active()
            ->with('region')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (PickupPoint $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'address' => $p->address,
                'region' => $p->region?->name,
                'label' => $p->title.($p->region ? ' — '.$p->region->name : ''),
                'delivery_hours' => $p->region?->delivery_hours,
            ]);

        $canPurchase = $purchasable && (int) $selectedRow['stock'] > 0;

        return Inertia::render('Product/Show', [
            'nftUser' => [
                'owner_name' => ($sellerPayload ?? [])['name'] ?? 'Продавец',
                'owner_avatar' => ($sellerPayload ?? [])['avatar'] ?? null,
            ],
            'product' => $product,
            'seller' => $sellerPayload,
            'can_purchase' => $canPurchase,
            'storefront_block' => $blockReason ? [
                'reason' => $blockReason,
                'message' => $product->storefrontBlockMessage(),
                'is_preview' => true,
            ] : null,
            'canPayWithWallet' => $canPurchase && $user !== null && (float) $user->balance >= (float) $selectedRow['price'],
            'walletBalance' => $user ? (float) $user->balance : 0,
            'auth' => ['user' => $user],
            'hasOrdered' => $selectedRow['has_ordered'],
            'existingOrderId' => $selectedRow['existing_order_id'],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'in_cart' => session('in_cart'),
            ],
            'pickupPoints' => $pickupPoints,
        ]);
    }

    protected function normalizePublicUrl(?string $url): ?string
    {
        return Product::normalizeListingUrl($url);
    }
    public function create()
    {
        $user = Auth()->user();
        if ($user->is_blocked === 1) {
            return redirect()->back();
        } else {
            return Inertia::render('Product/Create', [
                'categories' => Category::all(['id', 'name', 'img']),
            ]);
        }
    }

    public function store(Request $request)
    {
        $user = Auth()->user();
        if ($user->is_blocked === 1 || !$user->phone) {
            return redirect()->back();
        } else {
            $request->validate(
                [
                    'title' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                    'price' => 'required|numeric|min:0',
                    'tags' => 'nullable|string',
                    'category_id' => 'required|exists:categories,id',
                ],
                [
                    'title.required' => 'Название обязательно',
                    'title.max' => 'Название не должно превышать 100 символов',
                    'image.required' => 'Изображение обязательно',
                    'image.image' => 'Файл должен быть изображением',
                    'price.required' => 'Цена обязательна',
                    'price.min' => 'Цена не может быть отрицательной',
                    'category_id.exists' => 'Выбрана несуществующая категория',
                ]
            );
            $userId = auth()->id();
            $uploadDir = public_path("img/{$userId}");

            if (!File::exists($uploadDir)) {
                File::makeDirectory($uploadDir, 0755, true);
            }

            $extension = $request->file('image')->getClientOriginalExtension();

            // Находим следующий номер: img1.jpg, img2.png и т.д.
            $counter = 1;
            do {
                $filename = "img{$counter}.{$extension}";
                $fullPath = "{$uploadDir}/{$filename}";
                $counter++;
            } while (File::exists($fullPath));

            $request->file('image')->move($uploadDir, $filename);

            $relativePath = "/img/{$userId}/{$filename}";

            Product::create([
                'title' => $request->title,
                'description' => $request->description,
                'image' => $relativePath,
                'price' => $request->price,
                'previous_price' => $request->price,
                'tags' => $request->tags,
                'status' => 'moderation',
                'user_id' => $userId,
                'category_id' => $request->category_id,
            ]);

            return redirect()->route('profile')->with('success', 'NFT успешно создан!');
        }
    }

    // ProductController.php

    public function edit(Product $product)
    {
        $user = Auth()->user();
        if (auth()->id() !== $product->user_id || $user->is_blocked === 1) {
            return redirect()->route('nft.show', $product)->with('error', 'Доступ запрещён');
        }

        return Inertia::render('Product/Edit', [
            'nft' => $product,
            'auth' => ['user' => auth()->user()],
        ]);
    }
    public function stopSelling(Product $product)
    {
        if (auth()->id() !== $product->user_id) {
            return back()->with('error', 'Вы не владелец');
        }

        $product->update(['status' => 'sold']);

        return back()->with('success', 'NFT снят с продажи');
    }
    public function update(Request $request, Product $product)
    {
        if (auth()->id() !== $product->user_id) {
            return back()->with('error', 'Вы не владелец');
        }

        $request->validate([
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $product->update(
            [
                'price' => $request->price,
                'description' => $request->description,
                'previous_price' => $product->price,
                'status' => 'moderation',
            ],
            [
                'price.required' => 'Цена обязательна',
                'price.min' => 'Цена не может быть отрицательной',
                'category_id.exists' => 'Выбрана несуществующая категория',
            ]
        );

        return redirect()->route('nft.show', $product)->with('success', 'NFT выставлен на продажу!');
    }

    public function destroy(Request $request, $id)
    {
        $userId = Product::where('id', $id)->value('user_id');
        Product::where('id', $id)
            ->delete();
        return redirect('/users/' . $userId)
            ->with('success', 'NFT удалён');
    }
}
