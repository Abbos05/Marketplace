<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\User;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function show($id, Request $request)
    {
        $query = Product::with('user', 'category')->where('category_id', $id)->where('is_on_action', 1);
        
        // Поиск
        $search = request('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%");
            });
        }
    
        // Сортировка
        $sort = $request->query('sort', 'new');
        match ($sort) {
            'new'       => $query->orderByDesc('created_at'),
            'cheap'     => $query->orderBy('price'),
            'expensive' => $query->orderByDesc('price'),
            'popular'   => $query->orderByDesc('created_at'),
            default     => $query->orderByDesc('created_at'),
        };
    
        // Фильтр по цене
        if ($request->filled('price_from')) {
            $query->where('price', '>=', (float)$request->price_from);
        }
        if ($request->filled('price_to')) {
            $query->where('price', '<=', (float)$request->price_to);
        }
        
        $category = Category::findOrFail($id);
        $products = $query->get();
        
        // Рекомендации (товары из других категорий)
        $LikeProducts = Product::with('user', 'category')
            ->where('is_on_action', 1)
            ->where('category_id', '!=', $id)
            ->limit(20)
            ->get();
    
        // Добавляем флаг избранного
        if (auth()->check()) {
            $userId = auth()->id();
            $favoriteNftIds = DB::table('favorites')
                ->where('user_id', $userId)
                ->whereNotNull('product_id')
                ->pluck('product_id')
                ->toArray();
            
            $products->each(function ($product) use ($favoriteNftIds) {
                $product->is_favorite = in_array($product->id, $favoriteNftIds);
            });
            
            $LikeProducts->each(function ($product) use ($favoriteNftIds) {
                $product->is_favorite = in_array($product->id, $favoriteNftIds);
            });
        } else {
            $products->each(fn($product) => $product->is_favorite = false);
            $LikeProducts->each(fn($product) => $product->is_favorite = false);
        }
        
        return Inertia::render('CategoryPage', [
            'category' => $category,
            'products' => $products,
            'LikeProducts' => $LikeProducts,
            'filters' => [
                'search'      => $request->query('search'),
                'sort'        => $sort,
                'price_from'  => $request->query('price_from'),
                'price_to'    => $request->query('price_to'),
            ]
        ]);
    }
}
