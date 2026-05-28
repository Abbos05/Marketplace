<?php

namespace App\Services\Excel;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminUserReportExcelExporter
{
    public function download(User $user, Collection $buyerOrders, Collection $sellerItems, string $rangeLabel): StreamedResponse
    {
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $user->name ?? 'user');
        $filename = "report_user{$user->id}_{$name}_{$rangeLabel}.xlsx";

        return StyledExcelHelper::streamDownload(
            $this->build($user, $buyerOrders, $sellerItems, $rangeLabel),
            $filename,
        );
    }

    public function build(User $user, Collection $buyerOrders, Collection $sellerItems, string $rangeLabel): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Сводка');

        $sheet->setCellValue('A1', 'Отчёт по пользователю');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $meta = [
            ['ID', $user->id],
            ['Имя', trim(($user->name ?? '').' '.($user->last_name ?? ''))],
            ['Email', $user->email ?? ''],
            ['Телефон', $user->phone ?? ''],
            ['Роль', $user->role],
            ['Период', $rangeLabel],
        ];
        $r = 3;
        foreach ($meta as [$label, $value]) {
            $sheet->setCellValue("A{$r}", $label);
            $sheet->setCellValue("B{$r}", $value);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;
        }

        if ($buyerOrders->isNotEmpty()) {
            $buyerSheet = $spreadsheet->createSheet();
            $buyerSheet->setTitle('Покупки');
            $this->fillPurchasesSheet($buyerSheet, $buyerOrders);
        }

        if ($sellerItems->isNotEmpty()) {
            $salesSheet = $spreadsheet->createSheet();
            $salesSheet->setTitle('Продажи');
            $this->fillSalesSheet($salesSheet, $sellerItems);
        }

        return $spreadsheet;
    }

    /** @param  Collection<int, Order>  $orders */
    private function fillPurchasesSheet($sheet, Collection $orders): void
    {
        $headers = ['№ заказа', 'Дата', 'Позиций', 'Сумма', 'Статус'];
        $colCount = count($headers);
        StyledExcelHelper::applyTitle($sheet, 'Покупки', StyledExcelHelper::colLetter($colCount));
        $headerRow = 3;
        StyledExcelHelper::writeHeaderRow($sheet, $headers, $headerRow);
        $row = $headerRow + 1;
        foreach ($orders as $o) {
            $sheet->fromArray([
                $o->number,
                $o->created_at?->format('d.m.Y H:i'),
                $o->items->count(),
                (float) $o->total,
                $o->status,
            ], null, "A{$row}");
            $row++;
        }
        $sheet->fromArray(['ИТОГО', '', '', (float) $orders->sum('total'), ''], null, "A{$row}");
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF1F5');
        StyledExcelHelper::applyTableBorders($sheet, $headerRow, $row, $colCount);
        StyledExcelHelper::applyCurrencyColumn($sheet, 'D', $headerRow + 1, $row);
        StyledExcelHelper::autoSizeColumns($sheet, $colCount);
    }

    /** @param  Collection<int, OrderItem>  $items */
    private function fillSalesSheet($sheet, Collection $items): void
    {
        $headers = ['№ заказа', 'Дата', 'Товар', 'Кол-во', 'Цена', 'Сумма', 'Комиссия', 'К выплате', 'Статус'];
        $colCount = count($headers);
        StyledExcelHelper::applyTitle($sheet, 'Продажи', StyledExcelHelper::colLetter($colCount));
        $headerRow = 3;
        StyledExcelHelper::writeHeaderRow($sheet, $headers, $headerRow);
        $row = $headerRow + 1;
        $totalRevenue = 0.0;
        $totalCommission = 0.0;
        $totalPayout = 0.0;
        foreach ($items as $i) {
            $sum = (float) $i->price_at_purchase * (int) $i->quantity;
            $commission = (float) $i->commission_amount;
            $payout = (float) $i->seller_payout_amount > 0 ? (float) $i->seller_payout_amount : $sum - $commission;
            $totalRevenue += $sum;
            $totalCommission += $commission;
            $totalPayout += $payout;
            $sheet->fromArray([
                $i->order?->number ?? '—',
                $i->created_at?->format('d.m.Y H:i'),
                $i->variant?->product?->title ?? '—',
                $i->quantity,
                (float) $i->price_at_purchase,
                $sum,
                $commission,
                $payout,
                $i->order?->status ?? '—',
            ], null, "A{$row}");
            $row++;
        }
        $sheet->fromArray(['ИТОГО', '', '', '', '', $totalRevenue, $totalCommission, $totalPayout, ''], null, "A{$row}");
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF1F5');
        StyledExcelHelper::applyTableBorders($sheet, $headerRow, $row, $colCount);
        foreach (['E', 'F', 'G', 'H'] as $col) {
            StyledExcelHelper::applyCurrencyColumn($sheet, $col, $headerRow + 1, $row);
        }
        StyledExcelHelper::autoSizeColumns($sheet, $colCount);
    }
}
