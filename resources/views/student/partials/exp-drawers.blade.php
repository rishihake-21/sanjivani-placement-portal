{{-- Drawer templates for experience entries: one per live entry plus "new". --}}
@use('App\Support\StudentUi', 'U')
@if (empty($student['profile_locked']))
  @include('student.partials.drawer-exp', ['e' => null, 'mode' => 'add'])
@endif
@foreach ($experiences as $x)
  @if ($x['status'] !== 'SUPERSEDED')
    @include('student.partials.drawer-exp', ['e' => $x, 'mode' => U::expMode($x)])
  @endif
@endforeach
