<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveInquiry extends Model
{
    protected $fillable = ['user_id', 'fiscal_year', 'message', 'reply', 'replied_by', 'replied_at'];

    protected function casts(): array
    {
        return ['replied_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
