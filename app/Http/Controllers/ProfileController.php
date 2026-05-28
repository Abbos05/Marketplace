<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesCatalogRecommendations;
use App\Models\Order;
use App\Models\PickupPoint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\User;
use App\Notifications\MarketplaceAlert;
use App\Support\NotificationCategory;
use App\Services\OtpCodeGenerator;
use App\Services\UserRestrictionService;
use App\Models\Product;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Review;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use PreparesCatalogRecommendations;

    public function index()
    {
       $user = auth()->user()->load(['sellerProfile', 'defaultPickupPoint.region']);

        $LikeProducts = $this->catalogRecommendations();
        // Заказы с items и маппингом статусов
        $orders = Order::with('items.variant.product')
            ->where('buyer_id', $user->id)
            ->whereNotIn('status', [Order::STATUS_CANCELED, Order::STATUS_ISSUED, Order::STATUS_REFUSED])
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function ($order) {
                $order->frontend_status =
                    match ($order->status) {
                        Order::STATUS_NEW => 'pending',
                        Order::STATUS_INTRANSIT => 'shipping',
                        Order::STATUS_DELIVERED => 'ready',
                        Order::STATUS_ISSUED => 'completed',
                        Order::STATUS_CANCELED, Order::STATUS_REFUSED => 'cancelled',
                        default => 'pending',
                    };

                if ($order->delivery_method === 'pvz'
                    && in_array($order->status, [Order::STATUS_INTRANSIT, Order::STATUS_DELIVERED, Order::STATUS_ISSUED], true)) {
                    $order->pickup_code = $order->order_code;
                }

                return $order;
            });
            
        // Избранное
        $favoriteProductIds = DB::table('favorites')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->pluck('product_id')
            ->unique()
            ->values();

        $myFavorites = Product::forCatalogPresentation()
            ->whereIn('products.id', $favoriteProductIds)
            ->get()
            ->sortBy(fn ($p) => $favoriteProductIds->search($p->id))
            ->values();
        Product::enrichForCatalog($myFavorites);
        $this->markFavorites($myFavorites, true);

        $adminUsers = [];
        if ($user->isStaff()) {
            $restriction = app(UserRestrictionService::class);
            $adminUsers = User::withTrashed()
                ->with(['sellerProfile', 'approvedPickupPointStaff'])
                ->orderByRaw("FIELD(role, 'admin', 'moderator', 'seller', 'pvz', 'user')")
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($u) use ($restriction, $user) {
                    $flags = $restriction->roleFlagsFor($u);

                    return [
                        'id'             => $u->id,
                        'name'           => $u->name,
                        'last_name'      => $u->last_name,
                        'email'          => $u->email,
                        'phone'          => $u->phone,
                        'role'           => $u->role,
                        'is_blocked'     => $u->is_blocked,
                        'avatar'         => $u->avatar,
                        'deleted_at'     => $u->deleted_at,
                        'created_at'     => $u->created_at,
                        'seller_profile' => $u->sellerProfile ? ['shop_name' => $u->sellerProfile->shop_name] : null,
                        'can_assign_seller' => $flags['can_assign_seller'],
                        'can_assign_pvz' => $flags['can_assign_pvz'],
                        'assignable_roles' => $restriction->assignableRolesFor($user, $u),
                    ];
                });
        }

        $pickupPoints = PickupPoint::query()
            ->active()
            ->with('region')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (PickupPoint $p) => [
                'id' => $p->id,
                'label' => $p->title.($p->region ? ' — '.$p->region->name : ''),
                'title' => $p->title,
                'address' => $p->address,
                'region_name' => $p->region?->name,
            ]);

        $onlineThreshold = time() - 300;
        $userSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->limit(20)
            ->get()
            ->map(function ($session) use ($onlineThreshold) {
                $session->is_online = $session->last_activity >= $onlineThreshold;
                $session->last_activity = Carbon::createFromTimestamp($session->last_activity)->toIso8601String();

                return $session;
            });

        $loginHistory = DB::table('account_login_events')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(function ($event) {
                $event->created_at = Carbon::parse($event->created_at)->toIso8601String();

                return $event;
            });

        $ordersCount = Order::where('buyer_id', $user->id)
            ->whereNotIn('status', [Order::STATUS_CANCELED, Order::STATUS_REFUSED])
            ->count();

        $myReviewsScope = fn ($query) => $query
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('deleted_at')
                    ->orWhereNotNull('moderation_comment');
            });

        $reviewsCount = Review::query()
            ->withTrashed()
            ->tap($myReviewsScope)
            ->count();

        $myReviewsCollection = Review::query()
            ->withTrashed()
            ->with([
                'product' => fn ($q) => $q->select('id', 'title')->with([
                    'images' => fn ($iq) => $iq->whereNull('variant_id')->orderByDesc('is_main')->orderBy('sort_order'),
                ]),
            ])
            ->tap($myReviewsScope)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        Product::enrichForCatalog($myReviewsCollection->pluck('product')->filter()->unique('id')->values());

        $myReviews = $myReviewsCollection->map(function (Review $r) {
            $moderationStatus = $r->trashed() && $r->moderation_comment
                ? 'rejected'
                : ($r->is_moderated ? 'published' : 'pending');

            return [
                'id' => $r->id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'is_moderated' => $r->is_moderated,
                'moderation_status' => $moderationStatus,
                'moderation_comment' => $moderationStatus === 'rejected' ? $r->moderation_comment : null,
                'created_at' => $r->created_at?->toIso8601String(),
                'product' => $r->product ? [
                    'id' => $r->product->id,
                    'title' => $r->product->title,
                    'image' => $r->product->image,
                ] : null,
                'order_id' => $r->order_id,
            ];
        });

        return Inertia::render('Profile/Index', [
            'LikeProducts'  => $LikeProducts,
            'auth'          => ['user' => $user],
            'categories'    => Category::all(),
            'orders'        => $orders,
            'myFavorites'   => $myFavorites,
            'sellerProfile' => $user->sellerProfile,
            'closedSellerProfile' => app(\App\Services\AccountDeletionService::class)->closedSellerProfilePayload($user->id),
            'sellerRestorePending' => app(\App\Services\AccountDeletionService::class)->sellerRestorePendingPayload($user),
            'adminUsers'    => $adminUsers,
            'pickupPoints'  => $pickupPoints,
            'userSessions'  => $userSessions,
            'loginHistory'  => $loginHistory,
            'currentSessionId' => request()->session()->getId(),
            'accountDeletion' => app(\App\Services\AccountDeletionService::class)->accountDeletionInfo($user),
            'myReviews' => $myReviews,
            'profileCounts' => [
                'orders' => $ordersCount,
                'favorites' => $myFavorites->count(),
                'reviews' => $reviewsCount,
            ],
        ]);
    }

    public function destroySession(Request $request, string $sessionId): RedirectResponse
    {
        if ($sessionId === $request->session()->getId()) {
            return back()->with('error', 'Нельзя завершить текущую сессию.');
        }

        $deleted = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->delete();

        if (! $deleted) {
            return back()->with('error', 'Сессия не найдена или уже завершена.');
        }

        return back()->with('success', 'Сессия завершена.');
    }

    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $currentSessionId = $request->session()->getId();

        $deleted = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        if ($deleted === 0) {
            return back()->with('success', 'Других активных сеансов нет.');
        }

        return back()->with('success', "Завершено сеансов: {$deleted}.");
    }

    public function updateDefaultPickup(Request $request): RedirectResponse
    {
        $raw = $request->input('default_pickup_point_id');
        $request->merge([
            'default_pickup_point_id' => $raw === '' || $raw === null ? null : (int) $raw,
        ]);

        $data = $request->validate([
            'default_pickup_point_id' => 'nullable|integer|exists:pickup_points,id',
        ]);

        $id = $data['default_pickup_point_id'] ?? null;
        if ($id !== null) {
            $exists = PickupPoint::query()->active()->whereKey($id)->exists();
            if (! $exists) {
                return back()->withErrors(['default_pickup_point_id' => 'Пункт выдачи недоступен.']);
            }
        }

        $request->user()->update([
            'default_pickup_point_id' => $id,
        ]);

        return back()->with('success', 'Пункт выдачи сохранён.');
    }


   

    public function favorites()
    {
        $user = Auth::user();

        $favoriteProductIds = DB::table('favorites')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->pluck('product_id')
            ->unique()
            ->values();

        $myFavorites = Product::forCatalogPresentation()
            ->whereIn('products.id', $favoriteProductIds)
            ->get()
            ->sortBy(fn ($p) => $favoriteProductIds->search($p->id))
            ->values();
        Product::enrichForCatalog($myFavorites);
        $this->markFavorites($myFavorites, true);


        $LikeProducts = $this->catalogRecommendations([
            'exclude_product_ids' => $favoriteProductIds->all(),
        ]);

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
        $actor = auth()->user();

        if ($user->id === $actor->id || $user->id === 1) {
            return back()->with('error', 'Вы не можете заблокировать');
        }

        if ($actor->isModerator() && $user->isStaff()) {
            return back()->with('error', 'Модератор не может блокировать администраторов и других модераторов');
        }

        $wasBlocked = (bool) $user->is_blocked;
        $user->update([
            'is_blocked' => ! $user->is_blocked,
        ]);
        $user->refresh();
        if ($user->is_blocked && ! $wasBlocked) {
            app(UserRestrictionService::class)->applyBlock($user);
            $user->refresh();
            $user->notify(new MarketplaceAlert(
                'Аккаунт заблокирован',
                'Ваш доступ к покупкам и продаже ограничен. Вы можете выдать заказы, уже прибывшие в ваш пункт выдачи. Напишите в поддержку.',
                null,
                NotificationCategory::Account,
            ));
        }

        return back()->with('success', 'Статус блокировки пользователя изменен');
    }
    public function update(Request $request)
    {
        $user = $request->user();

      
        $rules = [
            'name'      => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'email'     => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:10240',
            'current_password' => 'nullable|string',
            'password' => 'nullable|string|min:4|confirmed',
        ];

        $validated = $request->validate($rules, [
            'name.required' => 'Имя обязательно для заполнения.',
            'name.string' => 'Имя должно быть текстом.',
            'name.max' => 'Имя не должно превышать 50 символов.',

            'email.email' => 'Введите корректный адрес электронной почты.',
            'email.max' => 'Email не должен превышать 255 символов.',
            'email.unique' => 'Пользователь с таким email уже существует.',

            'avatar.image' => 'Аватар должен быть изображением (JPG, PNG, WEBP).',
            'avatar.mimes' => 'Поддерживаются форматы JPG, PNG, WEBP и GIF.',
            'avatar.max' => 'Размер аватара не должен превышать 10 МБ.',

            'current_password.string' => 'Текущий пароль должен быть текстом.',

            'password.string' => 'Новый пароль должен быть текстом.',
            'password.min' => 'Новый пароль должен содержать минимум 4 символа.',
            'password.confirmed' => 'Подтверждение нового пароля не совпадает.',

        ]);
    // Аватар — напрямую в public/img/avatars (не storage)
    if ($request->hasFile('avatar')) {
        $this->deleteUserAvatar($user->avatar);

        $file = $request->file('avatar');
        $dir = public_path("img/avatars/{$user->id}");  // ← public/img/avatars

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $file->hashName();
        $file->move($dir, $filename);
        $validated['avatar'] = "/img/avatars/{$user->id}/{$filename}";
    } else {
        unset($validated['avatar']);
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

        if ($request->filled('password')) {
            $user->notify(new MarketplaceAlert(
                'Пароль изменён',
                'Пароль вашего аккаунта был успешно обновлён. Если это были не вы, немедленно свяжитесь с поддержкой.',
                null,
                NotificationCategory::Security,
            ));
        }

        return back()->with('success', 'Профиль успешно обновлён');
    }
    public function updatePhone(Request $request)
    {
        return $this->sendPhoneCode($request);
    }

    public function sendPhoneCode(Request $request): JsonResponse
    {
        $phone = $this->normalizePhone($request->input('phone'));
        $request->merge(['phone' => $phone]);

        $validated = $request->validate([
            'phone' => [
                'required',
                'string',
                'regex:/^7\d{10}$/',
                'max:11',
                Rule::unique('users', 'phone')->ignore($request->user()->id),
            ],
        ], [
            'phone.required' => 'Укажите номер телефона.',
            'phone.regex' => 'Номер телефона должен быть в формате 7XXXXXXXXXX (например, 79991234567).',
            'phone.max' => 'Номер телефона не должен превышать 11 символов.',
            'phone.unique' => 'Этот номер телефона уже привязан к другому аккаунту.',
        ]);

        $otp = app(OtpCodeGenerator::class)->forSms();
        $request->session()->put('profile_phone_pending', $validated['phone']);
        $request->session()->put('profile_phone_otp_hash', Hash::make($otp));
        $request->session()->put('profile_phone_otp_expires', now()->addMinutes(10)->timestamp);

        return response()->json([
            'success' => true,
            'message' => 'Код отправлен. Повторная отправка будет доступна через 60 секунд.',
            'cooldown_seconds' => 60,
        ]);
    }

    public function verifyPhoneCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ], [
            'code.required' => 'Введите код подтверждения.',
            'code.size' => 'Код должен состоять из 6 цифр.',
        ]);

        $phone = $request->session()->get('profile_phone_pending');
        $otpHash = $request->session()->get('profile_phone_otp_hash');
        $expires = $request->session()->get('profile_phone_otp_expires');

        if (
            ! $phone
            || ! $otpHash
            || ! Hash::check($request->input('code'), $otpHash)
            || ! $expires
            || now()->timestamp > $expires
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный или истёкший код подтверждения.',
            ], 422);
        }

        $phoneTaken = User::where('phone', $phone)
            ->whereKeyNot($request->user()->id)
            ->exists();

        if ($phoneTaken) {
            return response()->json([
                'success' => false,
                'message' => 'Этот номер телефона уже привязан к другому аккаунту.',
            ], 422);
        }

        $request->user()->update(['phone' => $phone]);
        $request->session()->forget(['profile_phone_pending', 'profile_phone_otp_hash', 'profile_phone_otp_expires']);

        return response()->json([
            'success' => true,
            'phone' => $phone,
            'message' => 'Номер телефона подтверждён.',
        ]);
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

        $user->notify(new MarketplaceAlert(
            'Пароль изменён',
            'Пароль вашего аккаунта был успешно обновлён.',
            null,
            NotificationCategory::Security,
        ));

        return redirect()->back()->with('success', 'Пароль обновлен');
    }

    public function changeRole(Request $request, User $user)
    {
        $actor = auth()->user();
        $allowedRoles = $actor->canAssignStaffRoles()
            ? ['user', 'seller', 'pvz', 'moderator', 'admin']
            : ['user', 'seller'];

        $request->validate([
            'role' => 'required|in:'.implode(',', $allowedRoles),
        ]);

        if ($user->id === $actor->id || $user->id === 1) {
            return back()->with('error', 'Нельзя изменить роль этого пользователя');
        }

        if ($actor->isModerator() && $user->isStaff()) {
            return back()->with('error', 'Недостаточно прав для изменения роли этого пользователя');
        }

        $check = app(UserRestrictionService::class)->canAssignRole($user, $request->role);
        if (! $check['ok']) {
            return back()->with('error', $check['message']);
        }

        $previousRole = $user->role;
        $user->update(['role' => $request->role]);

        if ($previousRole !== $request->role) {
            app(\App\Services\MarketplaceAuditService::class)->logRoleChanged(
                $user,
                $actor,
                $previousRole,
                $request->role,
            );
        }

        return back()->with('success', 'Роль пользователя изменена');
    }

    public function destroy(Request $request, ?User $user = null): RedirectResponse
    {
        if ($user) {
            $actor = auth()->user();

            if ($user->id === $actor->id || $user->id === 1) {
                return back()->with('error', 'Нельзя удалить этого пользователя');
            }

            if ($actor->isModerator() && $user->isStaff()) {
                return back()->with('error', 'Модератор не может удалять администраторов и других модераторов');
            }

            if ($user->role === 'admin') {
                return back()->with('error', 'Нельзя удалить администратора');
            }

            $hasOrders = \App\Models\Order::where('buyer_id', $user->id)
                ->whereNotIn('status', Order::statusesAllowingUserDeletion())
                ->exists();
            if ($hasOrders) {
                return back()->with('error', 'Нельзя удалить пользователя: есть активные заказы. Дождитесь их завершения.');
            }

            if ($user->role === 'seller') {
                $hasProducts = \App\Models\Product::where('seller_id', $user->id)->exists();
                $hasActiveSales = \App\Models\OrderItem::where('seller_id', $user->id)
                    ->whereHas('order', fn($q) => $q->whereNotIn('status', Order::statusesAllowingUserDeletion()))
                    ->exists();
                if ($hasProducts || $hasActiveSales) {
                    return back()->with('error', 'Нельзя удалить продавца: есть товары или активные продажи');
                }
            }

            $user->delete();
            return back()->with('success', 'Аккаунт деактивирован. Его можно восстановить в течение 30 дней.');
        }
        $user = $request->user();
        if ($user) {
            if (auth()->user()->is_blocked === 1) {
                return back()->with('error', 'Ваш аккаунт заблокирован');
            }

            $check = app(\App\Services\AccountDeletionService::class)->canDeleteOwnAccount($user);
            if (! $check['ok']) {
                return back()->with('error', $check['message']);
            }

            Auth::logout();
            $user->delete();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return Redirect::to('/');
        }
    }

    private function markFavorites(Collection $products, bool $favoriteOnly = false): void
    {
        if (! auth()->check()) {
            $products->each(function ($product) use ($favoriteOnly) {
                $product->is_favorite = false;
                $product->favorite_variant_ids = [];
                $product->favorite_only = $favoriteOnly;
            });

            return;
        }

        $favoriteProductIds = DB::table('favorites')
            ->where('user_id', auth()->id())
            ->whereNull('variant_id')
            ->pluck('product_id')
            ->toArray();

        $favoriteVariantIdsByProduct = DB::table('favorites')
            ->where('user_id', auth()->id())
            ->whereNotNull('variant_id')
            ->select('product_id', 'variant_id')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('variant_id')->map(fn ($id) => (int) $id)->values()->all());

        $products->each(function ($product) use ($favoriteProductIds, $favoriteVariantIdsByProduct, $favoriteOnly) {
            $variantIds = $favoriteVariantIdsByProduct->get($product->id, []);
            $product->favorite_variant_ids = $variantIds;
            $product->favorite_only = $favoriteOnly;
            $product->is_favorite = in_array($product->id, $favoriteProductIds) || $variantIds !== [];
        });
    }

    private function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/\D/', '', (string) $phone);
        if (str_starts_with($phone, '8')) {
            $phone = '7' . substr($phone, 1);
        }

        return $phone;
    }

    private function deleteUserAvatar(?string $avatarPath): void
    {
        if (empty($avatarPath)) {
            return;
        }
        
        // Полный путь к файлу
        $fullPath = public_path($avatarPath);
        
        // Удаляем файл если существует
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
        
        // Удаляем папку пользователя если она пустая
        $userDir = dirname($fullPath);
        if (is_dir($userDir) && count(scandir($userDir)) === 2) { // Только . и ..
            rmdir($userDir);
        }
    }
}
