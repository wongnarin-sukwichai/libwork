<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * เก็บรูปตอนลงเวลาแบบ private (storage/app/private) ย่อเหลือกว้างไม่เกิน 480px JPEG
 * เปิดดูผ่าน route ที่ตรวจสิทธิ์เท่านั้น
 */
class PhotoStore
{
    public const MAX_WIDTH = 480;

    /** รับ data URL หรือ base64 ของรูป JPEG/PNG คืน path ใน disk 'local' หรือ null ถ้าไม่ใช่รูป */
    public static function saveBase64(?string $data, string $date): ?string
    {
        if (! $data) {
            return null;
        }
        $raw = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $data), true);
        if ($raw === false || strlen($raw) > 2_000_000) {
            return null;
        }
        $image = @imagecreatefromstring($raw);
        if (! $image) {
            return null;
        }

        $w = imagesx($image);
        $h = imagesy($image);
        if ($w > self::MAX_WIDTH) {
            $nh = (int) round($h * self::MAX_WIDTH / $w);
            $resized = imagecreatetruecolor(self::MAX_WIDTH, $nh);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, self::MAX_WIDTH, $nh, $w, $h);
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, 72);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        $path = 'attendance/'.str_replace('-', '/', $date).'/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($path, $jpeg);

        return $path;
    }
}
