{{-- Action button of an academic row. Needs $row, $full (show "View"), $student. --}}
@use('App\Support\StudentUi', 'U')
@php
  $a = U::acadAction($row, $full, ! empty($student['profile_locked']));
@endphp
@if ($a['type'] === 'add')
  <button type="button" class="btn btn-secondary" data-open="acad-new" data-key="{{ $row['lv'] }}|{{ $row['sem'] }}" @disabled($a['disabled'])>{{ $a['label'] }}</button>
@elseif ($a['type'] === 'submit')
  <button type="button" class="btn btn-secondary" data-post="{{ route('student.x.academic.submit', $a['rec']['id']) }}" data-done="{{ $a['done'] }}" @disabled($a['disabled'])>{{ $a['label'] }}</button>
@elseif ($a['type'] === 'open')
  <button type="button" class="btn btn-secondary" data-open="acad-{{ $a['rec']['id'] }}"@if ($a['focus']) data-focus="file"@endif @disabled($a['disabled'])>{{ $a['label'] }}</button>
@endif
