<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRecord extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'leave_type_id', 'start_date', 'end_date', 'half_day', 'days', 'fiscal_year', 'reason', 'attachment', 'created_by', 'updated_by', 'deleted_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'days' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** ป้ายครึ่งวัน */
    public function halfLabel(): ?string
    {
        return ['am' => 'ครึ่งเช้า', 'pm' => 'ครึ่งบ่าย'][$this->half_day] ?? null;
    }
}
