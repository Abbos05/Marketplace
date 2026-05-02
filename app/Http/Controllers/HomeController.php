<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Category;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;


class HomeController extends Controller
{
  public function index(Request $request, $returnDataOnly = false, $id = null)
{
    $search = request('search');
    $sort = request('sort', 'price_desc');
    $price_from = $request->query('price_from');
    $price_to = $request->query('price_to');

    $validSorts = ['price_desc', 'price_asc', 'date_desc', 'date_asc'];
    $sort = in_array($sort, $validSorts) ? $sort : 'price_desc';

    // Основной запрос
    $query = Product::with('user', 'category')
        ->where('is_on_action', 1);

    // Поиск
    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Фильтр по цене
    if ($request->filled('price_from')) {
        $query->where('min_price', '>=', (float)$price_from);
    }
    if ($request->filled('price_to')) {
        $query->where('min_price', '<=', (float)$price_to);
    }

    // Сортировка
    switch ($sort) {
        case 'price_asc':
            $query->orderBy('min_price', 'asc');
            break;
        case 'price_desc':
            $query->orderBy('min_price', 'desc');
            break;
        case 'date_asc':
            $query->orderBy('created_at', 'asc');
            break;
        case 'date_desc':
            $query->orderBy('created_at', 'desc');
            break;
        default:
            $query->orderBy('min_price', 'desc');
    }

    $products = $query->take(50)->get();
    $category = Category::has('products')->with('products')->get();

    // Рекомендуемые товары
    $LikeProducts = Product::with('user', 'category')
        ->where('status', 'approved')
        ->take(12)
        ->get();

    // 🔥🔥🔥 ДОБАВЛЯЕМ ФЛАГ ИЗБРАННОГО (как в рабочем коде) 🔥🔥🔥
    if (auth()->check()) {
        $userId = auth()->id();
        $favoriteProductIds = DB::table('favorites')
            ->where('user_id', $userId)
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->toArray();
        
        // Добавляем is_favorite для основных товаров
        $products->each(function ($product) use ($favoriteProductIds) {
            $product->is_favorite = in_array($product->id, $favoriteProductIds);
        });
        
        // Добавляем is_favorite для рекомендуемых товаров
        $LikeProducts->each(function ($product) use ($favoriteProductIds) {
            $product->is_favorite = in_array($product->id, $favoriteProductIds);
        });
    } else {
        // Если пользователь не авторизован, то все товары не в избранном
        $products->each(fn($product) => $product->is_favorite = false);
        $LikeProducts->each(fn($product) => $product->is_favorite = false);
    }

    $data = [
        'LikeProducts' => $LikeProducts,
        'category' => $category,
        'auth' => auth()->user() ? ['user' => auth()->user()] : ['user' => null],
        'canResetPassword' => true,
        'mysqlNftsData' => $products,
        'categoryData' => $category,
        'search' => $search,
        'sort' => $sort,
        'filters' => [
            'search' => $search,
            'sort' => $sort,
            'price_from' => $price_from,
            'price_to' => $price_to,
        ]
    ];

    if ($returnDataOnly) return $data;
    return Inertia::render('Home', $data);
}

    public function category($returnDataOnly = false)
    {
        $search = request('search');
        $sort = request('sort', 'price_desc');
    
    
        $validSorts = ['price_desc', 'price_asc', 'date_desc', 'date_asc'];
        $sort = in_array($sort, $validSorts) ? $sort : 'price_desc';
    

        $query = Product::with('user', 'category')
            ->where('is_on_action', 1);
    
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('tags', 'like', "%{$search}%");
            });
        }
    
        switch ($sort) {
            case 'price_asc':  $query->orderBy('price', 'asc'); break;
            case 'price_desc': $query->orderBy('price', 'desc'); break;
            case 'date_asc':   $query->orderBy('created_at', 'asc'); break;
            case 'date_desc':  $query->orderBy('created_at', 'desc'); break;
            default:           $query->orderBy('price', 'desc');
        }
    
        $products= $query->take(50)->get();
    
        $bestNfts = Product::with('user')
            ->where('is_on_action', 1)
            ->orderBy('created_at', 'desc')
            ->take(9)
            ->get();
    
        $category = Category::has('nfts')->with('nfts')->get();
    
        $data = [
            'auth' => auth()->user() ? ['user' => auth()->user()] : ['user' => null],
            'canResetPassword' => true,
            'mysqlNftsData' => $products,
            'bestNftsData' => $bestNfts,
            'categoryData' => $category,
            'search' => $search,
            'sort' => $sort,
        ];
    
        if ($returnDataOnly) return $data;
    
        return Inertia::render('Product/CategoryMenu', $data);
    }

 public function favorites(Product $product)
    {
        $user = Auth::user();
        if ($user) {
            $user->favorites()->toggle($product->id);
        }
        
        // Важно: возвращаемся с обновлёнными данными
        return back();
    }
}
