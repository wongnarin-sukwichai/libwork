<?php

namespace App\Support;

use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * สร้าง PDF ด้วย mPDF · ตัวอักษรค้นหาได้ ไม่ใช่ภาพหน้าจอแบบระบบเดิม
 */
class Pdf
{
    public static function make(string $html, string $orientation = 'P'): Mpdf
    {
        $tmp = storage_path('app/mpdf');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        // ฟอนต์ Garuda (มากับ mPDF) วางวรรณยุกต์ไทยถูกต้อง · เคยลอง Sarabun แล้ว:
        // เปิด OTL จะมีกล่อง □ หลังจุด (mPDF แทรก U+200B ซึ่ง Sarabun ไม่มี) ปิด OTL วรรณยุกต์หาย
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => $orientation,
            'tempDir' => $tmp,
            'default_font' => 'garuda',
            'default_font_size' => 10,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 14,
            'margin_footer' => 6,
            'useDictionaryLBR' => false, // คำไทยในตารางสั้นอยู่แล้ว
            'shrink_tables_to_fit' => 0, // ไม่ย่อตัวอักษรของกลุ่มงานให้พอดีหน้า ให้ขึ้นหน้าใหม่แทน
        ]);
        $mpdf->SetTitle('รายงานการลงเวลาปฏิบัติงาน');
        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    public static function download(string $html, string $filename, string $orientation = 'P'): Response
    {
        $content = self::make($html, $orientation)->Output('', 'S');

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"; filename*=UTF-8\'\''.rawurlencode($filename),
        ]);
    }

    /** path จริงของรูปลงเวลา (private) สำหรับใส่ใน PDF */
    public static function photoPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        $full = \Illuminate\Support\Facades\Storage::disk('local')->path($path);

        return is_file($full) ? $full : null;
    }
}
