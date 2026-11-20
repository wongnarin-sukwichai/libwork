<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Punch extends Model
{
    public const ACTIONS = ['in', 'out', 'ot_in', 'ot_out'];

    protected $fillable = [
        'client_uuid', 'user_id', 'employee_code', 'kiosk_device_id', 'action', 'punched_at', 'received_at',
        'offline', 'photo', 'ip', 'result', 'message', 'attendance_id',
    ];

    protected function casts(): array
    {
        return ['punched_at' => 'datetime', 'received_at' => 'datetime', 'offline' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
