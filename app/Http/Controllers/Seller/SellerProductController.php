<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductAttributeValue;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use Inertia\Inertia;

class SellerProductController extends Controller
{
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
            'images' => 'nullable|array|max:10',
            'images.*' => 'nullable|image|max:10240',
            'variants' => 'required|array|min:1',
            'variants.*.price' => 'required|numeric|min:0.01',
            'variants.*.stock' => 'required|integer|min:0',
        ]);

        $user = Auth::user();

        foreach (array_keys($request->variants ?? []) as $variantIndex) {
            if (! $request->hasFile('variant_image_'.$variantIndex)) {
                return back()->withErrors([
                    "variants.{$variantIndex}.image" => 'Загрузите фото для каждого варианта',
                ])->withInput();
            }
        }

        $allowedAttributeIds = CategoryAttribute::query()
            ->where('category_id', (int) $request->category_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        try {
            DB::beginTransaction();

            $minPrice = (float) collect($request->variants)->min(
                fn ($v) => (float) ($v['price'] ?? 0)
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

            $folder = public_path('img/products/'.$user->id);
            if (! file_exists($folder)) {
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
                if (! is_array($options)) {
                    $options = [];
                }

                $pv = ProductVariant::create([
                    'product_id'   => $product->id,
                    'options'      => $options,
                    'price'        => $variant['price'],
                    'old_price'    => null,
                    'stock'        => (int) $variant['stock'],
                    'weight_grams' => isset($variant['weight_grams']) ? (int) $variant['weight_grams'] : null,
                    'is_active'    => true,
                ]);

                $variantImageKey = 'variant_image_'.$variantIndex;
                if ($request->hasFile($variantImageKey)) {
                    $vImg     = $request->file($variantImageKey);
                    $vImgName = time().'_v'.$variantIndex.'_'.Str::random(5).'.'.$vImg->extension();
                    $vImg->move($folder, $vImgName);
                    ProductImage::create([
                        'product_id' => $product->id,
                        'variant_id' => $pv->id,
                        'url'        => '/img/products/'.$user->id.'/'.$vImgName,
                        'sort_order' => 0,
                        'is_main'    => true,
                    ]);
                }
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $imageName = time().'_'.$index.'_'.Str::random(5).'.'.$image->extension();
                    $image->move($folder, $imageName);
                    $relPath = 'img/products/'.$user->id.'/'.$imageName;
                    ProductImage::create([
                        'product_id' => $product->id,
                        'url' => '/'.$relPath,
                        'sort_order' => $index + 1,
                        'is_main' => false,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Attributes (только id из выбранной категории — иначе FK и откат транзакции)
            |--------------------------------------------------------------------------
            */

            foreach ($request->attributes ?? [] as $attributeId => $value) {
                $aid = (int) $attributeId;
                if ($aid < 1 || ! in_array($aid, $allowedAttributeIds, true)) {
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
            'variants' => fn ($q) => $q->with(['images' => fn ($qi) => $qi->orderBy('sort_order')])->orderBy('id'),
            'images' => fn ($q) => $q->whereNull('variant_id')->orderBy('sort_order'),
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

            $variantImage = $v->images->first();

            return [
                'id'        => $v->id,
                'options'   => $opts,
                'price'     => (string) $v->price,
                'old_price' => $v->old_price !== null ? (string) $v->old_price : '',
                'stock'     => (string) $v->stock,
                'image_url' => $variantImage?->url ?? null,
                'image_id'  => $variantImage?->id ?? null,
            ];
        })->values()->all();

        return Inertia::render('Seller/Products/Edit', [
            'product' => [
                'id' => $product->id,
                'status' => $product->status,
                'moderation_comment' => $product->moderation_comment,
            ],
            'leafCategory' => $product->category,
            'parentCategory' => $product->category->parent,
            'categories' => $categories,
            'initial' => [
                'title' => $product->title,
                'short_description' => $product->short_description ?? '',
                'description' => $product->description ?? '',
                'category_id' => $product->category_id,
                'attributes' => $attributesPayload,
                'variants' => $variantsPayload,
            ],
            'existingImages' => $product->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->url,
                'is_main' => (bool) $img->is_main,
                'sort_order' => $img->sort_order,
            ]),
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
            'images' => 'nullable|array|max:10',
            'images.*' => 'nullable|image|max:10240',
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => [
                'integer',
                Rule::exists('product_images', 'id')->where('product_id', $product->id),
            ],
        ]);

        DB::beginTransaction();

        try {
            $minPrice = (float) collect($request->variants)->min(
                fn ($v) => (float) ($v['price'] ?? 0)
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
                ->map(fn ($id) => (int) $id)
                ->all();

            $submittedVariantIds = collect($request->variants)->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();

            if ($submittedVariantIds !== []) {
                $product->variants()
                    ->whereNotIn('id', $submittedVariantIds)
                    ->get()
                    ->each(fn ($v) => $v->delete());
            } else {
                $product->variants()->get()->each(fn ($v) => $v->delete());
            }

            $folder = public_path('img/products/'.$user->id);
            if (! file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            foreach ($request->variants as $variantIndex => $variant) {
                $options = $variant['options'] ?? [];
                if (is_string($options)) {
                    $decoded = json_decode($options, true);
                    $options = is_array($decoded) ? $decoded : [];
                }
                if (! is_array($options)) {
                    $options = [];
                }

                $newPrice        = (float) $variant['price'];
                $variantImageKey = 'variant_image_'.$variantIndex;

                if (! empty($variant['id'])) {
                    $pv = ProductVariant::where('product_id', $product->id)->find($variant['id']);
                    if ($pv) {
                        $autoOldPrice = ((float) $pv->price !== $newPrice)
                            ? $pv->price
                            : $pv->old_price;

                        $pv->update([
                            'options'   => $options,
                            'price'     => $newPrice,
                            'old_price' => $autoOldPrice,
                            'stock'     => (int) $variant['stock'],
                        ]);

                        if ($request->hasFile($variantImageKey)) {
                            ProductImage::where('product_id', $product->id)
                                ->where('variant_id', $pv->id)
                                ->each(fn ($img) => $img->delete());

                            $vImg     = $request->file($variantImageKey);
                            $vImgName = time().'_v'.$variantIndex.'_'.Str::random(5).'.'.$vImg->extension();
                            $vImg->move($folder, $vImgName);
                            ProductImage::create([
                                'product_id' => $product->id,
                                'variant_id' => $pv->id,
                                'url'        => '/img/products/'.$user->id.'/'.$vImgName,
                                'sort_order' => 0,
                                'is_main'    => true,
                            ]);
                        }

                        continue;
                    }
                }

                $pv = ProductVariant::create([
                    'product_id' => $product->id,
                    'options'    => $options,
                    'price'      => $newPrice,
                    'old_price'  => null,
                    'stock'      => (int) $variant['stock'],
                    'is_active'  => true,
                ]);

                if ($request->hasFile($variantImageKey)) {
                    $vImg     = $request->file($variantImageKey);
                    $vImgName = time().'_v'.$variantIndex.'_'.Str::random(5).'.'.$vImg->extension();
                    $vImg->move($folder, $vImgName);
                    ProductImage::create([
                        'product_id' => $product->id,
                        'variant_id' => $pv->id,
                        'url'        => '/img/products/'.$user->id.'/'.$vImgName,
                        'sort_order' => 0,
                        'is_main'    => true,
                    ]);
                }
            }

            foreach ($request->variants as $variantIndex => $variant) {
                if (empty($variant['id'])) {
                    continue;
                }
                $pv = ProductVariant::where('product_id', $product->id)->find($variant['id']);
                if (! $pv) {
                    continue;
                }
                $hasImage = $request->hasFile('variant_image_'.$variantIndex)
                    || ProductImage::where('product_id', $product->id)->where('variant_id', $pv->id)->exists();
                if (! $hasImage) {
                    throw new \InvalidArgumentException('У каждого варианта должно быть фото');
                }
            }

            foreach ($request->remove_image_ids ?? [] as $imgId) {
                $img = ProductImage::where('product_id', $product->id)
                    ->whereNull('variant_id')
                    ->find($imgId);
                if ($img) {
                    $img->delete();
                }
            }

            if ($request->hasFile('images')) {
                $maxSort = (int) ProductImage::where('product_id', $product->id)->max('sort_order');

                foreach ($request->file('images') as $index => $image) {
                    $imageName = time().'_'.$index.'_'.Str::random(5).'.'.$image->extension();
                    $image->move($folder, $imageName);

                    $relPath = 'img/products/'.$user->id.'/'.$imageName;

                    ProductImage::create([
                        'product_id' => $product->id,
                        'url' => '/'.$relPath,
                        'sort_order' => $maxSort + $index + 1,
                        'is_main' => false,
                    ]);
                }
            }

            foreach ($request->attributes ?? [] as $attributeId => $value) {
                $aid = (int) $attributeId;
                if ($aid < 1 || ! in_array($aid, $allowedAttributeIds, true)) {
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

            DB::commit();

            return redirect()
                ->route('seller.products')
                ->with('success', 'Товар обновлён и снова отправлен на модерацию');
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
            'variants' => fn ($q) => $q->orderBy('id'),
            'variants.images',
            'images' => fn ($q) => $q->whereNull('variant_id')->orderBy('sort_order'),
        ]);

        $liveVariants  = $product->variants->filter(fn ($v) => ! $v->trashed());
        $totalStock    = $liveVariants->sum('stock');
        $activeCount   = $liveVariants->where('is_active', true)->count();
        $hiddenCount   = $liveVariants->where('is_active', false)->count();
        $totalViews    = (int) $liveVariants->sum('views_count');

        return Inertia::render('Seller/Products/Manage', [
            'product' => [
                'id'             => $product->id,
                'title'          => $product->title,
                'status'             => $product->status,
                'moderation_comment' => $product->moderation_comment,
                'is_listed'          => (bool) $product->is_on_action,
                'category'       => $product->category?->name ?? '—',
                'min_price'      => (float) $product->min_price,
                'views_count'    => $totalViews,
                'sales_count'    => (int) ($product->sales_count ?? 0),
                'created_at'     => $product->created_at?->format('d.m.Y') ?? '',
                'main_image'     => $product->resolveListingImageUrl(),
                'total_stock'    => $totalStock,
                'active_variants'=> $activeCount,
                'hidden_variants'=> $hiddenCount,
            ],
            'variants' => $liveVariants->map(function (ProductVariant $v) {
                $opts = $v->options;
                if (is_string($opts)) {
                    $opts = json_decode($opts, true) ?? [];
                }

                return [
                    'id'        => $v->id,
                    'options'   => is_array($opts) ? $opts : [],
                    'price'     => (float) $v->price,
                    'old_price' => $v->old_price ? (float) $v->old_price : null,
                    'stock'     => (int) $v->stock,
                    'views_count' => (int) ($v->views_count ?? 0),
                    'is_active' => (bool) $v->is_active,
                    'sku'       => $v->sku,
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

        $variant->update(['is_active' => ! $variant->is_active]);

        return back()->with('success', 'Статус варианта обновлён');
    }

    public function updateVariantStock(Request $request, Product $product, ProductVariant $variant)
    {
        $user = Auth::user();

        if ($product->seller_id !== $user->id || $variant->product_id !== $product->id) {
            abort(403);
        }

        $request->validate(['stock' => 'required|integer|min:0']);

        $variant->update(['stock' => (int) $request->stock]);

        return back()->with('success', 'Остаток обновлён');
    }

}