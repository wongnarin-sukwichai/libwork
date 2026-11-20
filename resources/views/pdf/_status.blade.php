@php($cls = ['late' => 'b-late', 'bad' => 'b-bad', 'leave' => 'b-leave', 'duty' => 'b-duty', 'ot' => 'b-ot'][$tone] ?? null)
@if ($cls)<span class="b {{ $cls }}">{{ $status }}</span>@else{{ $status }}@endif
