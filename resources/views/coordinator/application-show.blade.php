{{--
  One application with its stage history and the academic snapshot taken when the student applied. Route: coordinator.applications.show
  $application  GET /api/coordinator/applications/{id} -> data: id, student{id,university_id,full_name,branch}, company,
                drive{id,title}, stage, applied_at, stage_updated_at, data_snapshot (frozen verified values, any shape),
                history[{from,to,by,remarks,at}]
  The snapshot is printed as a generic label/value list, so it follows whatever the snapshot builder stores.
--}}
@extends('layouts.coordinator')
@use('App\Support\StudentUi', 'U')
@section('title', $application['company'] . ' · ' . $application['student']['full_name'])

@section('content')
@php
  $st = $application['student'];
  $history = $application['history'] ?? [];
  $snapshot = $application['data_snapshot'] ?? [];
  $flat = function ($data, $prefix = '') use (&$flat) {
      $out = [];
      foreach ($data as $k => $v) {
          $name = is_int($k) ? '#' . ($k + 1) : ucfirst(str_replace('_', ' ', (string) $k));
          $label = $prefix === '' ? $name : $prefix . ' › ' . $name;
          if (is_array($v)) {
              $out = array_merge($out, $flat($v, $label));
          } else {
              $out[] = [$label, is_bool($v) ? ($v ? 'Yes' : 'No') : (($v === null || $v === '') ? '–' : (string) $v)];
          }
      }

      return $out;
  };
  $snap = is_array($snapshot) ? $flat($snapshot) : [];
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>{{ $st['full_name'] }}</h1>
      <p class="meta">{{ $st['university_id'] }}@if (! empty($st['branch'])) &middot; {{ $st['branch'] }}@endif &middot; {{ $application['company'] }} &middot; {{ $application['drive']['title'] }}</p>
    </div>
    <div class="row wrap">
      @include('coordinator.partials.badge', ['kind' => 'stage', 'value' => $application['stage']])
      <a class="btn btn-secondary" href="{{ route('coordinator.applications') }}">Back to applications</a>
      <a class="btn btn-primary" href="{{ route('coordinator.students.show', $st['id']) }}">View student</a>
    </div>
  </div>

  <div class="stack">
    <section class="card" aria-labelledby="h-sum">
      <div class="card-h"><h2 id="h-sum">Summary</h2></div>
      <dl class="dl">
        <div><dt>Company</dt><dd>{{ $application['company'] }}</dd></div>
        <div><dt>Drive</dt><dd><a class="linkbtn" href="{{ route('coordinator.drives.show', $application['drive']['id']) }}">{{ $application['drive']['title'] }}</a></dd></div>
        <div><dt>Applied</dt><dd>{{ U::date($application['applied_at']) }}</dd></div>
        <div><dt>Last change</dt><dd>{{ U::date($application['stage_updated_at'] ?? null) ?: '–' }}</dd></div>
      </dl>
    </section>

    <section class="card" aria-labelledby="h-his">
      <div class="card-h"><h2 id="h-his">Stage history</h2></div>
      @if (count($history))
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>When</th><th>Change</th><th>By</th><th>Remarks</th></tr></thead>
            <tbody>
              @foreach ($history as $h)
                <tr>
                  <td data-l="When" class="nw">{{ U::dateTime($h['at']) }}</td>
                  <td data-l="Change" class="nw">@if (! empty($h['from']))@include('coordinator.partials.badge', ['kind' => 'stage', 'value' => $h['from']]) &rarr; @endif @include('coordinator.partials.badge', ['kind' => 'stage', 'value' => $h['to']])</td>
                  <td data-l="By">{{ U::orDash($h['by'] ?? null) }}</td>
                  <td data-l="Remarks">{{ U::orDash($h['remarks'] ?? null) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="empty">No stage changes recorded yet.</p>
      @endif
    </section>

    <section class="card" aria-labelledby="h-snap">
      <div class="card-h"><h2 id="h-snap">Academic snapshot</h2><span class="meta">Verified values frozen when the student applied.</span></div>
      @if (count($snap))
        <table class="cmp">
          <tbody>
            @foreach ($snap as [$label, $value])
              <tr><td class="fn">{{ $label }}</td><td>{{ $value }}</td></tr>
            @endforeach
          </tbody>
        </table>
      @else
        <p class="empty">No snapshot is stored for this application.</p>
      @endif
    </section>
  </div>
</div>
@endsection
