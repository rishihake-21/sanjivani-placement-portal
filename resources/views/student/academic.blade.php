@extends('layouts.student')
@use('App\Support\StudentUi', 'U')
@section('title', 'Academic records')

@section('content')
@php
  $records = array_values($records ?? []);
  $locked = ! empty($student['profile_locked']);

  $rows = U::academicRows($student, $completeness, $records);
  $avail = U::availableKeys($student, $records);
  $required = implode(', ', array_map(fn ($r) => $r['label'], array_filter($rows, fn ($r) => $r['required'])));

  $pct = fn ($v) => $v === null ? '–' : U::num2($v) . '%';
  $secondLabel = $student['admission_type'] === 'LATERAL' ? 'Diploma' : '12th';
  $secondValue = $student['admission_type'] === 'LATERAL' ? $verified['diploma_percentage'] : $verified['twelfth_percentage'];
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>Academic records</h1>
      <p class="meta">Required for {{ U::admission($student['admission_type']) }} admission: {{ $required }}</p>
    </div>
    <div class="row wrap">
      <button type="button" class="btn btn-primary" data-open="acad-new" @disabled($locked || empty($avail))>{!! U::icon('plus', 'sm') !!} Add record</button>
    </div>
  </div>

  <div class="stack">
    @include('student.partials.locked-banner')

    <section class="card" id="sec-verified" aria-labelledby="h-ver">
      <div class="card-h"><h2 id="h-ver">Verified values</h2><span class="meta">Only verified records count towards eligibility.</span></div>
      <div class="stat-grid">
        <div class="stat"><span class="cap">10th</span><div class="v">{{ $pct($verified['tenth_percentage']) }}</div></div>
        <div class="stat"><span class="cap">{{ $secondLabel }}</span><div class="v">{{ $pct($secondValue) }}</div></div>
        <div class="stat"><span class="cap">CGPA</span><div class="v">{{ $verified['cgpa'] === null ? '–' : U::num2($verified['cgpa']) }}</div></div>
        <div class="stat"><span class="cap">Active backlogs</span><div class="v">{{ $verified['active_backlogs'] }}</div></div>
        <div class="stat"><span class="cap">Total backlogs</span><div class="v">{{ $verified['total_backlogs'] }}</div></div>
      </div>
      @if (empty($verified['is_complete']))
        <div style="margin-top:16px">@include('student.partials.notice', ['kind' => 'wait', 'ic' => 'info', 'text' => 'Not verified yet: ' . implode(', ', $verified['missing'])])</div>
      @endif
    </section>

    <section class="card" id="sec-academic" aria-labelledby="h-rec">
      <div class="card-h"><h2 id="h-rec">Records</h2></div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Record</th><th>Status</th><th>Marks</th><th>Marksheet</th><th>Submitted</th><th><span class="sr">Action</span></th></tr></thead>
          <tbody>
            @foreach ($rows as $row)
              @php
                $sh = $row['shown'];
                $when = ($row['open']['submitted_at'] ?? null) ?: ($sh['submitted_at'] ?? null);
                if (! $sh) {
                    $sub = '';
                } elseif ($sh['level'] === 'DEGREE_SEM') {
                    $sub = 'Backlogs in term ' . $sh['backlogs_in_term'] . ' · Active after term ' . $sh['active_backlogs_after_term'];
                } else {
                    $sub = implode(' · ', array_filter([$sh['institution_name'] ?? null, $sh['passing_year'] ?? null]));
                }
              @endphp
              <tr>
                <td data-l="Record"><b>{{ $row['label'] }}</b>@if (! $row['required']) <span class="tag">Optional</span>@endif @if ($sub !== '')<span class="sub">{{ $sub }}</span>@endif</td>
                <td data-l="Status">@include('student.partials.acad-status', ['row' => $row])</td>
                <td data-l="Marks">@if (U::marks($sh) !== null)<span class="num">{{ U::marks($sh) }}</span>@else<span class="meta">&ndash;</span>@endif</td>
                <td data-l="Marksheet">@if ($sh)@include('student.partials.file-link', ['doc' => $sh['document'] ?? null])@else<span class="meta">&ndash;</span>@endif</td>
                <td data-l="Submitted">@if ($when){{ U::date($when) }}@else<span class="meta">&ndash;</span>@endif</td>
                <td class="act">@include('student.partials.acad-actions', ['row' => $row, 'full' => true])</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
@include('student.partials.acad-drawers')
@endsection
