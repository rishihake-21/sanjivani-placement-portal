{{-- Drawer templates for academic records: one per live record plus "new". Cloned into #drawer-root by public/js/student.js. --}}
@use('App\Support\StudentUi', 'U')
@php
  $avail = U::availableKeys($student, $records);
@endphp
@if (! empty($avail) && empty($student['profile_locked']))
  @include('student.partials.drawer-acad', ['rec' => null, 'mode' => 'add', 'avail' => $avail])
@endif
@foreach ($records as $r)
  @if ($r['status'] !== 'SUPERSEDED')
    @include('student.partials.drawer-acad', ['rec' => $r, 'mode' => U::acadMode($r), 'avail' => []])
  @endif
@endforeach
