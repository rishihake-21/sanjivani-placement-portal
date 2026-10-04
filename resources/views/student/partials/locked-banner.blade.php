@if (! empty($student['profile_locked']))
  @include('student.partials.notice', ['kind' => 'no', 'ic' => 'lock', 'text' => 'Your profile is locked (batch completed). Contact the T&P office.'])
@endif
