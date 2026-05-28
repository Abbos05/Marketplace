<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupPoint;
use App\Models\PickupPointStaff;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\UserSession;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Product;
use App\Notifications\MarketplaceAlert;
use App\Support\NotificationCategory;
use App\Services\OrderNotificationService;
use App\Services\OrderLedgerService;
use App\Services\PvzAdminOverviewService;
use App\Services\PvzNotificationService;
use App\Services\SellerProfileModerationService;
use App\Services\UserRestrictionService;
use App\Services\RevenueChartQueryService;
use App\Services\Excel\AdminRevenueExcelExporter;
use App\Services\Excel\AdminUsersExcelExporter;
use App\Services\Excel\AdminUserReportExcelExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminController extends Controller
{

   
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
            'pending_approvals'=> User::query()
                ->where(function ($q) {
                    $q->whereHas('sellerProfile', fn ($p) => $p->whereNotNull('restore_requested_at'))
                        ->orWhere(fn ($q2) => $q2->whereHas('sellerProfile')->where('role', 'user'));
                })
                ->count(),
            'pending_shop_changes' => SellerProfile::query()->shopChangesPending()->count(),
            'pvz_pending_applications' => PickupPointStaff::query()
                ->where('status', PickupPointStaff::STATUS_PENDING)
                ->count(),
            'online_count'     => DB::table('sessions')
                                      ->whereNotNull('user_id')
                                      ->where('last_activity', '>=', $onlineThreshold)
                                      ->distinct('user_id')
                                      ->count('user_id'),
        ];

        $pendingSellers = User::with('sellerProfile')
            ->whereHas('sellerProfile')
            ->where(function ($q) {
                $q->whereHas('sellerProfile', fn ($p) => $p->whereNotNull('restore_requested_at'))
                    ->orWhere(function ($q2) {
                        $q2->where('role', 'user')
                            ->whereHas('sellerProfile', fn ($p) => $p->whereNull('restore_requested_at'));
                    });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($u) {
                $profile = $u->sellerProfile;
                $isRestore = $profile && $profile->isRestorePending();

                return [
                    'id'             => $u->id,
                    'name'           => $u->name,
                    'last_name'      => $u->last_name,
                    'email'          => $u->email,
                    'phone'          => $u->phone,
                    'avatar'         => $u->avatar,
                    'created_at'     => $u->created_at,
                    'application_type' => $isRestore ? 'restore' : 'new',
                    'restore_requested_at' => $profile?->restore_requested_at,
                    'seller_profile' => $profile ? [
                        'shop_name'      => $profile->shop_name,
                        'inn'            => $profile->inn,
                        'legal_address'  => $profile->legal_address,
                        'pickup_address' => $profile->pickup_address,
                        'description'    => $profile->description,
                    ] : null,
                ];
            });

        $restriction = app(UserRestrictionService::class);
        $usersPayload = $this->dashboardUsersPayload($request, $restriction);

        // Поиск заказов — только точное совпадение (номер, код, суточный код, ID/email/телефон)
        $orderSearch = trim((string) $request->input('order_search', ''));
        $orderResults = $orderSearch !== ''
            ? app(\App\Services\OrderSearchService::class)->mapOrdersForPanel(
                app(\App\Services\OrderSearchService::class)->searchExact($orderSearch)
            )
            : [];

        $sessionsPayload = $this->dashboardSessionsPayload($request, $onlineThreshold);
        $loginHistoryPayload = $this->dashboardLoginHistoryPayload($request);

        $chartService = app(RevenueChartQueryService::class);
        [$chartFrom, $chartTo] = $chartService->resolveRange(new Request(['period' => '30d']));
        $revenueChart = $chartService->getChartPayload($chartFrom, $chartTo, '30d')['data'];

        return Inertia::render('Admin/Dashboard', [
            'stats'            => $stats,
            'pendingSellers'   => $pendingSellers,
            'users'            => $usersPayload['items'],
            'usersMeta'        => $usersPayload['meta'],
            'orderSearch'      => $orderSearch,
            'orderResults'     => $orderResults,
            'sessions'         => $sessionsPayload['items'],
            'sessionsMeta'     => $sessionsPayload['meta'],
            'loginHistory'     => $loginHistoryPayload['items'],
            'loginHistoryMeta' => $loginHistoryPayload['meta'],
            'currentSessionId' => $request->session()->getId(),
            'revenueChart'     => $revenueChart,
        ]);
    }

    public function userDetail(Request $request, $userId)
    {
        $user = User::withTrashed()->with([
            'sellerProfile',
            'pickupPointStaff' => fn ($q) => $q->with(['proposedRegion', 'pickupPoint'])->orderByDesc('created_at'),
            'approvedPickupPointStaff.pickupPoint',
        ])->findOrFail($userId);

        $auditService = app(\App\Services\MarketplaceAuditService::class);
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

        $sellerSalesPayload = $this->userDetailSellerSalesPayload($request, $user->id);
        $sellerProductsPayload = $this->userDetailSellerProductsPayload($request, $user->id);

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
            'closed_seller_profile' => app(\App\Services\AccountDeletionService::class)->closedSellerProfilePayload($user->id),
            'seller_restore_pending' => app(\App\Services\AccountDeletionService::class)->sellerRestorePendingPayload($user),
            'pending_shop_changes' => app(SellerProfileModerationService::class)->pendingPayload($user->sellerProfile),
            'seller_history' => $auditService->sellerHistorySummary($user->id),
            'audit_events' => $auditService->eventsForUser($user->id),
            'has_orders'     => Order::where('buyer_id', $user->id)
                ->whereNotIn('status', Order::statusesAllowingUserDeletion())
                ->exists(),
            'orders_count'   => Order::where('buyer_id', $user->id)->count(),
            'has_sales'      => OrderItem::where('seller_id', $user->id)
                ->whereHas('order', fn ($q) => $q->whereNotIn('status', Order::statusesAllowingUserDeletion()))
                ->exists(),
            'has_products'   => Product::where('seller_id', $user->id)->exists(),
            'pvz_application' => ($pvzStaff = $user->pickupPointStaff
                ->sortBy(fn ($s) => match ($s->status) {
                    PickupPointStaff::STATUS_PENDING => 0,
                    PickupPointStaff::STATUS_APPROVED => 1,
                    default => 2,
                })
                ->first())
                ? PickupPointStaff::mapForUserDetail($pvzStaff)
                : null,
            'pvz_point' => $user->approvedPickupPointStaff?->pickupPoint ? [
                'id' => $user->approvedPickupPointStaff->pickupPoint->id,
                'title' => $user->approvedPickupPointStaff->pickupPoint->title,
                'address' => $user->approvedPickupPointStaff->pickupPoint->address,
                'is_active' => $user->approvedPickupPointStaff->pickupPoint->is_active,
                'closure_status' => $user->approvedPickupPointStaff->pickupPoint->closure_status,
            ] : null,
            ...app(UserRestrictionService::class)->roleFlagsFor($user),
            'assignable_roles' => app(UserRestrictionService::class)->assignableRolesFor(auth()->user(), $user),
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
            'sellerOrders'     => $sellerSalesPayload['items'],
            'sellerOrdersMeta' => $sellerSalesPayload['meta'],
            'products'         => $sellerProductsPayload['items'],
            'productsMeta'     => $sellerProductsPayload['meta'],
            'seller_history'   => $userData['seller_history'],
            'audit_events'     => $userData['audit_events'],
            'pvzOverview'      => app(PvzAdminOverviewService::class)->forUser($user),
            'userSessions'     => $userSessions,
            'userLoginHistory' => $userLoginHistory,
            'currentSessionId' => request()->session()->getId(),
        ]);
    }

    public function approveSeller(User $user)
    {
        if ($user->hasSellerRestorePending()) {
            app(\App\Services\AccountDeletionService::class)->approveSellerCompanyRestore($user, auth()->user());

            return back()->with('success', 'Восстановление компании одобрено. Продавец снова активен.');
        }

        $user->update(['role' => 'seller']);

        return back()->with('success', 'Продавец одобрен');
    }

    public function rejectSeller(User $user)
    {
        if ($user->hasSellerRestorePending()) {
            app(\App\Services\AccountDeletionService::class)->rejectSellerCompanyRestore($user, auth()->user());

            return back()->with('success', 'Заявка на восстановление компании отклонена.');
        }

        $user->sellerProfile?->delete();
        $user->update(['role' => 'user']);

        return back()->with('success', 'Заявка продавца отклонена');
    }

    public function approveShopChanges(User $user)
    {
        app(SellerProfileModerationService::class)->approveShopChanges($user, auth()->user());

        return back()->with('success', 'Изменения магазина одобрены и опубликованы.');
    }

    public function rejectShopChanges(User $user)
    {
        app(SellerProfileModerationService::class)->rejectShopChanges($user, auth()->user());

        return back()->with('success', 'Изменения магазина отклонены.');
    }

    public function approvePickupStaff(PickupPointStaff $pickupPointStaff)
    {
        if ($pickupPointStaff->status !== PickupPointStaff::STATUS_PENDING) {
            return back()->with('error', 'Заявка уже обработана.');
        }

        $user = $pickupPointStaff->user;
        if (! $user) {
            return back()->with('error', 'Пользователь не найден.');
        }

        if ($pickupPointStaff->type === PickupPointStaff::TYPE_OPEN) {
            $point = PickupPoint::query()->create([
                'title' => $pickupPointStaff->proposed_title,
                'address' => $pickupPointStaff->proposed_address,
                'region_id' => $pickupPointStaff->proposed_region_id,
                'is_active' => true,
                'sort_order' => 0,
            ]);
            $pickupPointStaff->pickup_point_id = $point->id;
        } else {
            if (! $pickupPointStaff->pickup_point_id) {
                return back()->with('error', 'Пункт выдачи не указан в заявке.');
            }
            if (PickupPointStaff::pickupPointHasApprovedStaff((int) $pickupPointStaff->pickup_point_id)) {
                return back()->with('error', 'На этом пункте уже есть оператор.');
            }
        }

        $pickupPointStaff->update([
            'status' => PickupPointStaff::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $user->update(['role' => 'pvz']);

        $pickupPointStaff->load('pickupPoint');
        $title = $pickupPointStaff->pickupPoint?->title ?? $pickupPointStaff->proposed_title ?? 'ПВЗ';
        app(PvzNotificationService::class)->notifyApplicationApproved($user, $title);

        return back()->with('success', 'Оператор ПВЗ одобрен.');
    }

    public function rejectPickupStaff(Request $request, PickupPointStaff $pickupPointStaff)
    {
        if ($pickupPointStaff->status !== PickupPointStaff::STATUS_PENDING) {
            return back()->with('error', 'Заявка уже обработана.');
        }

        $request->validate([
            'reject_reason' => 'nullable|string|max:500',
        ]);

        $pickupPointStaff->update([
            'status' => PickupPointStaff::STATUS_REJECTED,
            'reject_reason' => $request->reject_reason,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Заявка оператора ПВЗ отклонена.');
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

        $seller = User::withTrashed()->with('sellerProfile')->find($product->seller_id);
        $sellerCanPublish = $seller && ! $seller->trashed() && $seller->hasActiveSellerCompany();

        if ($status === 'approved' && ! $sellerCanPublish) {
            return back()->with('error', 'Нельзя вывести товар на витрину: компания продавца закрыта или аккаунт недоступен. Продавец должен восстановить компанию.');
        }

        $onCatalog = $status === 'approved' && $sellerCanPublish;

        $product->update([
            'status' => $status,
            'moderation_comment' => $comment !== '' ? $comment : null,
            'is_on_action' => $onCatalog,
        ]);

        $statusLabels = [
            'draft' => 'черновик',
            'moderation' => 'на модерации',
            'approved' => 'одобрен',
            'rejected' => 'отклонён',
            'archived' => 'в архиве',
            'hidden' => 'скрыт',
        ];

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
                NotificationCategory::SellerModeration,
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
        $actor = $request->user();

        if (! $order->canStaffAssignStatus($actor, $newStatus)) {
            return back()->with('error', 'Статусы «Выдан» и «Отказ от получения» может установить только администратор или сотрудник пункта выдачи.');
        }

        if (! $order->canSetDeliveryStatus($newStatus)) {
            return back()->with('error', 'Нельзя выдать неоплаченный заказ. Остальные статусы доставки доступны.');
        }

        $order->update(['status' => $newStatus]);

        $message = 'Статус заказа обновлён';

        if (in_array($newStatus, [Order::STATUS_CANCELED, Order::STATUS_REFUSED], true)) {
            app(OrderLedgerService::class)->reverseCommission($order->fresh());
            if ($order->payment_status === 'paid') {
                $message .= '. Покупатель подтвердит возврат средств в личном кабинете.';
            }
        } elseif ($newStatus === Order::STATUS_ISSUED) {
            app(OrderLedgerService::class)->finalizeCommission($order->fresh());
        }

        app(OrderNotificationService::class)->notifyStatusChange($order->fresh(), $newStatus);

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

    public function exportUsers(Request $request): StreamedResponse
    {
        $query = $this->buildDashboardUsersQuery($request);
        $users = $query->get();

        if ($request->input('format') === 'xlsx') {
            return app(AdminUsersExcelExporter::class)->download($users);
        }

        $filename = 'users_'.now()->format('Y-m-d_His').'.csv';
        $headers = ['ID', 'Имя', 'Фамилия', 'Email', 'Телефон', 'Роль', 'Зарегистрирован', 'Заблокирован', 'Удалён'];
        $rows = $users->map(fn (User $u) => [
            $u->id,
            $u->name ?? '',
            $u->last_name ?? '',
            $u->email ?? '',
            $u->phone ?? '',
            $u->role,
            $u->created_at?->format('Y-m-d H:i'),
            $u->is_blocked ? 'да' : 'нет',
            $u->deleted_at?->format('Y-m-d H:i') ?? '',
        ]);

        return $this->streamCsv($filename, $headers, $rows);
    }

    public function revenueChartData(Request $request)
    {
        $chartService = app(RevenueChartQueryService::class);
        [$from, $to, $rangeLabel] = $chartService->resolveRange($request);
        $period = $request->input('period');
        $payload = $chartService->getChartPayload($from, $to, $period);

        return response()->json([
            'data' => $payload['data'],
            'granularity' => $payload['granularity'],
            'period_label' => $payload['period_label'],
            'range_label' => $rangeLabel,
            'period' => $period,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);
    }

    public function exportRevenuePdf(Request $request)
    {
        [$from, $to] = $request->filled('period')
            ? array_slice(app(RevenueChartQueryService::class)->resolveRange($request), 0, 2)
            : $this->parseDateRange($request);
        $status = $request->input('status', 'paid');

        $q = Order::query();
        if ($from) {
            $q->where('created_at', '>=', $from);
        }
        if ($to) {
            $q->where('created_at', '<=', $to);
        }
        if ($status === 'paid') {
            $q->whereNotIn('status', [Order::STATUS_CANCELED, Order::STATUS_REFUSED]);
        } elseif ($status !== 'all') {
            $q->where('status', $status);
        }

        $orders = $q->orderBy('created_at', 'desc')->get();
        $total = (float) $orders->sum('total');
        $chartFrom = $from ?? Carbon::today()->subDays(29);
        $chartTo = $to ?? Carbon::today();

        $chartRequest = new Request($request->filled('period')
            ? ['period' => $request->input('period')]
            : ['from' => $chartFrom->format('Y-m-d'), 'to' => $chartTo->format('Y-m-d')]);
        $chartResponse = $this->revenueChartData($chartRequest);
        $chartData = $chartResponse->getData(true)['data'] ?? [];

        $pdf = Pdf::loadView('reports.revenue', [
            'from' => $chartFrom->format('d.m.Y'),
            'to' => $chartTo->format('d.m.Y'),
            'total' => $total,
            'ordersCount' => $orders->count(),
            'chartData' => $chartData,
        ]);

        $filename = 'revenue_'.($from?->format('Y-m-d') ?? 'all').'_'.($to?->format('Y-m-d') ?? 'all').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    private function userDetailSellerSalesPayload(Request $request, int $sellerId): array
    {
        $perPage = 25;
        $page = max(1, (int) $request->input('seller_sales_page', 1));
        $sort = $request->input('seller_sales_sort', 'date_desc');
        $status = $request->input('seller_sales_status', 'all');

        $query = OrderItem::with(['order', 'variant.product.images'])
            ->where('seller_id', $sellerId);

        if ($status !== 'all' && $status !== '') {
            $query->whereHas('order', fn ($q) => $q->where('status', $status));
        }

        if ($sort === 'date_asc') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $total = (clone $query)->count();
        $limit = $page * $perPage;
        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        $items = $rows->map(fn ($item) => [
            'id' => $item->id,
            'quantity' => $item->quantity,
            'price_at_purchase' => $item->price_at_purchase,
            'product_name' => $item->variant?->product?->title ?? '—',
            'product_id' => $item->variant?->product?->id,
            'product_image' => $item->variant?->product?->images?->firstWhere('is_main', true)?->url ?? null,
            'order_number' => $item->order?->number ?? '—',
            'order_status' => $item->order?->status ?? '—',
            'created_at' => $item->created_at,
        ])->values()->all();

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $hasMore,
                'last_page' => (int) ceil(max(1, $total) / $perPage),
                'sort' => $sort,
                'status' => $status,
            ],
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    private function userDetailSellerProductsPayload(Request $request, int $sellerId): array
    {
        $perPage = 25;
        $page = max(1, (int) $request->input('seller_products_page', 1));
        $sort = $request->input('seller_products_sort', 'date_desc');
        $status = $request->input('seller_products_status', 'all');
        $search = trim((string) $request->input('seller_products_search', ''));
        if (mb_strlen($search) > 200) {
            $search = mb_substr($search, 0, 200);
        }

        $query = Product::where('seller_id', $sellerId)
            ->with(['seller.sellerProfile'])
            ->withCount('variants');

        if ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($search, $like) {
                $q->where('title', 'like', $like);
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        if ($sort === 'name_asc') {
            $query->orderBy('title', 'asc');
        } elseif ($sort === 'name_desc') {
            $query->orderBy('title', 'desc');
        } elseif ($sort === 'date_asc') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $total = (clone $query)->count();
        $limit = $page * $perPage;
        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        return [
            'items' => $rows->map(fn ($p) => $this->mapProductForAdmin($p))->values()->all(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $hasMore,
                'last_page' => (int) ceil(max(1, $total) / $perPage),
                'sort' => $sort,
                'status' => $status,
                'search' => $search,
            ],
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    private function dashboardUsersPayload(Request $request, UserRestrictionService $restriction): array
    {
        $perPage = 50;
        $page = max(1, (int) $request->input('users_page', 1));
        $limit = $page * $perPage;

        $query = $this->buildDashboardUsersQuery($request);
        $total = (clone $query)->count();
        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        $items = $rows->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'last_name' => $u->last_name,
            'email' => $u->email,
            'phone' => $u->phone,
            'role' => $u->role,
            'is_blocked' => $u->is_blocked,
            'avatar' => $u->avatar,
            'deleted_at' => $u->deleted_at,
            'created_at' => $u->created_at,
            'shop_name' => $u->sellerProfile?->shop_name,
            'shop_changes_pending' => (bool) $u->sellerProfile?->isShopChangesPending(),
            'pvz_application_pending' => $u->pickupPointStaff
                ->contains(fn ($s) => $s->status === PickupPointStaff::STATUS_PENDING),
            'assignable_roles' => $restriction->assignableRolesFor(auth()->user(), $u),
        ])->values()->all();

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'total' => $total,
                'filter' => $request->input('users_filter', 'all'),
                'search' => trim((string) $request->input('users_search', '')),
                'sort' => $request->input('users_sort', 'created_at'),
                'dir' => $request->input('users_dir', 'desc') === 'asc' ? 'asc' : 'desc',
            ],
        ];
    }

    private function buildDashboardUsersQuery(Request $request)
    {
        $filter = $request->input('users_filter', 'all');
        $search = trim((string) $request->input('users_search', ''));
        $sort = $request->input('users_sort', 'created_at');
        $dir = $request->input('users_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = User::withTrashed()
            ->with(['sellerProfile', 'approvedPickupPointStaff', 'pickupPointStaff']);

        if ($filter === 'active') {
            $query->where(fn ($q) => $q->where('is_blocked', false)->orWhereNull('is_blocked'))
                ->whereNull('deleted_at');
        } elseif ($filter === 'blocked') {
            $query->where('is_blocked', true)->whereNull('deleted_at');
        } elseif ($filter === 'deleted') {
            $query->whereNotNull('deleted_at');
        } elseif ($filter === 'shop_changes') {
            $query->whereHas('sellerProfile', fn ($p) => $p->shopChangesPending());
        } elseif ($filter === 'pvz_pending') {
            $query->whereHas('pickupPointStaff', fn ($s) => $s->where('status', PickupPointStaff::STATUS_PENDING));
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        if ($sort === 'name') {
            $query->orderBy('name', $dir)->orderBy('last_name', $dir);
        } elseif ($sort === 'role') {
            $query->orderByRaw(
                "CASE role WHEN 'admin' THEN 1 WHEN 'moderator' THEN 2 WHEN 'seller' THEN 3 WHEN 'pvz' THEN 4 ELSE 5 END ".($dir === 'asc' ? 'ASC' : 'DESC')
            )->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('created_at', $dir);
        }

        return $query;
    }

    /**
     * @return array{items: array<int, object>, meta: array<string, mixed>}
     */
    private function dashboardSessionsPayload(Request $request, int $onlineThreshold): array
    {
        $perPage = 50;
        $page = max(1, (int) $request->input('sessions_page', 1));
        $limit = $page * $perPage;

        $rows = DB::table('sessions')
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
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        $items = $rows->map(function ($s) use ($onlineThreshold) {
            $s->is_online = $s->last_activity >= $onlineThreshold;
            $s->last_activity = Carbon::createFromTimestamp($s->last_activity)->toIso8601String();

            return $s;
        })->values()->all();

        return [
            'items' => $items,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'has_more' => $hasMore],
        ];
    }

    /**
     * @return array{items: array<int, object>, meta: array<string, mixed>}
     */
    private function dashboardLoginHistoryPayload(Request $request): array
    {
        $perPage = 50;
        $page = max(1, (int) $request->input('login_page', 1));
        $limit = $page * $perPage;

        $rows = DB::table('account_login_events')
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
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        $items = $rows->map(function ($event) {
            $event->created_at = Carbon::parse($event->created_at)->toIso8601String();

            return $event;
        })->values()->all();

        return [
            'items' => $items,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'has_more' => $hasMore],
        ];
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
        if ($request->input('format') === 'xlsx') {
            return app(AdminRevenueExcelExporter::class)->download($request);
        }

        [$from, $to] = $request->filled('period')
            ? array_slice(app(RevenueChartQueryService::class)->resolveRange($request), 0, 2)
            : $this->parseDateRange($request);
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

        if ($request->input('format') === 'xlsx') {
            $buyerOrders = Order::with('items.variant.product')
                ->where('buyer_id', $user->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->orderBy('created_at', 'desc')
                ->get();
            $sellerItems = OrderItem::with(['order', 'variant.product'])
                ->where('seller_id', $user->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->orderBy('created_at', 'desc')
                ->get();
            $rangeLabel = ($from ? $from->format('Y-m-d') : 'all').'_'.($to ? $to->format('Y-m-d') : 'all');

            return app(AdminUserReportExcelExporter::class)->download($user, $buyerOrders, $sellerItems, $rangeLabel);
        }

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

        $query = Product::with(['seller.sellerProfile', 'category', 'images', 'variants:id,product_id,sku'])
            ->withSum('variants as variants_views_sum', 'views_count')
            ->orderBy('created_at', 'desc');

        if ($status === 'off_catalog') {
            $query->where('status', 'approved')->where('is_on_action', false);
        } elseif ($status === 'on_catalog') {
            $query->where('status', 'approved')->where('is_on_action', true);
        } elseif ($status !== 'all') {
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
        $productItems = $products->getCollection()->map(fn ($p) => $this->mapProductForAdmin($p, includeSeller: true));

        $counts = [
            'all'        => Product::count(),
            'moderation' => Product::where('status', 'moderation')->count(),
            'approved'   => Product::where('status', 'approved')->count(),
            'off_catalog' => Product::where('status', 'approved')->where('is_on_action', false)->count(),
            'on_catalog' => Product::where('status', 'approved')->where('is_on_action', true)->count(),
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

    private function mapProductForAdmin(Product $p, bool $includeSeller = false): array
    {
        $row = [
            'id'                 => $p->id,
            'title'              => $p->title,
            'name'               => $p->title,
            'min_price'          => $p->min_price,
            'status'             => $p->status,
            'moderation_comment' => $p->moderation_comment,
            'is_on_action'       => (bool) $p->is_on_action,
            'catalog_visible'    => $p->isPubliclyVisible(),
            'seller_can_publish' => $p->sellerCanPublish(),
            'storefront_block_reason' => $p->storefrontBlockReason(),
            'sales_count'        => $p->sales_count ?? null,
            'views_count'        => (int) ($p->variants_views_sum ?? 0),
            'image'              => $p->relationLoaded('images')
                ? ($p->images?->firstWhere('is_main', true)?->url)
                : $p->images()->where('is_main', true)->value('url'),
            'created_at'         => $p->created_at,
            'variants_count'     => $p->variants_count ?? null,
            'variant_skus'       => $p->relationLoaded('variants')
                ? ($p->variants?->pluck('sku')->filter()->values()->all() ?? [])
                : [],
        ];

        if ($includeSeller) {
            $row['category'] = $p->category ? ['id' => $p->category->id, 'name' => $p->category->name] : null;
            $row['seller'] = $p->seller ? [
                'id'    => $p->seller->id,
                'name'  => $p->seller->name,
                'email' => $p->seller->email,
            ] : null;
        }

        return $row;
    }

}
