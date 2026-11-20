<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    public const TYPES = ['normal', 'ot'];

    public const APPLIES = ['weekday', 'holiday', 'special'];

    public const ROUNDING = ['minute', 'half_hour', 'hour'];

    protected $fillable = [
        'name', 'type', 'applies_to', 'start_time', 'end_time', 'grace_minutes', 'count_from',
        'break_start', 'break_end', 'special_from', 'rounding', 'daily_cap_minutes', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'grace_minutes' => 'integer', 'daily_cap_minutes' => 'integer'];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** "08:30" สำหรับแสดงผล */
    public function hm(string $field): ?string
    {
        return $this->{$field} ? substr($this->{$field}, 0, 5) : null;
    }
}
