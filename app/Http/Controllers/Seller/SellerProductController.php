<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductAttributeValue;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use Inertia\Inertia;

class SellerProductController extends Controller
{
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
        $request->validate([

            'title' => 'required|max:200',

            'category_id' => 'required|exists:categories,id',

            'main_image' => 'required|image',

            'variants' => 'required|array|min:1',

            'variants.*.price' => 'required|numeric',

            'variants.*.stock' => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {

            $user = Auth::user();

            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            */

            $minPrice = collect($request->variants)
                ->min('price');

            $product = Product::create([

                'seller_id' => $user->id,

                'category_id' => $request->category_id,

                'title' => $request->title,

                'description' => $request->description,

                'short_description' => $request->short_description,

                'min_price' => $minPrice,

                'status' => 'moderation',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Attributes
            |--------------------------------------------------------------------------
            */

            if ($request->attributes) {

                foreach ($request->attributes as $attributeId => $value) {

                    ProductAttributeValue::create([

                        'product_id' => $product->id,

                        'attribute_id' => $attributeId,

                        'value' => is_array($value)
                            ? json_encode($value)
                            : $value
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Variants
            |--------------------------------------------------------------------------
            */

            foreach ($request->variants as $variant) {

                ProductVariant::create([

                    'product_id' => $product->id,

                    'sku' => strtoupper(Str::random(12)),

                    'options' => json_encode(
                        $variant['options'] ?? []
                    ),

                    'price' => $variant['price'],

                    'old_price' => $variant['old_price'] ?? null,

                    'stock' => $variant['stock'],

                    'weight_grams' => $variant['weight_grams'] ?? null,

                    'is_active' => true,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Images
            |--------------------------------------------------------------------------
            */

            $folder = public_path(
                'img/products/' . $user->id
            );

            if (!file_exists($folder)) {

                mkdir($folder, 0777, true);
            }

            /*
            |--------------------------------------------------------------------------
            | Main image
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('main_image')) {

                $mainImage = $request->file('main_image');

                $mainName =
                    time() .
                    '_main_' .
                    Str::random(5) .
                    '.' .
                    $mainImage->extension();

                $mainImage->move($folder, $mainName);

                ProductImage::create([

                    'product_id' => $product->id,

                    'url' =>
                        '/img/products/' .
                        $user->id .
                        '/' .
                        $mainName,

                    'sort_order' => 0,

                    'is_main' => true,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Gallery images
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('images')) {

                foreach ($request->file('images') as $index => $image) {

                    $imageName =
                        time() .
                        '_' .
                        $index .
                        '_' .
                        Str::random(5) .
                        '.' .
                        $image->extension();

                    $image->move($folder, $imageName);

                    ProductImage::create([

                        'product_id' => $product->id,

                        'url' =>
                            '/img/products/' .
                            $user->id .
                            '/' .
                            $imageName,

                        'sort_order' => $index + 1,

                        'is_main' => false,
                    ]);
                }
            }

            DB::commit();

            return redirect()
                ->route('seller.dashboard')
                ->with(
                    'success',
                    'Товар успешно создан'
                );

        } catch (\Exception $e) {

            DB::rollBack();

            return back()->withErrors([
                'error' => $e->getMessage()
            ]);
        }
    }
}