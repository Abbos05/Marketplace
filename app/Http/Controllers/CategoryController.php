<?php
namespace App\Http\Controllers;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Nft;
use Inertia\Inertia;

class CategoryController extends Controller
{

    public function show($id, Request $request)
    {
        $query = Nft::with('user', 'category')->where('category_id', $id)->where('status', 'relevant');
        $search = request('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        $sort = $request->query('sort', 'new'); // по умолчанию — новинки
        match ($sort) {
            'new'       => $query->orderByDesc('created_at'),
            'cheap'     => $query->orderBy('price'),
            'expensive' => $query->orderByDesc('price'),
            'popular'   => $query->orderByDesc('created_at'), // вместо views — по дате
            default     => $query->orderByDesc('created_at'),
        };

        // ЦЕНА
        if ($request->filled('price_from')) {
            $query->where('price', '>=', (float)$request->price_from);
        }
        if ($request->filled('price_to')) {
            $query->where('price', '<=', (float)$request->price_to);
        }

        $category = Category::findOrFail($id);
        $products = $query->get();
        $LikeProducts = Nft::with('user', 'category')
        ->where('status', 'relevant')->get();
        return Inertia::render('CategoryPage', [
            'category' => $category,
            'products'     => $products,
            'LikeProducts'     => $LikeProducts,
            'filters'  => [
                'search'      => $request->query('search'),
                'sort'        => $sort,
                'price_from'  => $request->query('price_from'),
                'price_to'    => $request->query('price_to'),
            ]
        ]);
    }
}
