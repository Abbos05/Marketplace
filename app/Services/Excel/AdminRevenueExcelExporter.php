<?php

namespace App\Services\Excel;

use App\Models\Order;
use App\Services\RevenueChartQueryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminRevenueExcelExporter
{
    public function __construct(
        private readonly RevenueChartQueryService $chartQuery,
    ) {}

    public function download(Request $request): StreamedResponse
    {
        [$from, $to, $rangeLabel] = $this->chartQuery->resolveRange($request);
        $orders = $this->queryOrders($request, $from, $to);
        $chartPayload = $this->chartQuery->getChartPayload($from, $to, $request->input('period'));

        $spreadsheet = $this->build($orders, $chartPayload['data'], $rangeLabel, $chartPayload['period_label']);
        $filename = 'revenue_'.($from->format('Y-m-d')).'_'.$to->format('Y-m-d').'.xlsx';

        return StyledExcelHelper::streamDownload($spreadsheet, $filename);
    }

    private function queryOrders(Request $request, ?Carbon $from, ?Carbon $to)
    {
        $minTotal = $request->input('min_total');
        $maxTotal = $request->input('max_total');
        $status = $request->input('status', 'paid');

        $q = Order::with(['buyer' => fn ($q) => $q->withTrashed(), 'items']);
        if ($from) {
            $q->where('created_at', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $q->where('created_at', '<=', $to->copy()->endOfDay());
        }
        if ($minTotal !== null && $minTotal !== '') {
            $q->where('total', '>=', (float) $minTotal);
        }
        if ($maxTotal !== null && $maxTotal !== '') {
            $q->where('total', '<=', (float) $maxTotal);
        }
        if ($status === 'paid') {
            $q->whereNotIn('status', [Order::STATUS_CANCELED, Order::STATUS_REFUSED]);
        } elseif ($status !== 'all') {
            $q->where('status', $status);
        }

        return $q->orderBy('created_at', 'desc')->get();
    }

    /**
     * @param  list<array{date: string, label: string, revenue: float, count: int}>  $chartData
     */
    public function build($orders, array $chartData, string $rangeLabel, string $granularityLabel): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $ordersSheet = $spreadsheet->getActiveSheet();
        $ordersSheet->setTitle('Заказы');

        $headers = ['№ заказа', 'Код', 'Дата', 'Покупатель', 'Email', 'Телефон', 'Позиций', 'Сумма', 'Скидка', 'Комиссия', 'К выплате', 'Статус', 'Оплата'];
        $colCount = count($headers);

        StyledExcelHelper::applyTitle($ordersSheet, 'Отчёт по выручке · '.$rangeLabel, StyledExcelHelper::colLetter($colCount), 1);
        $ordersSheet->setCellValue('A2', 'Сформирован: '.now()->format('d.m.Y H:i'));
        $ordersSheet->mergeCells('A2:'.StyledExcelHelper::colLetter($colCount).'2');

        $headerRow = 4;
        StyledExcelHelper::writeHeaderRow($ordersSheet, $headers, $headerRow);

        $row = $headerRow + 1;
        foreach ($orders as $o) {
            $payout = $o->items->sum(fn ($i) => $i->seller_payout_amount > 0
                ? $i->seller_payout_amount
                : ($i->price_at_purchase * $i->quantity) - $i->commission_amount);
            $ordersSheet->fromArray([
                $o->number,
                $o->order_code,
                $o->created_at?->format('d.m.Y H:i'),
                trim(($o->buyer->name ?? '').' '.($o->buyer->last_name ?? '')) ?: '—',
                $o->buyer->email ?? '',
                $o->buyer->phone ?? '',
                $o->items->count(),
                (float) $o->total,
                (float) ($o->discount ?? 0),
                (float) $o->items->sum('commission_amount'),
                (float) $payout,
                $o->status,
                $o->payment_status,
            ], null, "A{$row}");
            $row++;
        }

        $sum = (float) $orders->sum('total');
        $commissionSum = (float) $orders->sum(fn ($o) => $o->items->sum('commission_amount'));
        $payoutSum = (float) $orders->sum(fn ($o) => $o->items->sum(fn ($i) => $i->seller_payout_amount > 0
            ? $i->seller_payout_amount
            : ($i->price_at_purchase * $i->quantity) - $i->commission_amount));

        $ordersSheet->fromArray(['', '', '', '', '', '', 'ИТОГО:', $sum, '', $commissionSum, $payoutSum, '', ''], null, "A{$row}");
        $ordersSheet->getStyle("A{$row}:".StyledExcelHelper::colLetter($colCount)."{$row}")->getFont()->setBold(true);
        $ordersSheet->getStyle("A{$row}:".StyledExcelHelper::colLetter($colCount)."{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF1F5');

        $lastRow = $row;
        StyledExcelHelper::applyTableBorders($ordersSheet, $headerRow, $lastRow, $colCount);
        StyledExcelHelper::applyCurrencyColumn($ordersSheet, 'H', $headerRow + 1, $lastRow);
        StyledExcelHelper::applyCurrencyColumn($ordersSheet, 'I', $headerRow + 1, $lastRow - 1);
        StyledExcelHelper::applyCurrencyColumn($ordersSheet, 'J', $headerRow + 1, $lastRow);
        StyledExcelHelper::applyCurrencyColumn($ordersSheet, 'K', $headerRow + 1, $lastRow);
        StyledExcelHelper::autoSizeColumns($ordersSheet, $colCount);
        $ordersSheet->freezePane('A5');

        $chartSheet = $spreadsheet->createSheet();
        $chartSheet->setTitle('График');
        $this->buildChartSheet($chartSheet, $chartData, $rangeLabel, $granularityLabel);

        return $spreadsheet;
    }

    /**
     * @param  list<array{date: string, label: string, revenue: float, count: int}>  $chartData
     */
    private function buildChartSheet($sheet, array $chartData, string $rangeLabel, string $granularityLabel): void
    {
        StyledExcelHelper::applyTitle($sheet, 'График выручки · '.$rangeLabel.' ('.$granularityLabel.')', 'D', 1);
        StyledExcelHelper::writeHeaderRow($sheet, ['Период', 'Выручка, ₽', 'Заказов'], 3);

        $row = 4;
        foreach ($chartData as $point) {
            $sheet->setCellValue("A{$row}", $point['label'] ?? $point['date']);
            $sheet->setCellValue("B{$row}", (float) $point['revenue']);
            $sheet->setCellValue("C{$row}", (int) $point['count']);
            $row++;
        }
        $lastDataRow = max(4, $row - 1);
        StyledExcelHelper::applyTableBorders($sheet, 3, $lastDataRow, 3);
        StyledExcelHelper::applyCurrencyColumn($sheet, 'B', 4, $lastDataRow);
        StyledExcelHelper::autoSizeColumns($sheet, 3);

        if ($lastDataRow < 4) {
            return;
        }

        $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'График'!\$B\$3", null, 1)];
        $categories = [new DataSeriesValues(
            DataSeriesValues::DATASERIES_TYPE_STRING,
            "'График'!\$A\$4:\$A\${$lastDataRow}",
            null,
            $lastDataRow - 3,
        )];
        $values = [new DataSeriesValues(
            DataSeriesValues::DATASERIES_TYPE_NUMBER,
            "'График'!\$B\$4:\$B\${$lastDataRow}",
            null,
            $lastDataRow - 3,
        )];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($values) - 1),
            $labels,
            $categories,
            $values,
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $chart = new Chart(
            'revenue_chart',
            new Title('Выручка'),
            $legend,
            $plotArea,
        );
        $chart->setTopLeftPosition('E3');
        $chart->setBottomRightPosition('P22');
        $sheet->addChart($chart);
    }
}
