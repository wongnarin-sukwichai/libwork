<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'user_id', 'work_date', 'type', 'schedule_id', 'department_id', 'check_in_at', 'check_out_at',
        'check_in_photo', 'check_out_photo', 'check_in_source', 'check_out_source', 'recorded_offline',
        'worked_minutes', 'special_minutes', 'late_minutes', 'early_leave_minutes', 'note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'recorded_offline' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** ค่าที่เก็บในประวัติการแก้ไข */
    public function auditSnapshot(): array
    {
        return [
            'check_in_at' => $this->check_in_at?->format('H:i'),
            'check_out_at' => $this->check_out_at?->format('H:i'),
            'note' => $this->note,
        ];
    }
}
