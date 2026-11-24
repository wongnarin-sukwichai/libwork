<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * ค่าตั้งค่าระบบแบบ key/value (JSON) อ่านผ่าน Setting::get() ซึ่ง cache ไว้
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    /** ค่าเริ่มต้นเมื่อยังไม่เคยบันทึก */
    public const DEFAULTS = [
        'org_name' => 'สำนักวิทยบริการ มหาวิทยาลัยมหาสารคาม',
        'login.google' => true,
        'login.password' => false,
        'checkin.kiosk' => true,
        'checkin.mobile' => false,
        'kiosk.auth_url' => null,
        'report.head_title' => 'หัวหน้าสำนัก / ผู้อำนวยการ',
        'report.head_name' => null,
        'public_calendar.show_leave_type' => false,
        // เวลายืดหยุ่น (เฉพาะคนที่ติ๊ก flex_time): เริ่มนับ 08:30 ตามรูปแบบเวลาปกติ · เข้าไม่เกิน latest_start · ชั่วโมงสุทธิ (หักพัก) ไม่น้อยกว่า min_minutes
        'flex.latest_start' => '09:00',
        'flex.min_minutes' => 420,
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key')->all());

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? static::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }
}
