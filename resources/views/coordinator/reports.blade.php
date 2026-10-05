{{--
  Reports. Route: coordinator.reports
  $rows            GET /api/coordinator/reports/branch-summary -> data. Row: branch_id, code, name, students, opted_out,
                   applied, placed, unplaced, placement_percentage (nullable), highest_ctc_lpa, average_ctc_lpa
  $graduationYear  int|null  the graduation_year the summary was computed for
  CSV downloads: coordinator.reports.students-csv and coordinator.reports.placements-csv (both accept ?graduation_year=)
--}}
@extends('layouts.coordinator')
@use('App\Support\StudentUi', 'U')
@section('title', 'Reports')

@section('content')
@php
  $q = $graduationYear ? ['graduation_year' => $graduationYear] : [];
  $lpa = fn ($v) => $v === null ? '–' : U::num2($v);
  $sum = fn ($key) => array_sum(array_map(fn ($r) => (int) $r[$key], $rows));
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>Reports</h1>
      <p class="meta">{{ $department['name'] }} &middot; {{ $graduationYear ? 'Graduation year ' . $graduationYear : 'All graduation years' }}</p>
    </div>
    <form method="get" action="{{ route('coordinator.reports') }}" class="fbar" style="margin:0">
      <div class="field">
        <label for="gy">Graduation year</label>
        <input id="gy" type="number" name="graduation_year" min="2000" max="2100" value="{{ $graduationYear }}">
      </div>
      <button type="submit" class="btn btn-secondary">Apply</button>
    </form>
  </div>

  <div class="stack">
    <section class="card" aria-labelledby="h-br">
      <div class="card-h"><h2 id="h-br">Branch summary</h2></div>
      @if (count($rows))
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>Branch</th><th>Students</th><th>Opted out</th><th>Applied</th><th>Placed</th><th>Unplaced</th><th>Placement %</th><th>Highest CTC (LPA)</th><th>Average CTC (LPA)</th></tr></thead>
            <tbody>
              @foreach ($rows as $r)
                <tr>
                  <td data-l="Branch"><b>{{ $r['code'] }}</b><span class="sub">{{ $r['name'] }}</span></td>
                  <td data-l="Students" class="num">{{ $r['students'] }}</td>
                  <td data-l="Opted out" class="num">{{ $r['opted_out'] }}</td>
                  <td data-l="Applied" class="num">{{ $r['applied'] }}</td>
                  <td data-l="Placed" class="num">{{ $r['placed'] }}</td>
                  <td data-l="Unplaced" class="num">{{ $r['unplaced'] }}</td>
                  <td data-l="Placement %" class="num">{{ ($r['placement_percentage'] ?? null) === null ? '–' : U::num2($r['placement_percentage']) . '%' }}</td>
                  <td data-l="Highest CTC (LPA)" class="num">{{ $lpa($r['highest_ctc_lpa'] ?? null) }}</td>
                  <td data-l="Average CTC (LPA)" class="num">{{ $lpa($r['average_ctc_lpa'] ?? null) }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <td data-l="Branch"><b>Total</b></td>
                <td data-l="Students" class="num"><b>{{ $sum('students') }}</b></td>
                <td data-l="Opted out" class="num"><b>{{ $sum('opted_out') }}</b></td>
                <td data-l="Applied" class="num"><b>{{ $sum('applied') }}</b></td>
                <td data-l="Placed" class="num"><b>{{ $sum('placed') }}</b></td>
                <td data-l="Unplaced" class="num"><b>{{ $sum('unplaced') }}</b></td>
                <td></td><td></td><td></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <p class="meta" style="margin-top:12px">Placement percentage is placed students out of those eligible for placement, as the module calculates it.</p>
      @else
        <p class="empty">No branches in this department yet.</p>
      @endif
    </section>

    <section class="card" aria-labelledby="h-csv">
      <div class="card-h"><h2 id="h-csv">Downloads</h2></div>
      <div class="list">
        <div class="li">
          <div class="l"><div class="t">Students</div><div class="meta">One row per student with the official placement status.</div></div>
          <div class="r"><a class="btn btn-secondary" href="{{ route('coordinator.reports.students-csv', $q) }}">{!! U::icon('down', 'sm') !!} Download CSV</a></div>
        </div>
        <div class="li">
          <div class="l"><div class="t">Placements</div><div class="meta">One row per placement record.</div></div>
          <div class="r"><a class="btn btn-secondary" href="{{ route('coordinator.reports.placements-csv', $q) }}">{!! U::icon('down', 'sm') !!} Download CSV</a></div>
        </div>
      </div>
    </section>
  </div>
</div>
@endsection
