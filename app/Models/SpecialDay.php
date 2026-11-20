<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialDay extends Model
{
    protected $fillable = ['date', 'schedule_id', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
