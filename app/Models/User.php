<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = ['admin', 'staff', 'member'];

    public const ROLE_LABELS = [
        'admin' => 'ผู้ดูแลระบบ',
        'staff' => 'เจ้าหน้าที่ (HR)',
        'member' => 'บุคลากร',
    ];

    protected $fillable = [
        'employee_code', 'prefix', 'first_name', 'last_name', 'email', 'google_id', 'avatar', 'password',
        'role', 'employment_type_id', 'department_id', 'position', 'start_date', 'end_date',
        'ot_eligible', 'mobile_checkin', 'flex_time', 'is_active', 'last_login_at', 'legacy_id',
    ];

    protected $hidden = ['password', 'remember_token', 'google_id'];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'start_date' => 'date',
            'end_date' => 'date',
            'ot_eligible' => 'boolean',
            'flex_time' => 'boolean',
            'mobile_checkin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim(($this->prefix ?? '').$this->first_name.' '.$this->last_name));
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function departmentAssignments(): HasMany
    {
        return $this->hasMany(DepartmentAssignment::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** staff และ admin ใช้เมนูฝั่งเจ้าหน้าที่ */
    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'staff'], true);
    }

    /** หน้าแรกหลัง login ตาม role */
    public function homePath(): string
    {
        return $this->isStaff() ? '/staff' : '/me';
    }

    /** กลุ่มงาน ณ วันที่กำหนด (ใช้จัดกลุ่มรายงานย้อนหลัง) */
    public function departmentIdOn(CarbonInterface|string $date): ?int
    {
        $date = is_string($date) ? $date : $date->toDateString();

        return $this->departmentAssignments()
            ->whereDate('effective_from', '<=', $date)
            ->value('department_id') ?? $this->department_id;
    }

    /**
     * ย้ายกลุ่มงานโดยมีวันที่มีผล และอัปเดตกลุ่มงานปัจจุบันถ้าวันที่มีผลมาถึงแล้ว
     */
    public function moveToDepartment(int $departmentId, string $effectiveFrom, ?int $byUserId = null, ?string $note = null): void
    {
        $this->departmentAssignments()->create([
            'department_id' => $departmentId,
            'effective_from' => $effectiveFrom,
            'created_by' => $byUserId,
            'note' => $note,
        ]);

        $current = $this->departmentIdOn(now());
        if ($current !== $this->department_id) {
            $this->forceFill(['department_id' => $current])->save();
        }
    }
}
