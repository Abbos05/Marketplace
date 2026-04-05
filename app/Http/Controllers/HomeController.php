<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

use App\Models\Category;
use Inertia\Inertia;
use App\Models\Nft;

class HomeController extends Controller
{
    public function index( Request $request, $returnDataOnly = false, $id = null)
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
    
        $nfts = $query->take(50)->get();
    
        $bestNfts = Nft::with('user')
            ->where('status', 'relevant')
            ->orderBy('created_at', 'desc')
            ->take(9)
            ->get();
    
        $category = Category::has('nfts')->with('nfts')->get();
    

        $query = Nft::with('user', 'category')->where('category_id', $id)->where('status', 'relevant');

        // ЦЕНА
        if ($request->filled('price_from')) {
            $query->where('price', '>=', (float)$request->price_from);
        }
        if ($request->filled('price_to')) {
            $query->where('price', '<=', (float)$request->price_to);
        }
    
        $data = [
            'category' => $category,
            
            'auth' => auth()->user() ? ['user' => auth()->user()] : ['user' => null],
            'canResetPassword' => true,
            'mysqlNftsData' => $nfts,
            'bestNftsData' => $bestNfts,
            'categoryData' => $category,
            'search' => $search,
            'sort' => $sort,
            'filters'  => [
                'search'      => $request->query('search'),
                'sort'        => $sort,
                'price_from'  => $request->query('price_from'),
                'price_to'    => $request->query('price_to'),
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
    
        $nfts = $query->take(50)->get();
    
        $bestNfts = Nft::with('user')
            ->where('status', 'relevant')
            ->orderBy('created_at', 'desc')
            ->take(9)
            ->get();
    
        $category = Category::has('nfts')->with('nfts')->get();
    
        $data = [
            'auth' => auth()->user() ? ['user' => auth()->user()] : ['user' => null],
            'canResetPassword' => true,
            'mysqlNftsData' => $nfts,
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

        $user = auth()->user();

        $user->favorites()->toggle($nft->id);
        return back();
    }
}
