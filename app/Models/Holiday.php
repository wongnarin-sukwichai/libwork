<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Holiday extends Model
{
    protected $fillable = ['date', 'name'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('holidays'));
        static::deleted(fn () => Cache::forget('holidays'));
    }

    /** ['2026-12-05' => 'วันพ่อแห่งชาติ', ...] (cache) */
    public static function map(): array
    {
        return Cache::rememberForever('holidays', fn () => static::query()->orderBy('date')->get()
            ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->name])->all());
    }
}
