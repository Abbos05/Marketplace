<?php

namespace App\Services\Excel;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerOrdersExcelExporter
{
    /** @param  list<list<mixed>>  $rows  first row = headers */
    public function download(array $rows): StreamedResponse
    {
        $filename = 'orders_'.now()->format('Y-m-d_H-i').'.xlsx';

        return StyledExcelHelper::streamDownload($this->build($rows), $filename);
    }

    /** @param  list<list<mixed>>  $rows */
    public function build(array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Заказы');

        if ($rows === []) {
            return $spreadsheet;
        }

        $headers = array_shift($rows);
        $colCount = count($headers);

        StyledExcelHelper::applyTitle($sheet, 'Заказы продавца', StyledExcelHelper::colLetter($colCount));
        $sheet->setCellValue('A2', 'Сформирован: '.now()->format('d.m.Y H:i'));
        $sheet->mergeCells('A2:'.StyledExcelHelper::colLetter($colCount).'2');

        $headerRow = 4;
        StyledExcelHelper::writeHeaderRow($sheet, $headers, $headerRow);

        $row = $headerRow + 1;
        foreach ($rows as $data) {
            $numeric = array_map(function ($val, $idx) {
                if (in_array($idx, [5, 6, 7, 8], true) && is_numeric(str_replace([' ', ','], '', (string) $val))) {
                    return (float) str_replace(',', '.', preg_replace('/[^\d.-]/', '', (string) $val));
                }

                return $val;
            }, $data, array_keys($data));

            $sheet->fromArray($numeric, null, "A{$row}");
            $row++;
        }

        $lastRow = max($headerRow, $row - 1);
        StyledExcelHelper::applyTableBorders($sheet, $headerRow, $lastRow, $colCount);
        foreach (['G', 'H', 'I', 'J'] as $col) {
            StyledExcelHelper::applyCurrencyColumn($sheet, $col, $headerRow + 1, $lastRow);
        }
        StyledExcelHelper::autoSizeColumns($sheet, $colCount);
        $sheet->freezePane('A5');

        return $spreadsheet;
    }
}
