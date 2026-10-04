@extends('layouts.student')
@use('App\Support\StudentUi', 'U')
@section('title', 'Experience')

@section('content')
@php
  $experiences = array_values($experiences ?? []);
  $locked = ! empty($student['profile_locked']);
  $liveExps = array_values(array_filter($experiences, fn ($x) => $x['status'] !== 'SUPERSEDED'));
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>Experience</h1>
      <p class="meta">Entries without a certificate stay self-declared and are shown as unverified.</p>
    </div>
    <div class="row wrap">
      <button type="button" class="btn btn-primary" data-open="exp-new" @disabled($locked)>{!! U::icon('plus', 'sm') !!} Add experience</button>
    </div>
  </div>

  <div class="stack">
    @include('student.partials.locked-banner')
    <section class="card" id="sec-experience" aria-label="Experience entries">
      @if (count($liveExps))
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>Experience</th><th>Dates</th><th>Status</th><th>Certificate</th><th><span class="sr">Action</span></th></tr></thead>
            <tbody>
              @foreach ($liveExps as $x)
                <tr>
                  <td data-l="Experience"><b>{{ U::expType($x['type']) }} &middot; {{ $x['organization'] }}</b><span class="sub">{{ $x['role_title'] }}</span></td>
                  <td data-l="Dates" class="num nw">{{ U::expDates($x) }}</td>
                  <td data-l="Status">
                    <div class="stk">{!! U::badge('rec', $x['status']) !!}@if (! empty($x['supersedes_id']))<span class="tag">Revision</span>@endif @include('student.partials.rej-why', ['rej' => $x['rejection'] ?? null])</div>
                  </td>
                  <td data-l="Certificate">@include('student.partials.file-link', ['doc' => $x['certificate'] ?? null])</td>
                  <td class="act">
                    @foreach (U::expActions($x, $locked) as $a)
                      <button type="button" class="btn btn-secondary" data-open="exp-{{ $x['id'] }}"@if ($a['focus']) data-focus="file"@endif @disabled($a['disabled'])>{{ $a['label'] }}</button>
                    @endforeach
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="empty">Optional: add internships or work experience.</p>
      @endif
    </section>
  </div>
</div>
@include('student.partials.exp-drawers')
@endsection
