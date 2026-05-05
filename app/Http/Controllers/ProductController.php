<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Category;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class ProductController extends Controller
{

    // ProductController.php
    public function show(Product $product, Request $request)
    {
        $product->load('user');
        $user = User::find(Auth::user()->id);

        if (!$user) {
            return redirect()->route('login');
        }

        // Получаем первый активный вариант товара
        $variant = $product->variants()
            ->where('is_active', true)
            ->first();

        // Если вариантов нет - создаем базовый вариант
        if (!$variant) {
            $variant = $product->variants()->create([
                'sku' => 'default-' . $product->id,
                'options' => json_encode(['default' => 'standard']),
                'price' => $product->min_price,
                'stock' => 999,
                'is_active' => true
            ]);
        }

        // Проверяем в корзине ли этот вариант
        $inCart = Cart::where('user_id', $user->id)
            ->where('variant_id', $variant->id)
            ->exists();

        // Добавляем данные варианта в объект продукта
        $product->variant_id = $variant->id;
        $product->variant_price = $variant->price;
        $product->in_cart = $inCart;
        $product->is_favorite = $user->favorites()->where('product_id', $product->id)->exists();

        $seller = User::find($product->seller_id);

        // Проверяем, заказывал ли пользователь этот товар
        $hasOrdered = OrderItem::where('variant_id', $variant->id)
            ->whereHas('order', function ($query) use ($user) {
                $query->where('buyer_id', $user->id)
                    ->whereIn('status', ['new', 'processing']); // Только активные статусы
            })
            ->exists();

        $existingOrderId = OrderItem::where('variant_id', $variant->id)
            ->whereHas('order', function ($query) use ($user) {
                $query->where('buyer_id', $user->id)
                    ->whereIn('status', ['new', 'processing']);
            })
            ->value('order_id');

        return Inertia::render('Product/Show', [
            'nftUser' => [
                'owner_name' => $product->user->name ?? 'Аноним',
                'owner_avatar' => $product->user->avatar ?? null,
            ],
            'product' => $product,
            'seller' => $seller,
            'canPayWithWallet' => $user->balance >= $variant->price,
            'walletBalance' => $user->balance,
            'auth' => ['user' => $user],
            'hasOrdered' => $hasOrdered,
            'existingOrderId' => $existingOrderId,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
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

            return redirect()->route('profile')->with('success', 'NFT успешно создан!');
        }
    }

    // ProductController.php

    public function edit(Product $product)
    {
        $user = Auth()->user();
        if (auth()->id() !== $product->user_id || $user->is_blocked === 1) {
            return redirect()->route('nft.show', $product)->with('error', 'Доступ запрещён');
        }

        return Inertia::render('Product/Edit', [
            'nft' => $product,
            'auth' => ['user' => auth()->user()],
        ]);
    }
    public function stopSelling(Product $product)
    {
        if (auth()->id() !== $product->user_id) {
            return back()->with('error', 'Вы не владелец');
        }

        $product->update(['status' => 'sold']);

        return back()->with('success', 'NFT снят с продажи');
    }
    public function update(Request $request, Product $product)
    {
        if (auth()->id() !== $product->user_id) {
            return back()->with('error', 'Вы не владелец');
        }

        $request->validate([
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $product->update(
            [
                'price' => $request->price,
                'description' => $request->description,
                'previous_price' => $product->price,
                'status' => 'moderation',
            ],
            [
                'price.required' => 'Цена обязательна',
                'price.min' => 'Цена не может быть отрицательной',
                'category_id.exists' => 'Выбрана несуществующая категория',
            ]
        );

        return redirect()->route('nft.show', $product)->with('success', 'NFT выставлен на продажу!');
    }

    public function destroy(Request $request, $id)
    {
        $userId = Product::where('id', $id)->value('user_id');
        Product::where('id', $id)
            ->delete();
        return redirect('/users/' . $userId)
            ->with('success', 'NFT удалён');
    }
}
