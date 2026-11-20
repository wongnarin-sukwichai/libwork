<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KioskDevice extends Model
{
    protected $fillable = ['name', 'setup_code', 'setup_expires_at', 'token_hash', 'registered_at', 'last_seen_at', 'last_ip', 'is_active', 'created_by'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'setup_expires_at' => 'datetime',
            'registered_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** สร้างรหัสลงทะเบียนใหม่ (ใช้ได้ 24 ชั่วโมง) — ใช้ทั้งตอนเพิ่มเครื่องและตอนลงทะเบียนใหม่ */
    public function issueSetupCode(): string
    {
        $code = strtoupper(Str::random(4).'-'.Str::random(4));
        $this->forceFill(['setup_code' => $code, 'setup_expires_at' => now()->addDay()])->save();

        return $code;
    }

    /** แลกรหัสลงทะเบียนเป็น token ถาวรของเครื่อง (รหัสเดิมใช้ซ้ำไม่ได้) */
    public function register(): string
    {
        $token = Str::random(48);
        $this->forceFill([
            'token_hash' => hash('sha256', $token),
            'setup_code' => null,
            'setup_expires_at' => null,
            'registered_at' => now(),
        ])->save();

        return $token;
    }

    public static function findByToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::query()->where('token_hash', hash('sha256', $token))->where('is_active', true)->first();
    }
}
