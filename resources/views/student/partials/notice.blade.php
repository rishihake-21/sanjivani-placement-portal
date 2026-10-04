{{-- $kind: neutral|wait|no|ok, $ic: icon name, $text: plain text --}}
@use('App\Support\StudentUi', 'U')
<div class="notice {{ $kind }}">{!! U::icon($ic, 'sm') !!}<div>{{ $text }}</div></div>
