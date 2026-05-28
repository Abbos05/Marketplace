<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesCatalogRecommendations;
use App\Models\Cart;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\PickupPoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\ReviewVote;
use App\Models\User;
use App\Services\CatalogFilterService;
use App\Services\CommissionService;
use App\Services\PromotionCatalogService;
use App\Services\ProductSimilarService;
use App\Services\ProductViewService;
use App\Services\ReviewImageService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class ProductController extends Controller
{
    use PreparesCatalogRecommendations;

    /**
     * Товары текущего продавца (кабинет).
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $sort   = $request->query('sort', 'newest');
        $search = trim((string) $request->query('search', ''));

        $query = ProductVariant::query()
            ->whereHas('product', function ($q) use ($status, $search) {
                $q->where('seller_id', Auth::id());
                if ($status !== 'all') {
                    $q->where('status', $status);
                }
                if ($search !== '') {
                    $q->where(function ($sq) use ($search) {
                        $sq->where('title', 'like', '%'.$search.'%')
                            ->orWhere('short_description', 'like', '%'.$search.'%');
                    });
                }
            })
            ->with([
                'images' => fn ($q) => $q->orderByDesc('is_main')->orderBy('sort_order'),
                'product' => fn ($q) => $q->with(['category']),
            ]);

        match ($sort) {
            'oldest'     => $query->orderBy('id'),
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'title'      => $query
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->orderBy('products.title')
                ->select('product_variants.*'),
            default      => $query->orderByDesc('id'),
        };

        $products = $query->paginate(50)->withQueryString();

        $productIds = $products->getCollection()
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        $variantCountsByProduct = ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, count(*) as cnt')
            ->groupBy('product_id')
            ->pluck('cnt', 'product_id');

        $promotionBadgesByProduct = $productIds->isNotEmpty()
            ? app(PromotionCatalogService::class)->badgesForProducts(
                Product::query()->whereIn('id', $productIds)->get()
            )
            : [];

        $products->getCollection()->transform(function (ProductVariant $variant) use ($variantCountsByProduct, $promotionBadgesByProduct) {
            $product = $variant->product;
            $productId = (int) ($product?->id ?? 0);
            $badges = $promotionBadgesByProduct[$productId] ?? [];

            return [
                'id'             => $variant->id,
                'product_id'     => $product?->id,
                'title'          => $product?->title ?? 'Товар',
                'variant_label'  => $variant->displayLabel(),
                'category'       => ['name' => $product?->category?->name],
                'min_price'      => (float) $variant->price,
                'status'         => $product?->status,
                'moderation_comment' => $product?->moderation_comment,
                'is_listed'      => (bool) ($product?->is_on_action ?? false),
                'variants_count' => (int) ($variantCountsByProduct[$product?->id] ?? 0),
                'total_stock'    => (int) $variant->stock,
                'main_image'     => $variant->images->first()?->url
                    ?? $product?->resolveListingImageUrl()
                    ?? '/img/products/default.png',
                'created_at'     => $variant->created_at?->format('d.m.Y'),
                'promotion_badges' => $badges,
                'promotion_label' => $badges[0]['label'] ?? null,
            ];
        });

        $counts = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.seller_id', Auth::id())
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('products.title', 'like', '%'.$search.'%')
                        ->orWhere('products.short_description', 'like', '%'.$search.'%');
                });
            })
            ->selectRaw('products.status as status, count(product_variants.id) as cnt')
            ->groupBy('products.status')
            ->pluck('cnt', 'status')
            ->toArray();

        $highlightVariantId = $request->session()->pull('highlight_variant_id');

        return Inertia::render('Seller/Products/Index', [
            'products'    => $products,
            'statusCounts'=> $counts,
            'filters'     => ['status' => $status, 'sort' => $sort, 'search' => $search],
            'highlightVariantId' => $highlightVariantId ? (int) $highlightVariantId : null,
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

        $buildGalleryForVariant = function (ProductVariant $v): array {
            $fromVariant = $v->images
                ->map(fn ($img) => Product::normalizeListingUrl($img->url))
                ->filter()
                ->values()
                ->all();

            return $fromVariant !== [] ? $fromVariant : ['/img/products/default.png'];
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

        if ($selectedVariant && $product->isPubliclyVisible()) {
            app(ProductViewService::class)->record($selectedVariant, $user);
        }

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

        $moderatedReviewsQuery = $product->reviews()->where('is_moderated', true);

        $reviews = (clone $moderatedReviewsQuery)
            ->with(['user:id,name,avatar', 'images'])
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

        $reviewsAvg = (clone $moderatedReviewsQuery)->avg('rating');
        $reviewsCount = (clone $moderatedReviewsQuery)->count();

        $distributionRows = (clone $moderatedReviewsQuery)
            ->selectRaw('rating, COUNT(*) as cnt')
            ->groupBy('rating')
            ->pluck('cnt', 'rating');

        $ratingDistribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $ratingDistribution[(string) $star] = (int) ($distributionRows[$star] ?? 0);
        }

        $reviewsWithPhotosCount = (clone $moderatedReviewsQuery)
            ->whereHas('images')
            ->count();

        $imageService = app(ReviewImageService::class);

        $reviewsList = $reviews->map(function ($r) use ($userVotes, $imageService) {
            $images = $imageService->mapImagesForFrontend($r->images);

            return [
                'id' => $r->id,
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'created_at' => $r->created_at?->format('d.m.Y'),
                'likes_count' => (int) ($r->likes_count ?? 0),
                'dislikes_count' => (int) ($r->dislikes_count ?? 0),
                'user_vote' => $userVotes[$r->id] ?? null,
                'user_name' => $r->user?->name ?? 'Покупатель',
                'user_avatar' => $r->user?->avatar ? Product::normalizeListingUrl($r->user->avatar) : null,
                'images' => $images,
                'has_photos' => count($images) > 0,
            ];
        })->values()->all();

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
        $product->reviews_rating_distribution = $ratingDistribution;
        $product->reviews_with_photos_count = (int) $reviewsWithPhotosCount;

        $similarProducts = app(ProductSimilarService::class)->forProduct($product);
        Product::enrichForCatalog($similarProducts);
        app(CatalogFilterService::class)->markFavorites($similarProducts);
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

        $product->setAttribute('promotion_badges', Promotion::query()
            ->active()
            ->whereHas('products', fn ($q) => $q->where('products.id', $product->id))
            ->get(['badge_label'])
            ->map(fn (Promotion $p) => [
                'label' => $p->badge_label,
                'title' => $p->badge_label,
            ])
            ->values()
            ->all());

        $sellerPayload = $seller ? [
            'id' => $seller->id,
            'name' => $seller->sellerProfile?->shop_name ?? $seller->name,
            'avatar' => $seller->avatar ? Product::normalizeListingUrl($seller->avatar) : null,
            'rating' => $seller->sellerProfile ? (float) $seller->sellerProfile->rating : null,
            'total_sales' => (int) ($seller->sellerProfile?->total_sales ?? 0),
            'verified' => (bool) $seller->sellerProfile,
        ] : null;

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
            'product' => $product,
            'similarProducts' => $similarProducts,
            'seller' => $sellerPayload,
            'can_purchase' => $canPurchase,
            'storefront_block' => $blockReason ? [
                'reason' => $blockReason,
                'message' => $product->storefrontBlockMessage(),
                'is_preview' => true,
            ] : null,
            'canPayWithWallet' => $canPurchase && $user !== null && (float) $user->balance >= (float) $selectedRow['price'],
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

            return redirect()->route('profile')->with('success', 'Product успешно создан!');
        }
    }

}
