@extends('layouts.student')
@use('App\Support\StudentUi', 'U')
@section('title', 'Dashboard')

@section('content')
@php
  /* StudentPageController hands over plain arrays built from the module's own
     resources, which is exactly what StudentUi expects. */
  $records = array_values($records ?? []);
  $experiences = array_values($experiences ?? []);
  $notifications = array_values($notifications ?? []);
  $documents = array_values($documents ?? []);

  $sections = U::sections($completeness);
  $blockers = U::blockers($completeness);
  $done = U::sectionsDone($completeness);
  $rows = array_values(array_filter(U::academicRows($student, $completeness, $records), fn ($r) => $r['required'] || $r['shown']));
  $liveExps = array_values(array_filter($experiences, fn ($x) => $x['status'] !== 'SUPERSEDED'));
  $first = explode(' ', trim($student['full_name']))[0];
  $locked = ! empty($student['profile_locked']);
  $canApply = ! empty($completeness['can_apply']);
  $unreadCount = $unread;
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>Welcome, {{ $first }}</h1>
      <p class="meta">{{ $student['university_id'] }} &middot; {{ $student['branch']['name'] }} &middot; Semester {{ $student['current_semester'] }} &middot; {{ U::admission($student['admission_type']) }} &middot; Graduating {{ $student['graduation_year'] }}</p>
    </div>
  </div>

  <div class="stack">
    <section class="card" aria-labelledby="h-ready">
      <div class="card-h"><h2 id="h-ready">Application readiness</h2></div>
      <div class="ready">
        <p class="ready-state {{ $canApply ? 'ok' : 'no' }}">{{ $canApply ? 'You can apply to drives' : 'You cannot apply yet' }}</p>
        <div class="ready-bar">
          <div class="seg" role="img" aria-label="{{ $done }} of 7 sections complete">
            @foreach ($sections as $s)<i class="{{ $s['status'] === 'COMPLETE' ? 'on' : '' }}"></i>@endforeach
          </div>
          <p class="meta num">{{ $done }} of 7 sections complete</p>
        </div>
      </div>
      @foreach ($blockers as $b)
        <div class="blk">
          <div><b>{{ $b['name'] }}</b>: {{ $b['hint'] }}</div>
          <a class="btn btn-secondary" href="{{ route($b['route']) }}#{{ $b['anchor'] }}">Go to section</a>
        </div>
      @endforeach
      @if (empty($blockers) && $locked)
        <div class="blk"><div>{!! U::icon('lock', 'sm') !!} Your profile is locked (batch completed). Contact the T&amp;P office.</div></div>
      @endif
    </section>

    <section class="card" aria-labelledby="h-sec">
      <div class="card-h"><h2 id="h-sec">Profile sections</h2></div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Section</th><th>Status</th><th>What to do</th><th><span class="sr">Action</span></th></tr></thead>
          <tbody>
            @foreach ($sections as $s)
              <tr>
                <td data-l="Section" class="nw"><span class="ltr">{{ $s['letter'] }}</span>{{ $s['name'] }}</td>
                <td data-l="Status">{!! U::badge('sec', $s['status']) !!}</td>
                <td data-l="What to do">@if ($s['status'] === 'COMPLETE')<span class="meta">&ndash;</span>@else{{ $s['hint'] }}@endif</td>
                <td class="act"><a class="btn btn-secondary" href="{{ route($s['route']) }}#{{ $s['anchor'] }}">Open</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    <section class="card" aria-labelledby="h-acad">
      <div class="card-h"><h2 id="h-acad">Academic records</h2><div class="acts"><a class="linkbtn" href="{{ route('student.academic') }}">View all</a></div></div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Record</th><th>Status</th><th>Marks</th><th>Marksheet</th><th><span class="sr">Action</span></th></tr></thead>
          <tbody>
            @foreach ($rows as $row)
              <tr>
                <td data-l="Record"><b>{{ $row['label'] }}</b></td>
                <td data-l="Status">@include('student.partials.acad-status', ['row' => $row])</td>
                <td data-l="Marks">@if (U::marks($row['shown']) !== null)<span class="num">{{ U::marks($row['shown']) }}</span>@else<span class="meta">&ndash;</span>@endif</td>
                <td data-l="Marksheet">@if ($row['shown'])@include('student.partials.file-link', ['doc' => $row['shown']['document'] ?? null])@else<span class="meta">&ndash;</span>@endif</td>
                <td class="act">@include('student.partials.acad-actions', ['row' => $row, 'full' => false])</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    <div class="grid">
      <section class="card c6" aria-labelledby="h-not">
        <div class="card-h">
          <h2 id="h-not">Notifications</h2>
          <div class="acts"><span class="meta num">{{ $unreadCount }} unread</span><a class="linkbtn" href="{{ route('student.notifications') }}">View all</a></div>
        </div>
        @if (count($notifications))
          <div class="list">@foreach ($notifications as $n)@include('student.partials.note-row', ['n' => $n, 'withAction' => false])@endforeach</div>
        @else
          <p class="empty">No notifications yet.</p>
        @endif
      </section>

      <section class="card c6" aria-labelledby="h-doc">
        <div class="card-h"><h2 id="h-doc">Documents</h2><div class="acts"><a class="linkbtn" href="{{ route('student.documents') }}">View all</a></div></div>
        @if (count($documents))
          <div class="list">
            @foreach ($documents as $d)
              <div class="li">
                <div class="l">
                  <div class="t">{{ U::docType($d['document_type']) }}@if ($d['is_primary']) <span class="tag">Primary</span>@endif</div>
                  <div class="meta">{{ $d['original_name'] }}</div>
                </div>
                <div class="r">{!! U::badge('doc', $d['status']) !!}<span class="meta num">Version {{ $d['version'] }}</span></div>
              </div>
            @endforeach
          </div>
        @else
          <p class="empty">No documents uploaded yet.</p>
        @endif
      </section>
    </div>

    <section class="card" aria-labelledby="h-exp">
      <div class="card-h"><h2 id="h-exp">Experience</h2><div class="acts"><a class="linkbtn" href="{{ route('student.experience') }}">View all</a></div></div>
      @if (count($liveExps))
        <div class="list">
          @foreach ($liveExps as $x)
            <div class="li">
              <div class="l">
                <div class="t">{{ U::expType($x['type']) }} &middot; {{ $x['organization'] }} &middot; {{ $x['role_title'] }}</div>
                <div class="meta">{{ U::expDates($x) }}</div>
              </div>
              <div class="r">{!! U::badge('rec', $x['status']) !!}</div>
            </div>
          @endforeach
        </div>
      @else
        <p class="empty">Optional: add internships or work experience.</p>
      @endif
    </section>
  </div>
</div>
@include('student.partials.acad-drawers')
@endsection
