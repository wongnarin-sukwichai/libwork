<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** สร้างไฟล์ Excel อย่างง่าย: หัวเรื่อง + หัวตาราง + แถว (แถวกลุ่มเป็นตัวหนาพื้นฟ้า) */
class Xlsx
{
    /**
     * @param  array<int, string>  $titles  บรรทัดหัวเรื่องด้านบน
     * @param  array<int, string>  $headers
     * @param  array<int, array|string>  $rows  array = แถวข้อมูล, string = แถวชื่อกลุ่ม
     */
    public static function download(string $filename, array $titles, array $headers, array $rows): StreamedResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('รายงาน');
        $book->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(11);

        $r = 1;
        foreach ($titles as $i => $t) {
            $sheet->setCellValue("A{$r}", $t);
            $sheet->getStyle("A{$r}")->getFont()->setBold($i === 0)->setSize($i === 0 ? 15 : 12);
            $r++;
        }
        $r++;

        $last = chr(ord('A') + count($headers) - 1);
        $sheet->fromArray($headers, null, "A{$r}");
        $sheet->getStyle("A{$r}:{$last}{$r}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A6E']],
        ]);
        $headerRow = $r++;

        foreach ($rows as $row) {
            if (is_string($row)) {
                $sheet->setCellValue("A{$r}", $row);
                $sheet->mergeCells("A{$r}:{$last}{$r}");
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EEF9']],
                ]);
            } else {
                // เก็บเวลาเป็นข้อความ (Excel จะไม่แปลง 08:30 เป็นเศษส่วนของวัน)
                foreach (array_values($row) as $c => $value) {
                    $sheet->setCellValueExplicit(chr(ord('A') + $c).$r, (string) ($value ?? ''), is_int($value) || is_float($value)
                        ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                        : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
            $r++;
        }

        foreach (range('A', $last) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A'.($headerRow + 1));

        return response()->streamDownload(function () use ($book) {
            (new XlsxWriter($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
