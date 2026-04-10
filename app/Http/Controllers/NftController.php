<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Nft;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class NftController extends Controller
{

    // NftController.php
    public function show(Nft $nft, Request $request)
    {
        $nft->load('user');
        $user = User::find(Auth::user()->id);
        if (!$user) {
            return redirect()->route('login');
        }

        $nft->is_favorite = $user ? $user->favorites()->where('nft_id', $nft->id)->exists() : false;
        $cart = \App\Models\Cart::where('user_id', $user->id)->where('nft_id', $nft->id)->exists();
        $nft->in_cart = $cart;

        // ... весь твой код с оплатой ...
        $seller = User::find($nft->user_id);
        return Inertia::render('Nft/Show', [
            'nftUser' => [
                'owner_name' => $nft->user->name ?? 'Аноним',
                'owner_avatar' => $nft->user->avatar ?? null,
            ],
            'nft' => $nft,  // ← ВСЁ В ОДНОМ ОБЪЕКТЕ!
            'seller' => $seller,  // ← ВСЁ В ОДНОМ ОБЪЕКТЕ!
            'canPayWithWallet' => $user->balance >= $nft->price,
            'walletBalance' => $user->balance,
            'auth' => ['user' => $user],
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
            return Inertia::render('Nft/Create', [
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

            Nft::create([
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

    // NftController.php
    public function cartStore(Request $request)
    {
        $userId = auth()->id();
        $nftId = $request->nft['id'];

        $exists = Cart::where('user_id', $userId)
            ->where('nft_id', $nftId)  // ← Уже правильно
            ->exists();

        if ($exists) {
            return back()->with('error', 'Этот NFT уже в корзине');
        }

        Cart::create([
            'user_id' => $userId,
            'nft_id' => $nftId,  // ← Исправь, если было 'nfts'
            'status' => 'active',  // ← Измени на 'active' по умолчанию (из миграции)
        ]);

        return back()->with('success', 'NFT добавлен в корзину');
    }

    public function cartDestroy(Request $request)
    {
        $userId = auth()->id();
        $nftId = $request->nft['id'];

        Cart::where('user_id', $userId)
            ->where('nft_id', $nftId)  // ← Уже правильно
            ->delete();

        return back()->with('success', 'NFT удалён из корзины');
    }

    public function edit(Nft $nft)
    {
        $user = Auth()->user();
        if (auth()->id() !== $nft->user_id || $user->is_blocked === 1) {
            return redirect()->route('nft.show', $nft)->with('error', 'Доступ запрещён');
        }

        return Inertia::render('Nft/Edit', [
            'nft' => $nft,
            'auth' => ['user' => auth()->user()],
        ]);
    }
    public function stopSelling(Nft $nft)
    {
        if (auth()->id() !== $nft->user_id) {
            return back()->with('error', 'Вы не владелец');
        }

        $nft->update(['status' => 'sold']);

        return back()->with('success', 'NFT снят с продажи');
    }
    public function update(Request $request, Nft $nft)
    {
        if (auth()->id() !== $nft->user_id) {
            return back()->with('error', 'Вы не владелец');
        }

        $request->validate([
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $nft->update(
            [
                'price' => $request->price,
                'description' => $request->description,
                'previous_price' => $nft->price,
                'status' => 'moderation',
            ],
            [
                'price.required' => 'Цена обязательна',
                'price.min' => 'Цена не может быть отрицательной',
                'category_id.exists' => 'Выбрана несуществующая категория',
            ]
        );

        return redirect()->route('nft.show', $nft)->with('success', 'NFT выставлен на продажу!');
    }

    public function destroy(Request $request, $id)
    {
        $userId = Nft::where('id', $id)->value('user_id');
        Nft::where('id', $id)
            ->delete();
        return redirect('/users/' . $userId)
            ->with('success', 'NFT удалён');
    }
}
