<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\UserSession;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Product;
use App\Notifications\MarketplaceAlert;
use App\Services\OrderLedgerService;
use App\Services\StripeRefundService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminController extends Controller
{
    public function index(Request $request)
    {

        $query = User::query();
        $search = request('search');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $query->orderBy('role', 'desc') // 1. Сначала по роли

            ->orderByRaw('(
                SELECT COUNT(*)
                FROM nfts
                WHERE nfts.user_id = users.id
                AND nfts.status = "moderation"
            ) DESC')
            ->orderBy('is_blocked', 'asc');
        $usersData = $query->get()->map(function ($user) {
            $userNfts = Product::where('user_id', $user->id)->get();
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'is_blocked' => $user->is_blocked,
                'numbertel' => $user->numbertel ?? 'Нет номера',
                'avatar' => $user->avatar,
                'nft' => $userNfts, // добавляем NFT как массив
            ];
        });

        // Получаем активные сессии с данными пользователей
        $sessions = DB::table('sessions')
            ->join('users', 'sessions.user_id', '=', 'users.id')
            ->select(
                'sessions.id as session_id',
                'sessions.user_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->whereNotNull('sessions.user_id')
            ->orderBy('sessions.last_activity', 'desc')
            ->get()
            ->map(function ($session) {
                // Преобразуем last_activity (timestamp) в Carbon
                $session->last_activity = Carbon::createFromTimestamp($session->last_activity);
                return $session;
            });
        return Inertia::render('Admin/Index', [
            'users' => $usersData,
            'nft' => $usersData,
            'sessions' => $sessions,
            'currentSessionId' => $request->session()->getId(),
            'search' => $search,
        ]);
    }

    public function showUser(User $user)
    {
        $myNfts = Product::where('user_id', $user->id)
            ->with(['category', 'user'])
            ->get();
        return Inertia::render('Admin/Show', [
            'nfts' => $myNfts,
            'user' => $user,
        ]);
        return inertia('Admin/Show', compact('user', 'nfts'));
    }

    public function nftbuy(Request $request)
    {
        $productId = $request->nft['id'];
        $product = Product::where('id', $productId)->first();
        $product->update([
            'status' => 'relevant',
        ]);
        return redirect()->back();
    }
    public function nftstop(Request $request)
    {
        $productId = $request->nft['id'];
        $product = Product::where('id', $productId)->first();
        $product->update([
            'status' => 'rejection',
        ]);
        return redirect()->back();
    }
    public function nftsold(Request $request)
    {
        $productId = $request->nft['id'];
        $product = Product::where('id', $productId)->first();
        $product->update([
            'status' => 'sold',
        ]);
        return redirect()->back();
    }

    public function destroy(Request $request, string $sessionId)
    {
        if ($sessionId === $request->session()->getId()) {
            return back()->with('error', 'Нельзя завершить свою собственную сессию.');
        }
        DB::table('sessions')->where('id', $sessionId)->delete();

        return back()->with('success', 'Сессия завершена');
    }

    public function dashboard(Request $request)
    {
        $onlineThreshold = time() - 300; // 5 минут

        $stats = [
            'total_users'      => User::count(),
            'active_users'     => User::where(fn($q) => $q->where('is_blocked', false)->orWhereNull('is_blocked'))->count(),
            'blocked_users'    => User::where('is_blocked', true)->count(),
            'total_sellers'    => User::where('role', 'seller')->count(),
            'total_orders'     => Order::count(),
            'orders_today'     => Order::whereDate('created_at', today())->count(),
            'revenue_total'    => (float) Order::whereNotIn('status', [Order::STATUS_CANCELED, Order::STATUS_REFUSED])->sum('total'),
            'platform_commission_total' => (float) OrderItem::query()
                ->where('commission_status', 'finalized')
                ->sum('commission_amount'),
            'pending_approvals'=> User::whereHas('sellerProfile')
                                      ->where('role', 'user')->count(),
            'online_count'     => DB::table('sessions')
                                      ->whereNotNull('user_id')
                                      ->where('last_activity', '>=', $onlineThreshold)
                                      ->distinct('user_id')
                                      ->count('user_id'),
        ];

        $pendingSellers = User::with('sellerProfile')
            ->whereHas('sellerProfile')
            ->where('role', 'user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($u) => [
                'id'             => $u->id,
                'name'           => $u->name,
                'last_name'      => $u->last_name,
                'email'          => $u->email,
                'phone'          => $u->phone,
                'avatar'         => $u->avatar,
                'created_at'     => $u->created_at,
                'seller_profile' => $u->sellerProfile ? [
                    'shop_name'      => $u->sellerProfile->shop_name,
                    'inn'            => $u->sellerProfile->inn,
                    'legal_address'  => $u->sellerProfile->legal_address,
                    'pickup_address' => $u->sellerProfile->pickup_address,
                    'description'    => $u->sellerProfile->description,
                ] : null,
            ]);

        // Список пользователей: расширен до 200, поиск — клиентский
        $users = User::withTrashed()
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'last_name'  => $u->last_name,
                'email'      => $u->email,
                'phone'      => $u->phone,
                'role'       => $u->role,
                'is_blocked' => $u->is_blocked,
                'avatar'     => $u->avatar,
                'deleted_at' => $u->deleted_at,
                'created_at' => $u->created_at,
            ]);

        // Поиск заказов — только точное совпадение (номер, код, суточный код, ID/email/телефон)
        $orderSearch = trim((string) $request->input('order_search', ''));
        $orderResults = $orderSearch !== ''
            ? $this->searchOrdersExact($orderSearch)
            : collect();

        // Сессии — джоиним с пользователями
        $sessions = DB::table('sessions')
            ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
            ->select(
                'sessions.id as id',
                'sessions.user_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.name as user_name',
                'users.last_name as user_last_name',
                'users.email as user_email',
                'users.avatar as user_avatar',
                'users.role as user_role',
                'users.is_blocked as user_is_blocked',
            )
            ->orderBy('sessions.last_activity', 'desc')
            ->limit(200)
            ->get()
            ->map(function ($s) use ($onlineThreshold) {
                $s->is_online      = $s->last_activity >= $onlineThreshold;
                $s->last_activity  = Carbon::createFromTimestamp($s->last_activity)->toIso8601String();
                return $s;
            });

        $loginHistory = DB::table('account_login_events')
            ->leftJoin('users', 'account_login_events.user_id', '=', 'users.id')
            ->select(
                'account_login_events.id',
                'account_login_events.user_id',
                'account_login_events.session_id',
                'account_login_events.ip_address',
                'account_login_events.user_agent',
                'account_login_events.login_method',
                'account_login_events.created_at',
                'users.name as user_name',
                'users.last_name as user_last_name',
                'users.email as user_email',
                'users.avatar as user_avatar',
            )
            ->orderByDesc('account_login_events.created_at')
            ->limit(200)
            ->get()
            ->map(function ($event) {
                $event->created_at = Carbon::parse($event->created_at)->toIso8601String();

                return $event;
            });

        // Revenue chart — последние 30 дней
        $chartFrom = Carbon::today()->subDays(29);
        $rows = Order::whereNotIn('status', [Order::STATUS_CANCELED, Order::STATUS_REFUSED])
            ->where('created_at', '>=', $chartFrom)
            ->selectRaw('DATE(created_at) as day, SUM(total) as revenue, COUNT(*) as orders_count')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $revenueChart = [];
        for ($i = 0; $i < 30; $i++) {
            $day = $chartFrom->copy()->addDays($i)->toDateString();
            $row = $rows->get($day);
            $revenueChart[] = [
                'date'    => $day,
                'revenue' => (float) ($row->revenue ?? 0),
                'count'   => (int)   ($row->orders_count ?? 0),
            ];
        }

        return Inertia::render('Admin/Dashboard', [
            'stats'            => $stats,
            'pendingSellers'   => $pendingSellers,
            'users'            => $users,
            'orderSearch'      => $orderSearch,
            'orderResults'     => $orderResults,
            'sessions'         => $sessions,
            'loginHistory'     => $loginHistory,
            'currentSessionId' => $request->session()->getId(),
            'revenueChart'     => $revenueChart,
        ]);
    }

    public function userDetail($userId)
    {
        $user = User::withTrashed()->with('sellerProfile')->findOrFail($userId);
        $onlineThreshold = time() - 300;

        $orders = Order::with(['items.variant.product.images'])
            ->where('buyer_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($o) => [
                'id'               => $o->id,
                'number'           => $o->number,
                'total'            => $o->total,
                'discount'         => $o->discount,
                'status'           => $o->status,
                'payment_status'   => $o->payment_status,
                'delivery_method'  => $o->delivery_method,
                'delivery_address' => $o->delivery_address,
                'comment'          => $o->comment,
                'created_at'       => $o->created_at,
                'items'            => $o->items->map(fn($item) => [
                    'id'                => $item->id,
                    'quantity'          => $item->quantity,
                    'price_at_purchase' => $item->price_at_purchase,
                    'product_name'      => $item->variant?->product?->title ?? '—',
                    'product_image'     => $item->variant?->product?->images?->firstWhere('is_main', true)?->url ?? null,
                    'seller_id'         => $item->seller_id,
                ]),
            ]);

        $sellerOrders = OrderItem::with(['order', 'variant.product.images'])
            ->where('seller_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($item) => [
                'id'                => $item->id,
                'quantity'          => $item->quantity,
                'price_at_purchase' => $item->price_at_purchase,
                'product_name'      => $item->variant?->product?->title ?? '—',
                'product_image'     => $item->variant?->product?->images?->firstWhere('is_main', true)?->url ?? null,
                'order_number'      => $item->order?->number ?? '—',
                'order_status'      => $item->order?->status ?? '—',
                'created_at'        => $item->created_at,
            ]);

        $products = Product::where('seller_id', $user->id)
            ->withCount('variants')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'                 => $p->id,
                'name'               => $p->title,
                'min_price'          => $p->min_price,
                'image'              => $p->images()->where('is_main', true)->value('url'),
                'status'             => $p->status,
                'moderation_comment' => $p->moderation_comment,
                'variants_count'     => $p->variants_count,
                'created_at'         => $p->created_at,
            ]);

        $userData = [
            'id'             => $user->id,
            'name'           => $user->name,
            'last_name'      => $user->last_name,
            'email'          => $user->email,
            'phone'          => $user->phone,
            'role'           => $user->role,
            'is_blocked'     => $user->is_blocked,
            'avatar'         => $user->avatar,
            'created_at'     => $user->created_at,
            'deleted_at'     => $user->deleted_at,
            'seller_profile' => $user->sellerProfile ? [
                'shop_name'      => $user->sellerProfile->shop_name,
                'inn'            => $user->sellerProfile->inn,
                'legal_address'  => $user->sellerProfile->legal_address,
                'pickup_address' => $user->sellerProfile->pickup_address,
                'rating'         => $user->sellerProfile->rating,
                'total_sales'    => $user->sellerProfile->total_sales,
            ] : null,
            'has_orders'     => Order::where('buyer_id', $user->id)
                ->whereNotIn('status', Order::statusesAllowingUserDeletion())
                ->exists(),
            'orders_count'   => Order::where('buyer_id', $user->id)->count(),
            'has_sales'      => OrderItem::where('seller_id', $user->id)
                ->whereHas('order', fn ($q) => $q->whereNotIn('status', Order::statusesAllowingUserDeletion()))
                ->exists(),
            'has_products'   => Product::where('seller_id', $user->id)->exists(),
        ];

        $userSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->limit(30)
            ->get()
            ->map(function ($s) use ($onlineThreshold) {
                $s->is_online     = $s->last_activity >= $onlineThreshold;
                $s->last_activity = Carbon::createFromTimestamp($s->last_activity)->toIso8601String();
                return $s;
            });

        $userLoginHistory = DB::table('account_login_events')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($event) {
                $event->created_at = Carbon::parse($event->created_at)->toIso8601String();

                return $event;
            });

        return Inertia::render('Admin/UserDetail', [
            'user'             => $userData,
            'orders'           => $orders,
            'sellerOrders'     => $sellerOrders,
            'products'         => $products,
            'userSessions'     => $userSessions,
            'userLoginHistory' => $userLoginHistory,
            'currentSessionId' => request()->session()->getId(),
        ]);
    }

    public function approveSeller(User $user)
    {
        $user->update(['role' => 'seller']);
        return back()->with('success', 'Продавец одобрен');
    }

    public function rejectSeller(User $user)
    {
        $user->sellerProfile?->delete();
        $user->update(['role' => 'user']);
        return back()->with('success', 'Заявка продавца отклонена');
    }

    public function updateProductStatus(Request $request, Product $product)
    {
        $request->validate([
            'status' => 'required|in:draft,moderation,approved,rejected,archived,hidden',
            'moderation_comment' => 'nullable|string|max:500',
        ]);

        $status = $request->status;
        $comment = trim((string) $request->moderation_comment);

        if ($status === 'rejected' && $comment === '') {
            return back()->withErrors([
                'moderation_comment' => 'При отклонении укажите комментарий для продавца — он увидит его в кабинете.',
            ]);
        }

        $product->update([
            'status' => $status,
            'moderation_comment' => $comment !== '' ? $comment : null,
            'is_on_action' => $status === 'approved',
        ]);

        $statusLabels = [
            'draft' => 'черновик',
            'moderation' => 'на модерации',
            'approved' => 'одобрен',
            'rejected' => 'отклонён',
            'archived' => 'в архиве',
            'hidden' => 'скрыт',
        ];

        $seller = User::query()->find($product->seller_id);
        if ($seller) {
            $message = sprintf(
                'Товар «%s» переведён в статус: %s.',
                $product->title,
                $statusLabels[$status] ?? $status,
            );
            if ($comment !== '') {
                $message .= ' Комментарий модератора: '.$comment;
            }

            $seller->notify(new MarketplaceAlert(
                'Статус товара обновлён',
                $message,
                route('seller.products.edit', $product, false),
            ));
        }

        return back()->with('success', 'Статус товара обновлён');
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => ['required', Rule::in(Order::allStatuses())],
        ]);
        $newStatus = $request->status;

        if (! $order->canSetDeliveryStatus($newStatus)) {
            return back()->with('error', 'Нельзя выдать неоплаченный заказ. Остальные статусы доставки доступны.');
        }

        $order->update(['status' => $newStatus]);

        $message = 'Статус заказа обновлён';

        if (in_array($newStatus, [Order::STATUS_CANCELED, Order::STATUS_REFUSED], true)) {
            $refund = app(StripeRefundService::class)->handleOrderCanceledOrRefused($order, 'admin_'.$newStatus);
            app(OrderLedgerService::class)->reverseCommission($order->fresh());
            if ($refund['refunded']) {
                $message .= '. '.$refund['message'];
            } elseif (! $refund['ok']) {
                return back()->with('error', $message.'. '.$refund['message']);
            }
        } elseif ($newStatus === Order::STATUS_ISSUED) {
            app(OrderLedgerService::class)->finalizeCommission($order->fresh());
        }

        $order->loadMissing('buyer');
        if ($order->buyer) {
            $order->buyer->notify(new MarketplaceAlert(
                'Заказ обновлён',
                sprintf('Заказ №%s — новый статус: %s.', $order->number ?? (string) $order->id, $newStatus),
                route('order.show', $order, false),
            ));
        }

        return back()->with('success', $message);
    }

    public function restoreUser($userId)
    {
        $user = User::withTrashed()->findOrFail($userId);
        if (!$user->trashed()) {
            return back()->with('error', 'Пользователь не удалён');
        }
        $user->restore();
        return back()->with('success', 'Аккаунт восстановлен');
    }

    private function parseDateRange(Request $request): array
    {
        $from = $request->input('from');
        $to   = $request->input('to');
        $from = $from ? Carbon::parse($from)->startOfDay() : null;
        $to   = $to   ? Carbon::parse($to)->endOfDay()   : null;
        return [$from, $to];
    }

    private function streamCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->stream(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM — для правильного открытия в Excel с кириллицей
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportRevenue(Request $request): StreamedResponse
    {
        [$from, $to] = $this->parseDateRange($request);
        $minTotal = $request->input('min_total');
        $maxTotal = $request->input('max_total');
        $status   = $request->input('status', 'paid'); // по умолчанию только успешные

        $q = Order::with(['buyer' => fn($q) => $q->withTrashed(), 'items']);
        if ($from)   $q->where('created_at', '>=', $from);
        if ($to)     $q->where('created_at', '<=', $to);
        if ($minTotal !== null && $minTotal !== '') $q->where('total', '>=', (float) $minTotal);
        if ($maxTotal !== null && $maxTotal !== '') $q->where('total', '<=', (float) $maxTotal);
        if ($status === 'paid') {
            $q->whereNotIn('status', [Order::STATUS_CANCELED, Order::STATUS_REFUSED]);
        } elseif ($status !== 'all') {
            $q->where('status', $status);
        }
        $orders = $q->orderBy('created_at', 'desc')->get();

        $rangeLabel = ($from ? $from->format('Y-m-d') : 'all') . '_' . ($to ? $to->format('Y-m-d') : 'all');
        $filename   = "revenue_{$rangeLabel}.csv";

        $headers = ['№ заказа', 'Код', 'Дата', 'Покупатель', 'Email', 'Телефон', 'Позиций', 'Сумма', 'Скидка', 'Комиссия платформы', 'К выплате продавцам', 'Статус', 'Статус оплаты'];
        $rows = $orders->map(fn($o) => [
            $o->number,
            $o->order_code,
            $o->created_at?->format('Y-m-d H:i'),
            trim(($o->buyer->name ?? '') . ' ' . ($o->buyer->last_name ?? '')) ?: '—',
            $o->buyer->email ?? '',
            $o->buyer->phone ?? '',
            $o->items->count(),
            number_format($o->total, 2, '.', ''),
            number_format($o->discount ?? 0, 2, '.', ''),
            number_format($o->items->sum('commission_amount'), 2, '.', ''),
            number_format($o->items->sum(fn ($i) => $i->seller_payout_amount > 0
                ? $i->seller_payout_amount
                : ($i->price_at_purchase * $i->quantity) - $i->commission_amount), 2, '.', ''),
            $o->status,
            $o->payment_status,
        ]);
        $sum  = $orders->sum('total');
        $commissionSum = $orders->sum(fn ($o) => $o->items->sum('commission_amount'));
        $payoutSum = $orders->sum(fn ($o) => $o->items->sum(fn ($i) => $i->seller_payout_amount > 0
            ? $i->seller_payout_amount
            : ($i->price_at_purchase * $i->quantity) - $i->commission_amount));
        $rows->push(['', '', '', '', '', '', 'ИТОГО:', number_format($sum, 2, '.', ''), '', number_format($commissionSum, 2, '.', ''), number_format($payoutSum, 2, '.', ''), '', '']);

        return $this->streamCsv($filename, $headers, $rows);
    }

    public function exportUserReport($userId, Request $request): StreamedResponse
    {
        $user = User::withTrashed()->findOrFail($userId);
        [$from, $to] = $this->parseDateRange($request);

        // Покупки
        $buyerOrders = Order::with('items.variant.product')
            ->where('buyer_id', $user->id)
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to,   fn($q) => $q->where('created_at', '<=', $to))
            ->orderBy('created_at', 'desc')
            ->get();

        // Продажи (если продавец)
        $sellerItems = OrderItem::with(['order', 'variant.product'])
            ->where('seller_id', $user->id)
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to,   fn($q) => $q->where('created_at', '<=', $to))
            ->orderBy('created_at', 'desc')
            ->get();

        $rangeLabel = ($from ? $from->format('Y-m-d') : 'all') . '_' . ($to ? $to->format('Y-m-d') : 'all');
        $name       = preg_replace('/[^A-Za-z0-9_-]/', '_', $user->name ?? 'user');
        $filename   = "report_user{$user->id}_{$name}_{$rangeLabel}.csv";

        return response()->stream(function () use ($user, $buyerOrders, $sellerItems) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            // Шапка
            fputcsv($out, ['ОТЧЁТ ПО ПОЛЬЗОВАТЕЛЮ'], ';');
            fputcsv($out, ['ID', $user->id], ';');
            fputcsv($out, ['Имя', trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''))], ';');
            fputcsv($out, ['Email', $user->email ?? ''], ';');
            fputcsv($out, ['Телефон', $user->phone ?? ''], ';');
            fputcsv($out, ['Роль', $user->role], ';');
            fputcsv($out, []);

            // Покупки
            if ($buyerOrders->count() > 0) {
                fputcsv($out, ['ПОКУПКИ'], ';');
                fputcsv($out, ['№ заказа', 'Дата', 'Позиций', 'Сумма', 'Статус'], ';');
                foreach ($buyerOrders as $o) {
                    fputcsv($out, [
                        $o->number,
                        $o->created_at?->format('Y-m-d H:i'),
                        $o->items->count(),
                        number_format($o->total, 2, '.', ''),
                        $o->status,
                    ], ';');
                }
                fputcsv($out, ['ИТОГО покупок:', '', '', number_format($buyerOrders->sum('total'), 2, '.', ''), ''], ';');
                fputcsv($out, []);
            }

            // Продажи
            if ($sellerItems->count() > 0) {
                fputcsv($out, ['ПРОДАЖИ'], ';');
                fputcsv($out, ['№ заказа', 'Дата', 'Товар', 'Кол-во', 'Цена', 'Сумма', 'Комиссия', 'К выплате', 'Статус заказа'], ';');
                $totalRevenue = 0;
                $totalCommission = 0;
                $totalPayout = 0;
                foreach ($sellerItems as $i) {
                    $sum = (float) $i->price_at_purchase * (int) $i->quantity;
                    $commission = (float) $i->commission_amount;
                    $payout = (float) $i->seller_payout_amount > 0
                        ? (float) $i->seller_payout_amount
                        : $sum - $commission;
                    $totalRevenue += $sum;
                    $totalCommission += $commission;
                    $totalPayout += $payout;
                    fputcsv($out, [
                        $i->order?->number ?? '—',
                        $i->created_at?->format('Y-m-d H:i'),
                        $i->variant?->product?->title ?? '—',
                        $i->quantity,
                        number_format($i->price_at_purchase, 2, '.', ''),
                        number_format($sum, 2, '.', ''),
                        number_format($commission, 2, '.', ''),
                        number_format($payout, 2, '.', ''),
                        $i->order?->status ?? '—',
                    ], ';');
                }
                fputcsv($out, ['ИТОГО продаж:', '', '', '', '', number_format($totalRevenue, 2, '.', ''), number_format($totalCommission, 2, '.', ''), number_format($totalPayout, 2, '.', ''), ''], ';');
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportOrderReceipt(Order $order)
    {
        $order->load([
            'buyer' => fn($q) => $q->withTrashed(),
            'items.variant.product',
            'items.seller' => fn($q) => $q->withTrashed(),
        ]);

        // Группируем позиции по продавцам — чтобы в чеке было видно "от кого"
        $bySeller = $order->items->groupBy(fn($i) => $i->seller_id);

        $pdf = Pdf::loadView('pdf.order-receipt', [
            'order'    => $order,
            'bySeller' => $bySeller,
        ])
            ->setPaper('a5', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download("receipt_order_{$order->number}.pdf");
    }

    public function products(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'all');
        $perPage = 50;

        $query = Product::with(['seller', 'category', 'images', 'variants:id,product_id,sku'])
            ->orderBy('created_at', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('id', $search)
                  ->orWhereHas('seller', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('id', $search);
                  })
                  ->orWhereHas('variants', function ($q2) use ($search) {
                      $q2->where('sku', 'like', '%'.$search.'%');
                  });
            });
        }

        $products = $query->paginate($perPage)->withQueryString();
        $productItems = $products->getCollection()->map(fn($p) => [
            'id'                 => $p->id,
            'title'              => $p->title,
            'min_price'          => $p->min_price,
            'status'             => $p->status,
            'moderation_comment' => $p->moderation_comment,
            'is_on_action'       => $p->is_on_action,
            'sales_count'        => $p->sales_count,
            'views_count'        => $p->views_count,
            'image'              => $p->images?->firstWhere('is_main', true)?->url,
            'created_at'         => $p->created_at,
            'category'           => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name] : null,
            'seller'             => $p->seller ? [
                'id'    => $p->seller->id,
                'name'  => $p->seller->name,
                'email' => $p->seller->email,
            ] : null,
            'variant_skus'       => $p->variants?->pluck('sku')->filter()->values()->all() ?? [],
        ]);

        $counts = [
            'all'        => Product::count(),
            'moderation' => Product::where('status', 'moderation')->count(),
            'approved'   => Product::where('status', 'approved')->count(),
            'rejected'   => Product::where('status', 'rejected')->count(),
            'hidden'     => Product::where('status', 'hidden')->count(),
            'archived'   => Product::where('status', 'archived')->count(),
            'draft'      => Product::where('status', 'draft')->count(),
        ];

        return Inertia::render('Admin/Products', [
            'products' => $productItems,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'has_more' => $products->hasMorePages(),
            ],
            'search'   => $search,
            'status'   => $status,
            'counts'   => $counts,
        ]);
    }

    /**
     * Поиск заказов в админке: только полное совпадение, без LIKE-подстрок.
     */
    private function searchOrdersExact(string $orderSearch)
    {
        $normalized = preg_replace('/\s+/', '', $orderSearch);
        $digitsOnly = preg_replace('/\D+/', '', $orderSearch);
        $orderCode = strtoupper($normalized);
        $dailyCodeFormatted = (strlen($digitsOnly) === 8)
            ? substr($digitsOnly, 0, 4).' '.substr($digitsOnly, 4, 4)
            : null;

        return Order::with([
            'buyer' => fn ($q) => $q->withTrashed(),
            'items.variant.product.images',
        ])
            ->where(function ($q) use ($orderSearch, $normalized, $orderCode, $dailyCodeFormatted, $digitsOnly) {
                // Номер заказа (ORD-...) — целиком
                $q->where('number', $orderSearch);
                if ($normalized !== '' && $normalized !== $orderSearch) {
                    $q->orWhere('number', $normalized);
                }

                // Код выдачи — ровно 10 символов
                if (strlen($orderCode) === 10) {
                    $q->orWhere('order_code', $orderCode);
                }

                // Суточный код покупателя — ровно 8 цифр (1234 5678 или 12345678)
                if ($dailyCodeFormatted !== null) {
                    $q->orWhereHas('buyer', fn ($b) => $b->withTrashed()
                        ->where('daily_pickup_code', $dailyCodeFormatted));
                }

                // ID покупателя
                if (ctype_digit($orderSearch) && (int) $orderSearch > 0) {
                    $q->orWhereHas('buyer', fn ($b) => $b->withTrashed()
                        ->where('id', (int) $orderSearch));
                }

                // Email — целиком
                if (filter_var($orderSearch, FILTER_VALIDATE_EMAIL)) {
                    $q->orWhereHas('buyer', fn ($b) => $b->withTrashed()
                        ->where('email', $orderSearch));
                }

                // Телефон — только при полном номере (от 10 цифр)
                if (strlen($digitsOnly) >= 10) {
                    $q->orWhereHas('buyer', function ($b) use ($digitsOnly) {
                        $b->withTrashed()
                            ->where(function ($phoneQ) use ($digitsOnly) {
                                $phoneQ->where('phone', $digitsOnly)
                                    ->orWhereRaw(
                                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?",
                                        [$digitsOnly]
                                    );
                            });
                    });
                }
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($o) => [
                'id'               => $o->id,
                'number'           => $o->number,
                'order_code'       => $o->order_code,
                'total'            => $o->total,
                'discount'         => $o->discount,
                'status'           => $o->status,
                'payment_status'   => $o->payment_status,
                'delivery_method'  => $o->delivery_method,
                'delivery_address' => $o->delivery_address,
                'comment'          => $o->comment,
                'created_at'       => $o->created_at,
                'items_count'      => $o->items->count(),
                'buyer'            => $o->buyer ? [
                    'id'         => $o->buyer->id,
                    'name'       => $o->buyer->name,
                    'last_name'  => $o->buyer->last_name,
                    'email'      => $o->buyer->email,
                    'phone'      => $o->buyer->phone,
                    'avatar'     => $o->buyer->avatar,
                    'role'       => $o->buyer->role,
                    'is_blocked' => $o->buyer->is_blocked,
                    'deleted_at' => $o->buyer->deleted_at,
                ] : null,
                'items'            => $o->items->map(fn ($item) => [
                    'id'                => $item->id,
                    'quantity'          => $item->quantity,
                    'price_at_purchase' => $item->price_at_purchase,
                    'product_name'      => $item->variant?->product?->title ?? '—',
                    'product_image'     => $item->variant?->product?->images?->firstWhere('is_main', true)?->url ?? null,
                ]),
            ]);
    }
}
