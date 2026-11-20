<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeavePolicy extends Model
{
    protected $fillable = ['employment_type_id', 'leave_type_id', 'days_per_year', 'min_service_months', 'max_total_days', 'max_total_days_10y'];

    protected function casts(): array
    {
        return ['days_per_year' => 'float', 'max_total_days' => 'float', 'max_total_days_10y' => 'float'];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }
}
