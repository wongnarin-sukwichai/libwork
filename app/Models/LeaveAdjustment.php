<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveAdjustment extends Model
{
    protected $fillable = ['leave_balance_id', 'amount', 'field', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class, 'leave_balance_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
