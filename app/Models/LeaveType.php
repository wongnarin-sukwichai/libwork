<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = ['code', 'name', 'category', 'counts_quota', 'carry_over', 'allow_half_day', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['counts_quota' => 'boolean', 'carry_over' => 'boolean', 'allow_half_day' => 'boolean', 'is_active' => 'boolean'];
    }

    public function policies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
