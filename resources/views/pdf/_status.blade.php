@php($cls = ['late' => 'b-late', 'bad' => 'b-bad', 'absent' => 'b-absent', 'leave' => 'b-leave', 'duty' => 'b-duty', 'ot' => 'b-ot', 'special' => 'b-special'][$tone] ?? null)
@if ($cls)<span class="b {{ $cls }}">{{ $status }}</span>@else{{ $status }}@endif
