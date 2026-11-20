<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveBalance extends Model
{
    protected $fillable = ['user_id', 'leave_type_id', 'fiscal_year', 'entitled', 'carried_over', 'system_carried_over', 'cap', 'reviewed', 'status', 'confirmed_at', 'confirmed_by'];

    protected function casts(): array
    {
        return [
            'entitled' => 'float',
            'carried_over' => 'float',
            'system_carried_over' => 'float',
            'cap' => 'float',
            'reviewed' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(LeaveAdjustment::class)->latest();
    }
}
