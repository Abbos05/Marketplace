<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StyledExcelHelper
{
    public const HEADER_BG = 'FF2E63';

    public const HEADER_FG = 'FFFFFF';

    public const ACCENT_BG = 'FFF1F5';

    public static function streamDownload(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setIncludeCharts(true);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public static function applyTitle(Worksheet $sheet, string $title, string $lastCol, int $row = 1): void
    {
        $sheet->setCellValue("A{$row}", $title);
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    /** @param list<string> $headers */
    public static function writeHeaderRow(Worksheet $sheet, array $headers, int $row): void
    {
        $lastCol = self::colLetter(count($headers));
        foreach ($headers as $i => $label) {
            $col = self::colLetter($i + 1);
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $range = "A{$row}:{$lastCol}{$row}";
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB(ltrim(self::HEADER_FG, '#'));
        $style->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB(ltrim(self::HEADER_BG, '#'));
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    public static function applyTableBorders(Worksheet $sheet, int $headerRow, int $lastRow, int $colCount): void
    {
        if ($lastRow < $headerRow) {
            return;
        }
        $lastCol = self::colLetter($colCount);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    public static function applyCurrencyColumn(Worksheet $sheet, string $col, int $fromRow, int $toRow): void
    {
        if ($toRow < $fromRow) {
            return;
        }
        $sheet->getStyle("{$col}{$fromRow}:{$col}{$toRow}")
            ->getNumberFormat()->setFormatCode('#,##0.00" ₽"');
    }

    public static function autoSizeColumns(Worksheet $sheet, int $colCount): void
    {
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getColumnDimension(self::colLetter($i))->setAutoSize(true);
        }
    }

    public static function colLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
