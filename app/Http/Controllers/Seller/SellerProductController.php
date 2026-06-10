<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductAttributeValue;
use App\Models\Promotion;
use App\Models\User;
use App\Services\SellerPromotionEligibilityService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use Inertia\Inertia;

class SellerProductController extends Controller
{
    private function syncVariantImages(Request $request, Product $product, ProductVariant $variant, int $variantIndex, string $folder, int $userId, ?string $mainImageKey = null): void
    {
        $newImageIdsByIndex = [];
        $newSortOrder = (int) ProductImage::where('product_id', $product->id)
            ->where('variant_id', $variant->id)
            ->max('sort_order');

        for ($imgIndex = 0; $imgIndex < 10; $imgIndex++) {
            $galleryKey = 'variant_gallery_' . $variantIndex . '_' . $imgIndex;
            $legacyAdditionalKey = 'additional_image_' . $variantIndex . '_' . $imgIndex;
            $key = $request->hasFile($galleryKey) ? $galleryKey : $legacyAdditionalKey;
            if (!$request->hasFile($key)) {
                continue;
            }
            $image = $request->file($key);
            $imageName = time() . '_v' . $variantIndex . '_g' . $imgIndex . '_' . Str::random(5) . '.' . $image->extension();
            $image->move($folder, $imageName);
            $created = ProductImage::create([
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'url' => '/img/products/' . $userId . '/' . $imageName,
                'sort_order' => ++$newSortOrder,
                'is_main' => false,
            ]);
            $newImageIdsByIndex[$imgIndex] = $created->id;
        }

        $remainingImages = ProductImage::where('product_id', $product->id)
            ->where('variant_id', $variant->id)
            ->orderBy('sort_order')
            ->get();

        if ($remainingImages->isEmpty()) {
            throw new \InvalidArgumentException('У каждого варианта должно быть хотя бы одно фото');
        }

        $this->setVariantMainImage($product, $variant, $mainImageKey, $newImageIdsByIndex);
    }

    /**
     * @param  array<int, int>  $newImageIdsByIndex
     */
    private function setVariantMainImage(
        Product $product,
        ProductVariant $variant,
        ?string $mainImageKey,
        array $newImageIdsByIndex = [],
    ): void {
        $remainingImages = ProductImage::where('product_id', $product->id)
            ->where('variant_id', $variant->id)
            ->orderBy('sort_order')
            ->get();

        if ($remainingImages->isEmpty()) {
            return;
        }

        $targetMainId = null;
        if (is_string($mainImageKey) && str_starts_with($mainImageKey, 'existing:')) {
            $existingId = (int) substr($mainImageKey, 9);
            if ($existingId > 0 && $remainingImages->contains(fn($img) => (int) $img->id === $existingId)) {
                $targetMainId = $existingId;
            }
        }
        if ($targetMainId === null && is_string($mainImageKey) && str_starts_with($mainImageKey, 'new:')) {
            $newIdx = (int) substr($mainImageKey, 4);
            $mapped = $newImageIdsByIndex[$newIdx] ?? null;
            if ($mapped !== null) {
                $targetMainId = (int) $mapped;
            }
        }
        if ($targetMainId === null) {
            $targetMainId = (int) $remainingImages->first()->id;
        }

        $currentMain = $remainingImages->firstWhere('is_main', true);
        if ($currentMain && (int) $currentMain->id === $targetMainId) {
            return;
        }

        ProductImage::where('product_id', $product->id)
            ->where('variant_id', $variant->id)
            ->update(['is_main' => false]);
        ProductImage::where('product_id', $product->id)
            ->where('variant_id', $variant->id)
            ->where('id', $targetMainId)
            ->update(['is_main' => true, 'sort_order' => 0]);
    }

    /**
     * Из multipart FormData вложенные массивы часто не доходят до PHP — мёрджим JSON-поля до валидации.
     * Используется при создании и обновлении товара.
     */
    private function mergeSellerProductEditPayload(Request $request): void
    {
        $rawVariants = $request->input('variants_json');
        if (is_string($rawVariants)) {
            $rawVariants = trim($rawVariants);
            if ($rawVariants !== '') {
                $decoded = json_decode($rawVariants, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $request->merge(['variants' => $decoded]);
                }
            }
        }

        $rawAttrs = $request->input('attributes_json');
        if (is_string($rawAttrs)) {
            $rawAttrs = trim($rawAttrs);
            if ($rawAttrs !== '') {
                $decoded = json_decode($rawAttrs, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $request->merge(['attributes' => $decoded]);
                }
            }
        }

        $rawRemove = $request->input('remove_image_ids_json');
        if (is_string($rawRemove)) {
            $rawRemove = trim($rawRemove);
            if ($rawRemove !== '') {
                $decoded = json_decode($rawRemove, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $request->merge(['remove_image_ids' => $decoded]);
                }
            }
        }

        $rawPromotion = $request->input('promotion_json');
        if (is_string($rawPromotion)) {
            $rawPromotion = trim($rawPromotion);
            if ($rawPromotion !== '') {
                $decoded = json_decode($rawPromotion, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $request->merge(['promotion' => $decoded]);
                }
            }
        }
    }

    public function create()
    {
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->with([
                'children.attributes'
            ])
            ->get();

        return Inertia::render('Seller/Products/Create', [
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $this->mergeSellerProductEditPayload($request);

        $request->validate([
            'title' => 'required|string|max:200',
            'short_description' => 'required|string|max:500',
            'description' => 'required|string|max:20000',
            'category_id' => 'required|exists:categories,id',
            'variants' => 'required|array|min:1',
            'variants.*.price' => 'required|numeric|min:0.01',
            'variants.*.stock' => 'required|integer|min:0',
        ], [
            'title.required' => 'Необходимо указать название товара.',
            'title.string' => 'Название товара должно быть текстом.',
            'title.max' => 'Название товара не должно превышать 200 символов.',
            'short_description.required' => 'Необходимо указать краткое описание.',
            'short_description.string' => 'Краткое описание должно быть текстом.',
            'short_description.max' => 'Краткое описание не должно превышать 500 символов.',
            'description.required' => 'Необходимо указать полное описание.',
            'description.string' => 'Полное описание должно быть текстом.',
            'description.max' => 'Полное описание не должно превышать 20000 символов.',
            'category_id.required' => 'Необходимо выбрать категорию.',
            'category_id.exists' => 'Выбранная категория не существует.',
            'variants.required' => 'Необходимо добавить хотя бы один вариант товара.',
            'variants.array' => 'Варианты товара должны быть массивом.',
            'variants.min' => 'Добавьте хотя бы один вариант товара.',
            'variants.*.price.required' => 'Необходимо указать цену варианта.',
            'variants.*.price.numeric' => 'Цена должна быть числом.',
            'variants.*.price.min' => 'Цена должна быть не менее 0.01.',
            'variants.*.stock.required' => 'Необходимо указать количество на складе.',
            'variants.*.stock.integer' => 'Количество должно быть целым числом.',
            'variants.*.stock.min' => 'Количество не может быть отрицательным.',
        ]);

        $user = Auth::user();

        foreach (array_keys($request->variants ?? []) as $variantIndex) {
            $hasLegacyMain = $request->hasFile('variant_image_' . $variantIndex);
            $hasGallery = false;
            for ($imgIndex = 0; $imgIndex < 10; $imgIndex++) {
                if ($request->hasFile('variant_gallery_' . $variantIndex . '_' . $imgIndex) || $request->hasFile('additional_image_' . $variantIndex . '_' . $imgIndex)) {
                    $hasGallery = true;
                    break;
                }
            }
            if (!$hasLegacyMain && !$hasGallery) {
                return back()->withErrors([
                    "variants.{$variantIndex}.image" => 'Загрузите фото для каждого варианта',
                ])->withInput();
            }
        }

        $allowedAttributeIds = CategoryAttribute::query()
            ->where('category_id', (int) $request->category_id)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        try {
            DB::beginTransaction();

            $minPrice = (float) collect($request->variants)->min(
                fn($v) => (float) ($v['price'] ?? 0)
            );

            $product = Product::create([
                'seller_id' => $user->id,
                'category_id' => $request->category_id,
                'title' => $request->title,
                'description' => $request->description,
                'short_description' => $request->short_description,
                'min_price' => $minPrice,
                'status' => 'moderation',
                'is_on_action' => false,
            ]);

            $folder = public_path('img/products/' . $user->id);
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            /*
            |--------------------------------------------------------------------------
            | Variants
            |--------------------------------------------------------------------------
            */

            foreach ($request->variants as $variantIndex => $variant) {
                $options = $variant['options'] ?? [];
                if (is_string($options)) {
                    $decoded = json_decode($options, true);
                    $options = is_array($decoded) ? $decoded : [];
                }
                if (!is_array($options)) {
                    $options = [];
                }

                $pv = ProductVariant::create([
                    'product_id' => $product->id,
                    'options' => $options,
                    'price' => $variant['price'],
                    'old_price' => null,
                    'stock' => (int) $variant['stock'],
                    'weight_grams' => isset($variant['weight_grams']) ? (int) $variant['weight_grams'] : null,
                    'is_active' => true,
                ]);

                if ($request->hasFile('variant_image_' . $variantIndex)) {
                    $legacyMain = $request->file('variant_image_' . $variantIndex);
                    $legacyName = time() . '_v' . $variantIndex . '_legacy_' . Str::random(5) . '.' . $legacyMain->extension();
                    $legacyMain->move($folder, $legacyName);
                    ProductImage::create([
                        'product_id' => $product->id,
                        'variant_id' => $pv->id,
                        'url' => '/img/products/' . $user->id . '/' . $legacyName,
                        'sort_order' => 1,
                        'is_main' => false,
                    ]);
                }
                $this->syncVariantImages($request, $product, $pv, $variantIndex, $folder, (int) $user->id, $variant['main_image_key'] ?? null);
            }

            /*
            |--------------------------------------------------------------------------
            | Attributes (только id из выбранной категории — иначе FK и откат транзакции)
            |--------------------------------------------------------------------------
            */

            foreach ($request->input('attributes', []) as $attributeId => $value) {
                $aid = (int) $attributeId;
                if ($aid < 1 || !in_array($aid, $allowedAttributeIds, true)) {
                    continue;
                }
                if ($value === '' || $value === null) {
                    continue;
                }

                ProductAttributeValue::create([
                    'product_id' => $product->id,
                    'attribute_id' => $aid,
                    'value' => is_array($value) ? json_encode($value) : $value,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('seller.products')
                ->with('success', 'Товар успешно создан');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function edit(Product $product)
    {
        $user = Auth::user();

        if ($product->seller_id !== $user->id) {
            abort(403);
        }

        $product->load([
            'category.parent',
            'category.attributes',
            'attributeValues',
            'variants' => fn($q) => $q->with(['images' => fn($qi) => $qi->orderBy('sort_order')])->orderBy('id'),
        ]);

        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children.attributes'])
            ->get();

        $attributesPayload = [];
        foreach ($product->attributeValues as $pav) {
            $attributesPayload[$pav->attribute_id] = $pav->value;
        }

        $variantsPayload = $product->variants->map(function (ProductVariant $v) {
            $opts = $v->options;
            if (is_string($opts)) {
                $decoded = json_decode($opts, true);
                $opts = is_array($decoded) ? $decoded : [];
            }

            $variantImage = $v->images->firstWhere('is_main', true) ?? $v->images->first();
            $allImages = $v->images
                ->values()
                ->map(fn($img) => [
                    'id' => $img->id,
                    'url' => $img->url,
                    'is_main' => (bool) $img->is_main,
                    'sort_order' => $img->sort_order,
                ])
                ->all();
            $additionalImages = $v->images
                ->where('is_main', false)
                ->values()
                ->map(fn($img) => [
                    'id' => $img->id,
                    'url' => $img->url,
                    'sort_order' => $img->sort_order,
                ])
                ->all();

            return [
                'id' => $v->id,
                'options' => $opts,
                'price' => (string) $v->price,
                'old_price' => $v->old_price !== null ? (string) $v->old_price : '',
                'stock' => (string) $v->stock,
                'image_url' => $variantImage?->url ?? null,
                'image_id' => $variantImage?->id ?? null,
                'images' => $allImages,
                'additional_images' => $additionalImages,
            ];
        })->values()->all();

        $eligibility = app(SellerPromotionEligibilityService::class);
        $variantsForRules = $eligibility->variantsPayloadFromProduct($product);
        $eligibleBadges = array_values($eligibility->eligibleBadges($product, $variantsForRules));
        $sellerPromotion = $this->findSellerPromotionForProduct($product, (int) $user->id);

        return Inertia::render('Seller/Products/Edit', [
            'product' => [
                'id' => $product->id,
                'status' => $product->status,
                'moderation_comment' => $product->moderation_comment,
                'created_at' => $product->created_at?->toIso8601String(),
            ],
            'leafCategory' => $product->category,
            'parentCategory' => $product->category->parent,
            'categories' => $categories,
            'eligible_badges' => $eligibleBadges,
            'initial' => [
                'title' => $product->title,
                'short_description' => $product->short_description ?? '',
                'description' => $product->description ?? '',
                'category_id' => $product->category_id,
                'attributes' => $attributesPayload,
                'variants' => $variantsPayload,
                'promotion' => $this->formatPromotionForEdit($sellerPromotion),
            ],
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $user = Auth::user();

        if ($product->seller_id !== $user->id) {
            abort(403);
        }

        $this->mergeSellerProductEditPayload($request);

        $request->validate([
            'title' => 'required|string|max:200',
            'short_description' => 'required|string|max:500',
            'description' => 'required|string|max:20000',
            'variants' => 'required|array|min:1',
            'variants.*.id' => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->where('product_id', $product->id),
            ],
            'variants.*.price' => 'required|numeric|min:0.01',
            'variants.*.stock' => 'required|integer|min:0',
            'variants.*.remove_image_ids' => 'nullable|array',
            'variants.*.remove_image_ids.*' => [
                'integer',
                Rule::exists('product_images', 'id')->where('product_id', $product->id),
            ],
        ], [
            'title.required' => 'Необходимо указать название товара.',
            'title.string' => 'Название товара должно быть текстом.',
            'title.max' => 'Название товара не должно превышать 200 символов.',
            'short_description.required' => 'Необходимо указать краткое описание.',
            'short_description.string' => 'Краткое описание должно быть текстом.',
            'short_description.max' => 'Краткое описание не должно превышать 500 символов.',
            'description.required' => 'Необходимо указать полное описание.',
            'description.string' => 'Полное описание должно быть текстом.',
            'description.max' => 'Полное описание не должно превышать 20000 символов.',
            'variants.required' => 'Необходимо добавить хотя бы один вариант товара.',
            'variants.array' => 'Варианты товара должны быть массивом.',
            'variants.min' => 'Добавьте хотя бы один вариант товара.',
            'variants.*.id.integer' => 'ID варианта должен быть числом.',
            'variants.*.id.exists' => 'Выбранный вариант товара не существует.',
            'variants.*.price.required' => 'Необходимо указать цену варианта.',
            'variants.*.price.numeric' => 'Цена должна быть числом.',
            'variants.*.price.min' => 'Цена должна быть не менее 0.01.',
            'variants.*.stock.required' => 'Необходимо указать количество на складе.',
            'variants.*.stock.integer' => 'Количество должно быть целым числом.',
            'variants.*.stock.min' => 'Количество не может быть отрицательным.',
            'variants.*.remove_image_ids.array' => 'Список ID удаляемых изображений должен быть массивом.',
            'variants.*.remove_image_ids.*.integer' => 'ID изображения должен быть числом.',
            'variants.*.remove_image_ids.*.exists' => 'Одно из выбранных изображений не существует.',
        ]);
        DB::beginTransaction();

        try {
            $product->load(['variants', 'attributeValues']);

            if (!$this->productUpdateRequiresModeration($product, $request)) {
                $this->applyMinorProductUpdate($product, $request, $user);
                DB::commit();

                return $this->redirectAfterSellerProductUpdate(
                    $product,
                    $request,
                    true,
                    'Изменения сохранены',
                );
            }

            $minPrice = (float) collect($request->variants)->min(
                fn($v) => (float) ($v['price'] ?? 0)
            );

            $product->update([
                'title' => $request->title,
                'short_description' => $request->short_description,
                'description' => $request->description,
                'min_price' => $minPrice,
                'status' => 'moderation',
                'is_on_action' => false,
            ]);

            $allowedAttributeIds = CategoryAttribute::query()
                ->where('category_id', $product->category_id)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();

            $submittedVariantIds = collect($request->variants)->pluck('id')->filter()->map(fn($id) => (int) $id)->values()->all();

            if ($submittedVariantIds !== []) {
                $product->variants()
                    ->whereNotIn('id', $submittedVariantIds)
                    ->get()
                    ->each(fn($v) => $v->delete());
            } else {
                $product->variants()->get()->each(fn($v) => $v->delete());
            }

            $folder = public_path('img/products/' . $user->id);
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            foreach ($request->variants as $variantIndex => $variant) {
                $options = $variant['options'] ?? [];
                if (is_string($options)) {
                    $decoded = json_decode($options, true);
                    $options = is_array($decoded) ? $decoded : [];
                }
                if (!is_array($options)) {
                    $options = [];
                }

                $newPrice = (float) $variant['price'];
                if (!empty($variant['id'])) {
                    $pv = ProductVariant::where('product_id', $product->id)->find($variant['id']);
                    if ($pv) {
                        $autoOldPrice = ((float) $pv->price !== $newPrice)
                            ? $pv->price
                            : $pv->old_price;

                        $pv->update([
                            'options' => $options,
                            'price' => $newPrice,
                            'old_price' => $autoOldPrice,
                            'stock' => (int) $variant['stock'],
                        ]);

                        foreach (($variant['remove_image_ids'] ?? []) as $imgId) {
                            $img = ProductImage::where('product_id', $product->id)
                                ->where('variant_id', $pv->id)
                                ->find($imgId);
                            if ($img) {
                                $img->delete();
                            }
                        }

                        if ($request->hasFile('variant_image_' . $variantIndex)) {
                            $legacyMain = $request->file('variant_image_' . $variantIndex);
                            $legacyName = time() . '_v' . $variantIndex . '_legacy_' . Str::random(5) . '.' . $legacyMain->extension();
                            $legacyMain->move($folder, $legacyName);
                            ProductImage::create([
                                'product_id' => $product->id,
                                'variant_id' => $pv->id,
                                'url' => '/img/products/' . $user->id . '/' . $legacyName,
                                'sort_order' => 1,
                                'is_main' => false,
                            ]);
                        }
                        $this->syncVariantImages($request, $product, $pv, $variantIndex, $folder, (int) $user->id, $variant['main_image_key'] ?? null);

                        continue;
                    }
                }

                $pv = ProductVariant::create([
                    'product_id' => $product->id,
                    'options' => $options,
                    'price' => $newPrice,
                    'old_price' => null,
                    'stock' => (int) $variant['stock'],
                    'is_active' => true,
                ]);

                if ($request->hasFile('variant_image_' . $variantIndex)) {
                    $legacyMain = $request->file('variant_image_' . $variantIndex);
                    $legacyName = time() . '_v' . $variantIndex . '_legacy_' . Str::random(5) . '.' . $legacyMain->extension();
                    $legacyMain->move($folder, $legacyName);
                    ProductImage::create([
                        'product_id' => $product->id,
                        'variant_id' => $pv->id,
                        'url' => '/img/products/' . $user->id . '/' . $legacyName,
                        'sort_order' => 1,
                        'is_main' => false,
                    ]);
                }
                $this->syncVariantImages($request, $product, $pv, $variantIndex, $folder, (int) $user->id, $variant['main_image_key'] ?? null);
            }

            foreach ($request->variants as $variantIndex => $variant) {
                if (empty($variant['id'])) {
                    continue;
                }
                $pv = ProductVariant::where('product_id', $product->id)->find($variant['id']);
                if (!$pv) {
                    continue;
                }
                $hasImage = $request->hasFile('variant_image_' . $variantIndex)
                    || ProductImage::where('product_id', $product->id)->where('variant_id', $pv->id)->exists();
                if (!$hasImage) {
                    throw new \InvalidArgumentException('У каждого варианта должно быть фото');
                }
            }

            foreach ($request->input('attributes', []) as $attributeId => $value) {
                $aid = (int) $attributeId;
                if ($aid < 1 || !in_array($aid, $allowedAttributeIds, true)) {
                    continue;
                }
                if ($value === '' || $value === null) {
                    continue;
                }

                ProductAttributeValue::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'attribute_id' => $aid,
                    ],
                    [
                        'value' => is_array($value) ? json_encode($value) : $value,
                    ]
                );
            }

            $product->refresh();
            $product->load('variants');
            $this->syncSellerPromotion($product, $request, $user);

            DB::commit();

            return $this->redirectAfterSellerProductUpdate(
                $product,
                $request,
                false,
                'Товар обновлён и снова отправлен на модерацию',
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Управление товаром (страница «Управление»)
    // -------------------------------------------------------------------------

    public function manage(Product $product)
    {
        $user = Auth::user();

        if ($product->seller_id !== $user->id) {
            abort(403);
        }

        $product->load([
            'category',
            'variants' => fn($q) => $q->orderBy('id'),
            'variants.images',
            'images' => fn($q) => $q->whereNull('variant_id')->orderBy('sort_order'),
        ]);

        $liveVariants = $product->variants->filter(fn($v) => !$v->trashed());
        $totalStock = $liveVariants->sum('stock');
        $activeCount = $liveVariants->where('is_active', true)->count();
        $hiddenCount = $liveVariants->where('is_active', false)->count();
        $totalViews = (int) $liveVariants->sum('views_count');
        $sellerPromotion = $this->findSellerPromotionForProduct($product, (int) $user->id);
        $promotionSummary = null;
        if ($sellerPromotion && $sellerPromotion->isCurrentlyActive()) {
            $promotionSummary = [
                'badge_label' => $sellerPromotion->badge_label,
                'ends_at' => $sellerPromotion->ends_at?->format('d.m.Y H:i'),
            ];
        }

        return Inertia::render('Seller/Products/Manage', [
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
                'status' => $product->status,
                'moderation_comment' => $product->moderation_comment,
                'is_listed' => (bool) $product->is_on_action,
                'category' => $product->category?->name ?? '—',
                'min_price' => (float) $product->min_price,
                'views_count' => $totalViews,
                'sales_count' => (int) ($product->sales_count ?? 0),
                'created_at' => $product->created_at?->format('d.m.Y') ?? '',
                'main_image' => $product->resolveListingImageUrl(),
                'total_stock' => $totalStock,
                'active_variants' => $activeCount,
                'hidden_variants' => $hiddenCount,
                'promotion' => $promotionSummary,
            ],
            'variants' => $liveVariants->map(function (ProductVariant $v) {
                $opts = $v->options;
                if (is_string($opts)) {
                    $opts = json_decode($opts, true) ?? [];
                }

                return [
                    'id' => $v->id,
                    'options' => is_array($opts) ? $opts : [],
                    'price' => (float) $v->price,
                    'old_price' => $v->old_price ? (float) $v->old_price : null,
                    'stock' => (int) $v->stock,
                    'views_count' => (int) ($v->views_count ?? 0),
                    'is_active' => (bool) $v->is_active,
                    'sku' => $v->sku,
                    'image_url' => $v->images->first()?->url ?? null,
                ];
            })->values()->all(),
        ]);
    }

    public function toggleVisibility(Product $product)
    {
        $user = Auth::user();

        if ($product->seller_id !== $user->id) {
            abort(403);
        }

        if ($product->status === 'hidden') {
            $product->update([
                'status' => 'approved',
                'is_on_action' => true,
            ]);
            $message = 'Товар снова показан на витрине';
        } elseif ($product->status === 'approved') {
            $product->update([
                'status' => 'hidden',
                'is_on_action' => false,
            ]);
            $message = 'Товар скрыт с витрины';
        } else {
            return back()->with('error', 'Скрыть или показать можно только одобренный или скрытый товар');
        }

        return back()->with('success', $message);
    }

    public function toggleVariant(Product $product, ProductVariant $variant)
    {
        $user = Auth::user();

        if ($product->seller_id !== $user->id || $variant->product_id !== $product->id) {
            abort(403);
        }

        $variant->update(['is_active' => !$variant->is_active]);

        return back()->with('success', 'Статус варианта обновлён');
    }

    public function updateVariantStock(Request $request, Product $product, ProductVariant $variant)
    {
        $user = Auth::user();

        if ($product->seller_id !== $user->id || $variant->product_id !== $product->id) {
            abort(403);
        }

        $request->validate([
            'stock' => 'required|integer|min:0',
        ], [
            'stock.required' => 'Необходимо указать количество на складе.',
            'stock.integer' => 'Количество должно быть целым числом.',
            'stock.min' => 'Количество не может быть отрицательным.',
        ]);
        $variant->update(['stock' => (int) $request->stock]);

        return back()->with('success', 'Остаток обновлён');
    }

    private function findSellerPromotionForProduct(Product $product, int $sellerId): ?Promotion
    {
        return Promotion::query()
            ->where('seller_id', $sellerId)
            ->where('created_by', Promotion::CREATED_BY_SELLER)
            ->whereHas('products', fn($q) => $q->where('products.id', $product->id))
            ->first();
    }

    /**
     * @return array{enabled: bool, badge_key: string, ends_at: string}
     */
    private function formatPromotionForEdit(?Promotion $promotion): array
    {
        if (!$promotion) {
            return [
                'enabled' => false,
                'badge_key' => '',
                'ends_at' => '',
            ];
        }

        return [
            'enabled' => $promotion->status === Promotion::STATUS_ACTIVE,
            'badge_key' => $this->badgeKeyFromPromotion($promotion) ?? '',
            'ends_at' => $promotion->ends_at?->format('Y-m-d\TH:i') ?? '',
        ];
    }

    private function badgeKeyFromPromotion(Promotion $promotion): ?string
    {
        foreach (config('seller_promotion_badges', []) as $key => $meta) {
            if (($meta['label'] ?? '') === $promotion->badge_label) {
                return $key;
            }
        }

        return null;
    }

    private function syncSellerPromotion(Product $product, Request $request, User $user): void
    {
        $promo = $request->input('promotion', []);
        if (!is_array($promo)) {
            $promo = [];
        }

        $enabled = filter_var($promo['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $existing = $this->findSellerPromotionForProduct($product, (int) $user->id);

        if (!$enabled) {
            if ($existing) {
                $existing->products()->detach($product->id);
                if ($existing->products()->count() === 0) {
                    $existing->delete();
                }
            }

            return;
        }

        $badgeKey = (string) ($promo['badge_key'] ?? '');
        $endsAt = $promo['ends_at'] ?? null;

        if ($badgeKey === '' || !$endsAt) {
            throw new \InvalidArgumentException('Выберите акцию и укажите, до какой даты она действует.');
        }

        $eligibility = app(SellerPromotionEligibilityService::class);
        $variantsPayload = $eligibility->variantsPayloadFromProduct($product);
        $eligibility->assertEligible($badgeKey, $product, $variantsPayload);

        $label = $eligibility->labelForKey($badgeKey);
        $starts = now();
        $ends = \Carbon\Carbon::parse($endsAt);
        if ($ends->lt($starts)) {
            throw new \InvalidArgumentException('Дата окончания акции не может быть раньше начала.');
        }

        $payload = [
            'badge_label' => $label,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => Promotion::STATUS_ACTIVE,
            'created_by' => Promotion::CREATED_BY_SELLER,
            'seller_id' => $user->id,
        ];

        if ($existing) {
            $existing->update($payload);
            $existing->products()->sync([$product->id]);

            return;
        }

        $promotion = Promotion::create($payload);
        $promotion->products()->sync([$product->id]);
    }

    /**
     * Сохранение без модерации: цена, остаток, акция — без смены статуса товара.
     */
    private function applyMinorProductUpdate(Product $product, Request $request, User $user): void
    {
        foreach ($request->variants as $variant) {
            if (empty($variant['id'])) {
                continue;
            }
            $pv = ProductVariant::where('product_id', $product->id)->find($variant['id']);
            if (!$pv) {
                continue;
            }

            $newPrice = (float) ($variant['price'] ?? 0);
            $autoOldPrice = ((float) $pv->price !== $newPrice)
                ? $pv->price
                : $pv->old_price;

            $pv->update([
                'stock' => (int) $variant['stock'],
                'price' => $newPrice,
                'old_price' => $autoOldPrice,
            ]);

            $this->setVariantMainImage($product, $pv, $variant['main_image_key'] ?? null);
        }

        $minPrice = (float) collect($request->variants)->min(
            fn($v) => (float) ($v['price'] ?? 0)
        );
        $product->update(['min_price' => $minPrice]);

        $product->refresh();
        $product->load('variants');
        $this->syncSellerPromotion($product, $request, $user);
    }

    /**
     * Модерация только при изменении карточки: название, описания, фото, атрибуты, варианты.
     */
    private function productUpdateRequiresModeration(Product $product, Request $request): bool
    {
        if (trim((string) $request->title) !== trim((string) $product->title)) {
            return true;
        }
        if (trim((string) $request->short_description) !== trim((string) ($product->short_description ?? ''))) {
            return true;
        }
        if (trim((string) $request->description) !== trim((string) ($product->description ?? ''))) {
            return true;
        }

        $submittedAttributes = $request->input('attributes', []);
        if (!is_array($submittedAttributes)) {
            $submittedAttributes = [];
        }
        if ($this->attributesChanged($product, $submittedAttributes)) {
            return true;
        }

        $existingVariants = $product->variants->keyBy('id');
        $submittedVariants = $request->variants ?? [];

        if (count($submittedVariants) !== $existingVariants->count()) {
            return true;
        }

        foreach ($submittedVariants as $variantIndex => $variant) {
            $variantId = (int) ($variant['id'] ?? 0);
            if ($variantId < 1 || !$existingVariants->has($variantId)) {
                return true;
            }

            $pv = $existingVariants->get($variantId);

            if (!$this->variantOptionsEqual($pv->options ?? [], $variant['options'] ?? [])) {
                return true;
            }

            if (!empty($variant['remove_image_ids'])) {
                return true;
            }

            if ($this->variantHasIncomingFiles($request, (int) $variantIndex)) {
                return true;
            }
        }

        return false;
    }

    private function attributesChanged(Product $product, array $submitted): bool
    {
        $existing = $product->attributeValues->keyBy('attribute_id');

        foreach ($existing as $attributeId => $pav) {
            $key = (string) $attributeId;
            $submittedVal = array_key_exists($attributeId, $submitted)
                ? $submitted[$attributeId]
                : (array_key_exists($key, $submitted) ? $submitted[$key] : null);

            if ($this->normalizeAttributeValue($submittedVal) !== $this->normalizeAttributeValue($pav->value)) {
                return true;
            }
        }

        foreach ($submitted as $attributeId => $value) {
            $aid = (int) $attributeId;
            if ($aid < 1) {
                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            if (!$existing->has($aid)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeAttributeValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>|string|null  $options
     */
    private function normalizeVariantOptions(array|string|null $options): array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($options)) {
            return [];
        }
        ksort($options);

        return $options;
    }

    private function variantOptionsEqual(mixed $left, mixed $right): bool
    {
        return $this->normalizeVariantOptions($left) === $this->normalizeVariantOptions($right);
    }

    private function variantHasIncomingFiles(Request $request, int $variantIndex): bool
    {
        if ($request->hasFile('variant_image_' . $variantIndex)) {
            return true;
        }

        for ($imgIndex = 0; $imgIndex < 10; $imgIndex++) {
            if ($request->hasFile('variant_gallery_' . $variantIndex . '_' . $imgIndex)) {
                return true;
            }
            if ($request->hasFile('additional_image_' . $variantIndex . '_' . $imgIndex)) {
                return true;
            }
        }

        return false;
    }

    private function redirectAfterSellerProductUpdate(
        Product $product,
        Request $request,
        bool $minorUpdate,
        string $message,
    ) {
        $highlightVariantId = (int) collect($request->variants)->pluck('id')->filter()->first();

        return redirect()
            ->route('seller.products')
            ->with('success', $message)
            ->with('highlight_variant_id', $highlightVariantId > 0 ? $highlightVariantId : null);
    }

}