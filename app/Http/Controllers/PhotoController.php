<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** รูปตอนลงเวลา: เปิดได้เฉพาะ staff/admin และเจ้าของรูป */
class PhotoController extends Controller
{
    public function show(Request $request, Attendance $attendance, string $kind): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isStaff() || $attendance->user_id === $user->id, 403);

        $path = $kind === 'out' ? $attendance->check_out_photo : $attendance->check_in_photo;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'Content-Type' => 'image/jpeg',
        ]);
    }
}
