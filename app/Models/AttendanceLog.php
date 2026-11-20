<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $fillable = ['attendance_id', 'subject_user_id', 'work_date', 'actor_id', 'action', 'before', 'after', 'reason'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'work_date' => 'date'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
