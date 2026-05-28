<?php

namespace App\Services\Excel;

use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminUsersExcelExporter
{
    /** @param  Collection<int, User>  $users */
    public function download(Collection $users): StreamedResponse
    {
        $filename = 'users_'.now()->format('Y-m-d_His').'.xlsx';

        return StyledExcelHelper::streamDownload($this->build($users), $filename);
    }

    /** @param  Collection<int, User>  $users */
    public function build(Collection $users): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Пользователи');

        $headers = ['ID', 'Имя', 'Фамилия', 'Email', 'Телефон', 'Роль', 'Зарегистрирован', 'Заблокирован', 'Удалён'];
        $colCount = count($headers);

        StyledExcelHelper::applyTitle($sheet, 'Список пользователей', StyledExcelHelper::colLetter($colCount));
        $sheet->setCellValue('A2', 'Всего: '.$users->count().' · '.now()->format('d.m.Y H:i'));
        $sheet->mergeCells('A2:'.StyledExcelHelper::colLetter($colCount).'2');

        $headerRow = 4;
        StyledExcelHelper::writeHeaderRow($sheet, $headers, $headerRow);

        $row = $headerRow + 1;
        foreach ($users as $u) {
            $sheet->fromArray([
                $u->id,
                $u->name ?? '',
                $u->last_name ?? '',
                $u->email ?? '',
                $u->phone ?? '',
                $u->role,
                $u->created_at?->format('d.m.Y H:i'),
                $u->is_blocked ? 'да' : 'нет',
                $u->deleted_at?->format('d.m.Y H:i') ?? '',
            ], null, "A{$row}");
            if ($u->is_blocked || $u->deleted_at) {
                $sheet->getStyle("A{$row}:".StyledExcelHelper::colLetter($colCount)."{$row}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF1F5');
            }
            $row++;
        }

        StyledExcelHelper::applyTableBorders($sheet, $headerRow, max($headerRow, $row - 1), $colCount);
        StyledExcelHelper::autoSizeColumns($sheet, $colCount);
        $sheet->freezePane('A5');

        return $spreadsheet;
    }
}
