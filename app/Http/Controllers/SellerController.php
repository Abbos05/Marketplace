<?php

namespace App\Http\Controllers;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    public function index($id, Request $request)
    {
        $query = Product::with('seller')->where('seller_id', $id)->where('is_on_action', 1);
        //                     ^^^^^^ вместо 'user'          ^^^^^^^^^ вместо 'user_id'

        $search = request('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
                // или 'description', если нужно
            });
        }

        $sort = $request->query('sort', 'new');
        match ($sort) {
            'new' => $query->orderByDesc('created_at'),
            'cheap' => $query->orderBy('min_price'),  // у вас price → min_price
            'expensive' => $query->orderByDesc('min_price'),
            'popular' => $query->orderByDesc('sales_count'), // у вас есть sales_count
            default => $query->orderByDesc('created_at'),
        };

        // ЦЕНА (фильтр по min_price)
        if ($request->filled('price_from')) {
            $query->where('min_price', '>=', (float) $request->price_from);
        }
        if ($request->filled('price_to')) {
            $query->where('min_price', '<=', (float) $request->price_to);
        }

        $products = $query->get();

        if (auth()->check()) {
            $userId = auth()->id();

            $favoriteProductIds = DB::table('favorites')
                ->where('user_id', $userId)
                ->pluck('product_id')
                ->toArray();

            $products->each(function ($product) use ($favoriteProductIds) {
                $product->is_favorite = in_array($product->id, $favoriteProductIds);
            });
        } else {
            $products->each(function ($product) {
                $product->is_favorite = false;
            });
        }

        // Вместо Product::where('user_id', $id)->get() – не нужно, у вас уже есть $products
        // $seller = Product::where('seller_id', $id)->get(); // лишняя дублирующая выборка

        return Inertia::render('SellerProfile/Seller', [
            'products' => $products,
            'filters' => [
                'search' => $request->query('search'),
                'sort' => $sort,
                'price_from' => $request->query('price_from'),
                'price_to' => $request->query('price_to'),
            ]
        ]);
    }
}
