{{--
  Placement drives open to the department (read only; the TPO manages drives). Route: coordinator.drives
  $rows     GET /api/coordinator/drives -> data. Row: id, company, title, employment_type, ctc_lpa, ctc_max_lpa, location,
            graduation_year, drive_date, application_deadline, status, department_applications_count
  $pg       ['current_page','last_page','total','prev_url','next_url']
  $filters  ['status' => PUBLISHED|CLOSED|'', 'graduation_year' => int|'']
--}}
@extends('layouts.coordinator')
@use('App\Support\StudentUi', 'U')
@section('title', 'Drives')

@section('content')
@php
  $f = $filters;
  $hasFilter = ! empty($f['status']) || ! empty($f['graduation_year']);
  $ctc = function ($r) {
      if (($r['ctc_lpa'] ?? null) === null) { return '–'; }
      $max = $r['ctc_max_lpa'] ?? null;
      $range = $max !== null && (float) $max !== (float) $r['ctc_lpa'] ? ' – ' . U::num2($max) : '';

      return U::num2($r['ctc_lpa']) . $range . ' LPA';
  };
  $human = fn ($v) => ucfirst(strtolower(str_replace('_', ' ', (string) $v)));
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>Drives</h1>
      <p class="meta">Drives your students can apply to. The T&amp;P officer manages them; this list is read only.</p>
    </div>
  </div>

  <div class="stack">
    <section class="card" aria-labelledby="h-dr">
      <div class="card-h"><h2 id="h-dr">All drives</h2></div>

      <form method="get" action="{{ route('coordinator.drives') }}" class="fbar">
        <div class="field">
          <label for="f-status">Status</label>
          <select id="f-status" name="status">
            <option value="">All</option>
            @foreach (['PUBLISHED' => 'Published', 'CLOSED' => 'Closed'] as $code => $name)
              <option value="{{ $code }}" @selected(($f['status'] ?? '') === $code)>{{ $name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="f-gy">Graduation year</label>
          <input id="f-gy" type="number" name="graduation_year" min="2000" max="2100" value="{{ $f['graduation_year'] ?? '' }}">
        </div>
        <div class="row">
          <button type="submit" class="btn btn-primary">Filter</button>
          @if ($hasFilter)<a class="btn btn-secondary" href="{{ route('coordinator.drives') }}">Reset</a>@endif
        </div>
      </form>

      @if (count($rows))
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>Drive</th><th>Type</th><th>CTC</th><th>Location</th><th>Graduation year</th><th>Drive date</th><th>Deadline</th><th>Status</th><th>Applications</th><th><span class="sr">Action</span></th></tr></thead>
            <tbody>
              @foreach ($rows as $r)
                <tr>
                  <td data-l="Drive"><b>{{ $r['company'] }}</b><span class="sub">{{ $r['title'] }}</span></td>
                  <td data-l="Type">{{ $human($r['employment_type'] ?? '') ?: '–' }}</td>
                  <td data-l="CTC" class="num nw">{{ $ctc($r) }}</td>
                  <td data-l="Location">{{ U::orDash($r['location'] ?? null) }}</td>
                  <td data-l="Graduation year" class="num">{{ $r['graduation_year'] }}</td>
                  <td data-l="Drive date" class="nw">{{ U::date($r['drive_date'] ?? null) ?: '–' }}</td>
                  <td data-l="Deadline" class="nw">{{ U::date($r['application_deadline'] ?? null) ?: '–' }}</td>
                  <td data-l="Status">@include('coordinator.partials.badge', ['kind' => 'drive', 'value' => $r['status']])</td>
                  <td data-l="Applications" class="num">{{ $r['department_applications_count'] }}</td>
                  <td class="act"><a class="btn btn-secondary" href="{{ route('coordinator.drives.show', $r['id']) }}">View</a></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="empty">{{ $hasFilter ? 'No drives match these filters.' : 'No drives yet.' }}</p>
      @endif
      @include('coordinator.partials.pager', ['pg' => $pg])
    </section>
  </div>
</div>
@endsection
