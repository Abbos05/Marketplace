<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Category;
use Inertia\Inertia;
use App\Models\Nft;
use App\Models\User;

class HomeController extends Controller
{
    public function index( Request $request, $returnDataOnly = false, $id = null)
    {
        $search = request('search');
        $sort = request('sort', 'price_desc');
        $price_from = $request->query('price_from'); // ✅ Добавлено
        $price_to = $request->query('price_to');     // ✅ Добавлено
    
        $validSorts = ['price_desc', 'price_asc', 'date_desc', 'date_asc'];
        $sort = in_array($sort, $validSorts) ? $sort : 'price_desc';
    
        $query = Nft::with('user', 'category')
            ->where('status', 'relevant');
    
        // Поиск
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('tags', 'like', "%{$search}%");
            });
        }
    
        // ✅ ДОБАВЛЕНО: Фильтр по цене
        if ($request->filled('price_from')) {
            $query->where('price', '>=', (float)$price_from);
        }
        if ($request->filled('price_to')) {
            $query->where('price', '<=', (float)$price_to);
        }
    
        // Сортировка
        switch ($sort) {
            case 'price_asc':  $query->orderBy('price', 'asc'); break;
            case 'price_desc': $query->orderBy('price', 'desc'); break;
            case 'date_asc':   $query->orderBy('created_at', 'asc'); break;
            case 'date_desc':  $query->orderBy('created_at', 'desc'); break;
            default:           $query->orderBy('price', 'desc');
        }
    
        $products = $query->take(50)->get();
        $bestNfts = Nft::with('user')
            ->where('status', 'relevant')
            ->orderBy('created_at', 'desc')
            ->take(9)
            ->get();
    
        $category = Category::has('nfts')->with('nfts')->get();
        $LikeProducts = Nft::with('user', 'category')
        ->where('status', 'relevant')->get();
        $data = [
            'LikeProducts'     => $LikeProducts,
            'category' => $category,
            'auth' => auth()->user() ? ['user' => auth()->user()] : ['user' => null],
            'canResetPassword' => true,
            'mysqlNftsData' => $products,
            'bestNftsData' => $bestNfts,
            'categoryData' => $category,
            'search' => $search,
            'sort' => $sort,
            'filters'  => [
                'search'      => $search,
                'sort'        => $sort,
                'price_from'  => $price_from,
                'price_to'    => $price_to,
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
    

        $query = Nft::with('user', 'category')
            ->where('status', 'relevant');
    
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
    
        $bestNfts = Nft::with('user')
            ->where('status', 'relevant')
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
    
        return Inertia::render('Nft/CategoryMenu', $data);
    }

    public function favorites(Nft $nft)
    {       
        $user = User::find( Auth::id());
        $user->favorites()->toggle($nft->id);
        return back();
    }
}
