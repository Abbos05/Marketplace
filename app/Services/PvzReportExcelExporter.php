<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PvzReportExcelExporter
{
    public function download(array $reportData, string $period): StreamedResponse
    {
        $spreadsheet = $this->build($reportData, $period);
        $filename = "pvz-report-{$period}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function build(array $reportData, string $period): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Отчёт '.$period);

        $operator = $reportData['operator'];
        $pickup = $reportData['pickup_point'];
        $stats = $reportData['stats'];
        $percent = $reportData['fee_percent'];

        $sheet->setCellValue('A1', 'Отчёт ПВЗ за период '.$period);
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $rows = [
            ['ПВЗ', $pickup?->title ?? '—'],
            ['Адрес', $pickup?->address ?? '—'],
            ['Оператор', trim(($operator->name ?? '').' '.($operator->last_name ?? ''))],
            ['Формула вознаграждения', $reportData['fee_description']],
            ['Выдано заказов', $stats['issued_count']],
            ['Отказов', $reportData['refused_count']],
            ['К выплате, ₽', (float) $stats['earnings']],
        ];

        $r = 3;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$r}", $row[0]);
            $sheet->setCellValue("B{$r}", $row[1]);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;
        }

        $headerRow = $r + 1;
        $headers = ['Заказ', 'Дата', 'Сумма заказа, ₽', 'Процент', 'Вознаграждение, ₽'];
        foreach ($headers as $col => $label) {
            $cell = chr(65 + $col).$headerRow;
            $sheet->setCellValue($cell, $label);
        }

        $headerStyle = $sheet->getStyle("A{$headerRow}:E{$headerRow}");
        $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('2563EB');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $dataRow = $headerRow + 1;
        foreach ($reportData['accruals'] as $accrual) {
            $sheet->setCellValue("A{$dataRow}", $accrual->order?->number ?? $accrual->order_id);
            $sheet->setCellValue("B{$dataRow}", $accrual->created_at?->format('d.m.Y H:i') ?? '');
            $sheet->setCellValue("C{$dataRow}", (float) ($accrual->order_total ?? $accrual->order?->total ?? 0));
            $sheet->setCellValue("D{$dataRow}", $percent.'%');
            $sheet->setCellValue("E{$dataRow}", (float) $accrual->amount);
            $dataRow++;
        }

        $lastRow = max($dataRow - 1, $headerRow);
        $sheet->getStyle("A{$headerRow}:E{$lastRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->getStyle("C".($headerRow + 1).":C{$lastRow}")
            ->getNumberFormat()->setFormatCode('#,##0.00" ₽"');
        $sheet->getStyle("E".($headerRow + 1).":E{$lastRow}")
            ->getNumberFormat()->setFormatCode('#,##0.00" ₽"');
        $sheet->getStyle('B7')->getNumberFormat()->setFormatCode('#,##0.00" ₽"');

        return $spreadsheet;
    }
}
