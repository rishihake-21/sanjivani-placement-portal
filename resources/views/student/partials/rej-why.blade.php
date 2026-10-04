{{-- Rejection reason under a status badge. $rej = ['code','label','text'] or null. --}}
@if ($rej)<span class="why">{{ $rej['label'] }}@if (! empty($rej['text']))<br>{{ $rej['text'] }}@endif</span>@endif
