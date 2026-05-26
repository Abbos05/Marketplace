<?php

namespace App\Http\Controllers\Pvz;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PvzAccrual;
use App\Models\PickupPoint;
use App\Services\OrderSearchService;
use App\Services\PvzClosureService;
use App\Services\PvzFeeCalculator;
use App\Services\PvzNotificationService;
use App\Services\PvzOrderService;
use App\Services\PvzRefusalCodeService;
use App\Services\PvzReportExcelExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PvzDashboardController extends Controller
{
    protected function pickupPointId(Request $request): int
    {
        return (int) $request->attributes->get('pvz_pickup_point_id');
    }

    protected function sharedProps(Request $request): array
    {
        $staff = $request->attributes->get('pvz_staff');
        $point = $staff->pickupPoint;
        $fee = PvzFeeCalculator::feeDescription();
        $closure = $point ? app(PvzClosureService::class)->canRequestClosure($point) : ['ok' => false, 'message' => ''];

        return [
            'pickupPoint' => $point ? [
                'id' => $point->id,
                'title' => $point->title,
                'address' => $point->address,
                'closure_status' => $point->closure_status ?? PickupPoint::CLOSURE_NONE,
                'closure_requested_at' => $point->closure_requested_at,
                'closure_admin_reject_reason' => $point->closure_admin_reject_reason,
                'closure_admin_rejected_at' => $point->closure_admin_rejected_at,
            ] : [],
            'feeDescription' => $fee,
            'canRequestClosure' => $closure['ok'],
            'closureBlockMessage' => $closure['message'],
        ];
    }

    public function settings(Request $request): Response
    {
        return Inertia::render('Pvz/Settings', $this->sharedProps($request));
    }

    public function requestClosure(Request $request): RedirectResponse
    {
        $staff = $request->attributes->get('pvz_staff');
        $point = $staff->pickupPoint;

        if (! $point) {
            return back()->with('error', 'Пункт выдачи не найден.');
        }

        $data = $request->validate([
            'closure_reason' => 'nullable|string|max:1000',
        ]);

        $check = app(PvzClosureService::class)->canRequestClosure($point);
        if (! $check['ok']) {
            return back()->with('error', $check['message']);
        }

        $point->update([
            'closure_status' => PickupPoint::CLOSURE_PENDING,
            'closure_requested_at' => now(),
            'closure_reason' => $data['closure_reason'] ?? null,
            'closure_admin_reject_reason' => null,
            'closure_admin_rejected_at' => null,
        ]);

        app(PvzNotificationService::class)->notifyClosureRequested($request->user(), $point->title);

        return back()->with('success', 'Запрос на закрытие отправлен администратору.');
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $pickupPointId = $this->pickupPointId($request);
        $pvzService = app(PvzOrderService::class);
        $currentPeriod = now()->format('Y-m');

        return Inertia::render('Pvz/Index', [
            ...$this->sharedProps($request),
            'monthStats' => $pvzService->monthlyStats($user, $pickupPointId, $currentPeriod),
            'incomingPreview' => $pvzService->incomingForPickup($pickupPointId, 5),
            'incomingCount' => Order::query()
                ->where('pickup_point_id', $pickupPointId)
                ->whereIn('status', [Order::STATUS_NEW, Order::STATUS_INTRANSIT])
                ->count(),
            'queuePreview' => array_slice($pvzService->queueForPickup($pickupPointId), 0, 5),
            'queueCount' => Order::query()
                ->where('pickup_point_id', $pickupPointId)
                ->where('status', Order::STATUS_DELIVERED)
                ->count(),
            'recentOperations' => $pvzService->recentOperations($user, $pickupPointId, 12),
        ]);
    }

    public function queue(Request $request): Response
    {
        $pickupPointId = $this->pickupPointId($request);

        return Inertia::render('Pvz/Queue', [
            ...$this->sharedProps($request),
            'orders' => app(PvzOrderService::class)->queueForPickup($pickupPointId),
        ]);
    }

    public function searchOrders(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_search' => ['required', 'string', 'max:80'],
            'pickup_filter' => ['nullable', 'string', 'in:mine,other,all'],
            'status_filter' => ['nullable', 'string', 'in:active,ready,done,all'],
        ]);

        $searchService = app(OrderSearchService::class);
        $orderSearch = $searchService->trimSearchInput($data['order_search']);

        if ($orderSearch === '') {
            return redirect()->route('pvz.orders');
        }

        $searchType = $searchService->classifySearch($orderSearch);
        $pickupFilter = $data['pickup_filter']
            ?? $searchService->defaultPickupFilterForSearchType($searchType);
        $statusFilter = $data['status_filter']
            ?? $searchService->defaultStatusFilterForSearchType($searchType);

        $this->persistPvzOrderSearch($request, $orderSearch, $searchType, $pickupFilter, $statusFilter);

        return redirect()->route('pvz.orders');
    }

    public function orders(Request $request): Response
    {
        $pickupPointId = $this->pickupPointId($request);

        if ($request->boolean('clear')) {
            $request->session()->forget(['pvz_orders_search', 'pvz_issue_auth']);

            return Inertia::render('Pvz/Orders', $this->ordersPageProps($request, $pickupPointId, '', 'mine', 'active'));
        }

        $request->validate([
            'pickup_filter' => ['nullable', 'string', 'in:mine,other,all'],
            'status_filter' => ['nullable', 'string', 'in:active,ready,done,all'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $searchService = app(OrderSearchService::class);
        $saved = $request->session()->get('pvz_orders_search', []);

        if ($request->filled('order_search')) {
            $orderSearch = $searchService->trimSearchInput((string) $request->input('order_search'));
            $searchType = $searchService->classifySearch($orderSearch);
            $pickupFilter = $request->input('pickup_filter')
                ?? $searchService->defaultPickupFilterForSearchType($searchType);
            $statusFilter = $request->input('status_filter')
                ?? $searchService->defaultStatusFilterForSearchType($searchType);
            $this->persistPvzOrderSearch($request, $orderSearch, $searchType, $pickupFilter, $statusFilter);
            $saved = $request->session()->get('pvz_orders_search', []);
        }

        $orderSearch = ! empty($saved['order_search'])
            ? $searchService->trimSearchInput($saved['order_search'])
            : '';

        $searchType = $orderSearch !== '' ? $searchService->classifySearch($orderSearch) : '';

        $pickupFilter = $request->input('pickup_filter', $saved['pickup_filter'] ?? 'mine');
        if (! in_array($pickupFilter, ['mine', 'other', 'all'], true)) {
            $pickupFilter = 'mine';
        }

        $statusFilter = $request->input('status_filter', $saved['status_filter'] ?? 'active');
        if (! in_array($statusFilter, ['active', 'ready', 'done', 'all'], true)) {
            $statusFilter = $searchService->defaultStatusFilterForSearchType($searchType);
        }

        if ($orderSearch !== '') {
            $request->session()->put('pvz_orders_search', [
                'order_search' => $orderSearch,
                'pickup_filter' => $pickupFilter,
                'status_filter' => $statusFilter,
            ]);
        }

        return Inertia::render('Pvz/Orders', $this->ordersPageProps(
            $request,
            $pickupPointId,
            $orderSearch,
            $pickupFilter,
            $searchType,
            $statusFilter,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function ordersPageProps(
        Request $request,
        int $pickupPointId,
        string $orderSearch,
        string $pickupFilter,
        string $searchType = '',
        string $statusFilter = 'active',
    ): array {
        $searchService = app(OrderSearchService::class);
        $searchType = $searchType !== '' || $orderSearch === ''
            ? $searchType
            : $searchService->classifySearch($orderSearch);

        $issueAuth = $request->session()->get('pvz_issue_auth', [
            'daily_code' => null,
            'order_code' => null,
            'at' => null,
        ]);

        $issueContext = [
            'search_type' => $searchType,
            'issue_auth' => $issueAuth,
        ];

        $foundOrders = $orderSearch !== ''
            ? $searchService->searchExact($orderSearch)
            : collect();

        $pickupCounts = [
            'all' => $foundOrders->count(),
            'mine' => $foundOrders->where('pickup_point_id', $pickupPointId)->count(),
            'other' => $foundOrders->where('pickup_point_id', '!=', $pickupPointId)->count(),
        ];

        $filteredOrders = match ($pickupFilter) {
            'mine' => $foundOrders->where('pickup_point_id', $pickupPointId),
            'other' => $foundOrders->filter(fn (Order $o) => (int) $o->pickup_point_id !== $pickupPointId),
            default => $foundOrders,
        };

        $page = (int) $request->input('page', 1);
        $presentation = $orderSearch !== ''
            ? $searchService->paginatePvzSearchResults(
                $filteredOrders->values(),
                $pickupPointId,
                $statusFilter,
                $page,
            )
            : [
                'orders' => collect(),
                'status_counts' => ['active' => 0, 'ready' => 0, 'done' => 0, 'all' => 0],
                'pagination' => ['current_page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1],
            ];

        $orderResults = $orderSearch !== ''
            ? $searchService->mapOrdersForPanel(
                $presentation['orders'],
                $pickupPointId,
                $issueContext,
            )
            : [];

        $statusCounts = $presentation['status_counts'];
        $statusCounts['filtered'] = $presentation['pagination']['total'];

        return [
            ...$this->sharedProps($request),
            'orderSearch' => $orderSearch,
            'searchType' => $searchType,
            'pickupFilter' => $pickupFilter,
            'statusFilter' => $statusFilter,
            'pickupCounts' => $pickupCounts,
            'statusCounts' => $statusCounts,
            'pagination' => $presentation['pagination'],
            'orderResults' => $orderResults,
                'recentSearches' => array_slice($request->session()->get('pvz_recent_searches', []), 0, 4),
        ];
    }

    protected function persistPvzOrderSearch(
        Request $request,
        string $orderSearch,
        string $searchType,
        string $pickupFilter,
        string $statusFilter,
    ): void {
        $searchService = app(OrderSearchService::class);

        if ($searchType === OrderSearchService::SEARCH_DAILY_CODE) {
            $request->session()->put('pvz_issue_auth', [
                'daily_code' => $searchService->formatDailyPickupCodeFromQuery($orderSearch),
                'order_code' => null,
                'at' => now()->timestamp,
            ]);
        } elseif ($searchType === OrderSearchService::SEARCH_ORDER_CODE) {
            $request->session()->put('pvz_issue_auth', [
                'daily_code' => null,
                'order_code' => $searchService->normalizeOrderCodeFromQuery($orderSearch),
                'at' => now()->timestamp,
            ]);
        } else {
            $request->session()->forget('pvz_issue_auth');
        }

        $request->session()->put('pvz_orders_search', [
            'order_search' => $orderSearch,
            'pickup_filter' => $pickupFilter,
            'status_filter' => $statusFilter,
        ]);

        $recent = $request->session()->get('pvz_recent_searches', []);
        $recent = array_values(array_filter(
            $recent,
            fn (array $row) => ($row['q'] ?? '') !== $orderSearch,
        ));
        array_unshift($recent, [
            'q' => $orderSearch,
            'type' => $searchType,
            'at' => now()->toIso8601String(),
        ]);
        $request->session()->put('pvz_recent_searches', array_slice($recent, 0, 4));
    }

    public function reports(Request $request): Response
    {
        $user = $request->user();
        $pickupPointId = $this->pickupPointId($request);
        $pvzService = app(PvzOrderService::class);

        $data = $request->validate([
            'period_from' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'period_to' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'sort' => ['nullable', Rule::in(['asc', 'desc'])],
            'only_activity' => ['nullable', 'boolean'],
        ]);

        $periodFrom = $data['period_from'] ?? null;
        $periodTo = $data['period_to'] ?? null;
        $sort = $data['sort'] ?? 'desc';
        $onlyActivity = $request->boolean('only_activity', true);

        if ($periodFrom && ! $periodTo) {
            $periodTo = $periodFrom;
        }
        if ($periodTo && ! $periodFrom) {
            $periodFrom = $periodTo;
        }

        return Inertia::render('Pvz/Reports', [
            ...$this->sharedProps($request),
            'periodSummaries' => $pvzService->periodSummaries(
                $user,
                $pickupPointId,
                12,
                $onlyActivity,
                $periodFrom,
                $periodTo,
                $sort,
            ),
            'availablePeriods' => $pvzService->availablePeriods($user, $pickupPointId),
            'filters' => [
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'sort' => $sort,
                'only_activity' => $onlyActivity,
            ],
        ]);
    }

    public function sendRefusalCode(Request $request, Order $order): RedirectResponse
    {
        try {
            app(PvzRefusalCodeService::class)->sendVerificationCode(
                $order,
                $request->user(),
                $this->pickupPointId($request),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Код отправлен покупателю в уведомления.');
    }

    public function updateOrderStatus(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'status' => ['required', Rule::in([Order::STATUS_ISSUED, Order::STATUS_REFUSED])],
            'refusal_code' => ['required_if:status,'.Order::STATUS_REFUSED, 'nullable', 'string', 'min:4', 'max:10'],
        ]);

        try {
            if ($request->status === Order::STATUS_REFUSED) {
                app(PvzRefusalCodeService::class)->assertValidCode(
                    $order,
                    $request->user(),
                    (string) $request->input('refusal_code', ''),
                );
            }

            app(PvzOrderService::class)->transition(
                $order,
                $request->status,
                $request->user(),
                $this->pickupPointId($request),
                $request->session()->get('pvz_issue_auth', []),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Статус заказа обновлён.');
    }

    public function exportReport(Request $request): StreamedResponse|\Illuminate\Http\Response
    {
        $user = $request->user();
        $pickupPointId = $this->pickupPointId($request);
        $staff = $request->attributes->get('pvz_staff');

        $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'period_from' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'period_to' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'all' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('all') && $request->format === 'xlsx') {
            return $this->exportAllExcel($request, $user, $pickupPointId, $staff);
        }

        $period = $request->period ?? now()->format('Y-m');
        if (! $period) {
            abort(422, 'Укажите период.');
        }
        [$year, $month] = explode('-', $period);
        $start = now()->setDate((int) $year, (int) $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $accruals = PvzAccrual::query()
            ->with('order')
            ->where('user_id', $user->id)
            ->where('pickup_point_id', $pickupPointId)
            ->where('period', $period)
            ->orderBy('created_at')
            ->get();

        $stats = app(PvzOrderService::class)->monthlyStats($user, $pickupPointId, $period);
        $refusedCount = $stats['refused_count'];
        $fee = PvzFeeCalculator::feeDescription();

        $reportData = [
            'period' => $period,
            'operator' => $user,
            'pickup_point' => $staff->pickupPoint,
            'stats' => $stats,
            'refused_count' => $refusedCount,
            'accruals' => $accruals,
            'fee_description' => $fee['label'],
            'fee_percent' => $fee['percent'],
            'fee_max' => $fee['max'],
        ];

        if ($request->format === 'csv') {
            return $this->exportCsv($reportData, $period);
        }

        if ($request->format === 'xlsx') {
            return app(PvzReportExcelExporter::class)->download($reportData, $period);
        }

        $pdf = Pdf::loadView('pdf.pvz-report', $reportData);

        return $pdf->download("pvz-report-{$period}.pdf");
    }

    protected function exportAllExcel(
        Request $request,
        $user,
        int $pickupPointId,
        $staff,
    ): StreamedResponse {
        $periodFrom = $request->input('period_from');
        $periodTo = $request->input('period_to');

        $summaries = app(PvzOrderService::class)->periodSummaries(
            $user,
            $pickupPointId,
            24,
            false,
            $periodFrom,
            $periodTo,
            'desc',
        );

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($summaries as $summary) {
            $period = $summary['period'];
            [$year, $month] = explode('-', $period);
            $start = now()->setDate((int) $year, (int) $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $accruals = PvzAccrual::query()
                ->with('order')
                ->where('user_id', $user->id)
                ->where('pickup_point_id', $pickupPointId)
                ->where('period', $period)
                ->orderBy('created_at')
                ->get();

            $fee = PvzFeeCalculator::feeDescription();
            $reportData = [
                'period' => $period,
                'operator' => $user,
                'pickup_point' => $staff->pickupPoint,
                'stats' => $summary,
                'refused_count' => $summary['refused_count'],
                'accruals' => $accruals,
                'fee_description' => $fee['label'],
                'fee_percent' => $fee['percent'],
                'fee_max' => $fee['max'],
            ];

            $sheetSpreadsheet = app(PvzReportExcelExporter::class)->build($reportData, $period);
            $sheet = $sheetSpreadsheet->getActiveSheet();
            $sheet->setTitle(substr($period, 0, 7));
            $spreadsheet->addExternalSheet($sheet);
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $spreadsheet->createSheet()->setTitle('Пусто');
        }

        $filename = 'pvz-reports-all.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function exportCsv(array $reportData, string $period): StreamedResponse
    {
        $filename = "pvz-report-{$period}.csv";
        $percent = $reportData['fee_percent'];

        return response()->streamDownload(function () use ($reportData, $percent) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Период', $reportData['period']], ';');
            fputcsv($out, ['ПВЗ', $reportData['pickup_point']?->title ?? ''], ';');
            fputcsv($out, ['Формула вознаграждения', $reportData['fee_description']], ';');
            fputcsv($out, ['Выдано заказов', $reportData['stats']['issued_count']], ';');
            fputcsv($out, ['Отказов', $reportData['refused_count']], ';');
            fputcsv($out, ['Сумма к выплате', $reportData['stats']['earnings']], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['Заказ', 'Дата', 'Сумма заказа', 'Процент', 'Вознаграждение'], ';');
            foreach ($reportData['accruals'] as $row) {
                fputcsv($out, [
                    $row->order?->number ?? $row->order_id,
                    $row->created_at?->format('d.m.Y H:i'),
                    $row->order_total ?? $row->order?->total,
                    $percent.'%',
                    $row->amount,
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
