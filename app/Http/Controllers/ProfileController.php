<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $myNfts = Product::where('user_id', $user->id)->where('is_on_action', 1)
            ->with(['category', 'user'])
            ->get();
        return Inertia::render('Profile/Index', [
            'auth' => ['user' => $user],
            'users' => User::all(),
            'nfts' => $myNfts,
            'categories' => Category::all(),
        ]);
    }
    public function filter(Request $request)
    {
        $user = Auth::user();

        $myNfts = collect(); // по умолчанию — пустая коллекция
        $user = Auth::user();
        $myNfts = Product::where('user_id', $user->id)->where('is_on_action', 1)
            ->with(['category', 'user']) // добавляем загрузку пользователя
            ->get();

        // Правильно проверяем параметр запроса
        if ($request->filter == "myNfts") {
            $myNfts = Product::where('user_id', $user->id)
                ->with(['category', 'user'])
                ->orderByRaw("FIELD(status, 'sold', 'relevant', 'moderation', 'rejection')")
                ->orderBy('created_at', 'desc')
                ->get();
        }
        // В ProfileController@filter
        if ($request->filter == "myHistory") {
            $transactions = Transaction::with(['nft', 'buyer', 'seller'])
                ->where('buyer_id', $user->id)
                ->orWhere('seller_id', $user->id)
                ->latest()
                ->get()
                ->map(function ($tx) use ($user) {
                    $isBuyer = $tx->buyer_id == $user->id;
                    $counterparty = $isBuyer ? $tx->seller : $tx->buyer;
                    return [
                        'id' => $tx->id,
                        'type' => $tx->buyer_id == $user->id ? 'buy' : 'sell',
                        'nft' => $tx->nft,
                        'status' => $tx->status == 'failed' ? 'Неуспешно' : ($tx->status == 'completed' ? 'Успешно' : 'в процессе'),
                        'price' => $tx->amount,
                        'created_at' => $tx->created_at,
                        'counterparty' => [
                            'id' => $counterparty?->id,
                            'name' => $counterparty?->name,
                            'avatar' => $counterparty?->avatar,
                            'trashed' => $counterparty?->trashed(), // <-- Добавляем флаг
                        ],
                    ];
                });

            $myNfts = $transactions; // ← Передаём как nfts
        }
        if ($request->filter == "myCheck") {
            $myNfts = Product::where('user_id', $user->id)->where('status', 'moderation')
                ->with(['category', 'user'])
                ->get();
        }
        if ($request->filter == "myFavorites") {

            $myNfts = Product::whereHas('favorites', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
                ->with(['category', 'user'])
                ->get();
        }
        if ($request->filter == "myCart") {
            $myNfts = Product::whereHas('carts', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
                ->with(['category', 'user'])
                ->get();
        }

        // dd($myNfts);
        return Inertia::render('Profile/Index', [
            'auth' => ['user' => $user],
            'categories' => Category::all(),
            'nfts' => $myNfts->toArray(),   // ← массив, а не коллекция
            'activeFilter' => $request->filter ?? null,
        ]);
    }


    public function favorites()
    {
        $user = Auth::user();

        $myFavorites = Product::select('products.*')
            ->join('favorites', 'products.id', '=', 'favorites.product_id')
            ->where('favorites.user_id', $user->id)
            ->with(['category', 'seller'])
            ->orderBy('favorites.created_at', 'desc')
            ->get();

        $myFavorites->each(fn($p) => $p->is_favorite = true);


        $products = Product::all();
        $LikeProducts = Product::with('user')
            ->get();
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
        return Inertia::render('Profile/Favorite', [
            'auth' => ['user' => $user],
            'product' => $myFavorites,
            'LikeProducts' => $LikeProducts,
        ]);
    }



    public function edit(Request $request)
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail,
            'status' => session('status'),
            'auth' => ['user' => $request->user()],
        ]);
    }

    public function show(User $user)
    {
        return Inertia::render('Profile/Show', [
            'auth' => ['user' => Auth::user()],
            'profileUser' => $user,
        ]);
    }


    public function block(User $user)
    {
        if ($user->id === auth()->id() || $user->id === 1) {
            return back()->with('error', 'Вы не можете заблокировать');
        } else {
            $user->update([
                'is_blocked' => !$user->is_blocked,
            ]);
            return back()->with('success', 'Статус блокировки пользователя изменен');
        }
    }
    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [
            'name' => 'required|string|max:50',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'description' => 'nullable|string|max:500',
            'avatar' => 'nullable|image|max:2048',
            'current_password' => 'nullable|string',
            'password' => 'nullable|string|min:4|confirmed',
            'phone' => 'nullable|string|regex:/^7\d{10}$/|max:11', // <-- главное правило
        ];

        $validated = $request->validate($rules, [
            'phone.regex' => 'Номер телефона должен начинаться с 7 и содержать 11 цифр.',
            'name.required' => 'Имя обязательно для заполнения.',
            'name.string' => 'Имя должно быть текстом.',
            'name.max' => 'Имя не должно превышать 50 символов.',

            'email.required' => 'Email обязателен для заполнения.',
            'email.email' => 'Введите корректный адрес электронной почты.',
            'email.max' => 'Email не должен превышать 255 символов.',
            'email.unique' => 'Пользователь с таким email уже существует.',

            'description.string' => 'Описание должно быть текстом.',
            'description.max' => 'Описание не должно превышать 500 символов.',

            'avatar.image' => 'Аватар должен быть изображением.',
            'avatar.max' => 'Размер аватара не должен превышать 2 МБ.',

            'current_password.string' => 'Текущий пароль должен быть текстом.',

            'password.string' => 'Новый пароль должен быть текстом.',
            'password.min' => 'Новый пароль должен содержать минимум 4 символа.',
            'password.confirmed' => 'Подтверждение нового пароля не совпадает.',

        ]);

        // Аватар
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::delete('public/' . $user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            $validated['avatar'] = $user->avatar;
        }

        // Пароль
        if ($request->filled('password')) {
            if (!$user->newPassw && !Hash::check($request->current_password, $user->password)) {
                return back()->withErrors(['current_password' => 'Неверный текущий пароль']);
            }
            $validated['password'] = Hash::make($validated['password']);
            $validated['newPassw'] = false;
        }

        // Убираем служебные поля
        unset($validated['current_password'], $validated['password_confirmation']);

        // Обновляем пользователя
        $user->update($validated);

        return back()->with('success', 'Профиль успешно обновлён');
    }
    public function updatePhone(Request $request)
    {

        $phone = preg_replace('/\D/', '', $request->input('phone'));
        $request->merge(['phone' => $phone]);
        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'string',
                'regex:/^7\d{10}$/',
                'max:11',
            ],
        ], [
            'phone.regex' => 'Номер телефона должен быть в формате 7XXXXXXXXXX (например, 79991234567).',
            'phone.max' => 'Номер телефона не должен превышать 11 символов.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validatedData = $validator->validated();

        // Получаем текущего аутентифицированного пользователя
        $user = $request->user();
        $user->update(['phone' => $validatedData['phone']]);

        // Возвращаем ответ
        return back()->with('success', 'Номер телефона обновлён.');
    }



    public function updateEmail(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', 'Email обновлен');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:4|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Текущий пароль неверный']);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back()->with('success', 'Пароль обновлен');
    }

    public function destroy(Request $request, User $user = null): RedirectResponse
    {
        if ($user) {
            if ($user->role === 'admin') {
                return back()->with('error', 'Вы не можете удалить себя или админов');
            } else {
                $user->delete();
                return back()->with('success', 'Акк удален');
            }
        }
        $user = $request->user();
        if ($user) {
            if (auth()->user()->is_blocked === 1) {
                return back()->with('error', 'Ваш аккаунт заблакирован');
            } else {
                Auth::logout();
                $user->delete();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return Redirect::to('/');
            }
        }
    }
}
