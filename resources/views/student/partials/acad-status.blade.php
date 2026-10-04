{{-- Status cell of an academic row: badge, revision badge, rejection reason. --}}
@use('App\Support\StudentUi', 'U')
<div class="stk">{!! U::badge('rec', $row['status']) !!}@if ($row['revision']){!! U::revBadge($row['revision']) !!}@endif @include('student.partials.rej-why', ['rej' => U::rowRejection($row)])</div>
