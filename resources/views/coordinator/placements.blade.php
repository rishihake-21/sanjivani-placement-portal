{{--
  Official placement records of the department's students (read only; the TPO maintains them). Route: coordinator.placements
  $rows       GET /api/coordinator/placements -> data. Row: id, student{id,university_id,full_name,branch}, company,
              role_title, ctc_lpa, location, offer_date, joining_date, status
  $pg         ['current_page','last_page','total','prev_url','next_url']
  $filters    ['status','branch_id','graduation_year','company_id']
  $branches   [['id','code','name'], ...]
  $companies  optional [['id','name'], ...] for the company select (omit to hide it)
--}}
@extends('layouts.coordinator')
@use('App\Support\StudentUi', 'U')
@section('title', 'Placements')

@section('content')
@php
  $f = $filters;
  $hasFilter = ! empty($f['status']) || ! empty($f['branch_id']) || ! empty($f['graduation_year']) || ! empty($f['company_id']);
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>Placements</h1>
      <p class="meta">Offers and placements of your students, newest offer first.</p>
    </div>
  </div>

  <div class="stack">
    <section class="card" aria-labelledby="h-pl">
      <div class="card-h"><h2 id="h-pl">All placements</h2></div>

      <form method="get" action="{{ route('coordinator.placements') }}" class="fbar">
        <div class="field">
          <label for="f-status">Status</label>
          <select id="f-status" name="status">
            <option value="">All</option>
            @foreach (['OFFERED' => 'Offered', 'PLACED' => 'Placed', 'DECLINED' => 'Declined'] as $code => $name)
              <option value="{{ $code }}" @selected(($f['status'] ?? '') === $code)>{{ $name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="f-branch">Branch</label>
          <select id="f-branch" name="branch_id">
            <option value="">All branches</option>
            @foreach ($branches as $b)
              <option value="{{ $b['id'] }}" @selected((string) ($f['branch_id'] ?? '') === (string) $b['id'])>{{ $b['code'] }} &middot; {{ $b['name'] }}</option>
            @endforeach
          </select>
        </div>
        @if (! empty($companies))
          <div class="field">
            <label for="f-company">Company</label>
            <select id="f-company" name="company_id">
              <option value="">All companies</option>
              @foreach ($companies as $c)
                <option value="{{ $c['id'] }}" @selected((string) ($f['company_id'] ?? '') === (string) $c['id'])>{{ $c['name'] }}</option>
              @endforeach
            </select>
          </div>
        @elseif (! empty($f['company_id']))
          <input type="hidden" name="company_id" value="{{ $f['company_id'] }}">
        @endif
        <div class="field">
          <label for="f-gy">Graduation year</label>
          <input id="f-gy" type="number" name="graduation_year" min="2000" max="2100" value="{{ $f['graduation_year'] ?? '' }}">
        </div>
        <div class="row">
          <button type="submit" class="btn btn-primary">Filter</button>
          @if ($hasFilter)<a class="btn btn-secondary" href="{{ route('coordinator.placements') }}">Reset</a>@endif
        </div>
      </form>

      @if (count($rows))
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>Student</th><th>Branch</th><th>Company</th><th>Role</th><th>CTC</th><th>Location</th><th>Offer date</th><th>Joining date</th><th>Status</th></tr></thead>
            <tbody>
              @foreach ($rows as $r)
                <tr>
                  <td data-l="Student"><a class="linkbtn" href="{{ route('coordinator.students.show', $r['student']['id']) }}"><b>{{ $r['student']['full_name'] }}</b></a><span class="sub">{{ $r['student']['university_id'] }}</span></td>
                  <td data-l="Branch">{{ U::orDash($r['student']['branch'] ?? null) }}</td>
                  <td data-l="Company"><b>{{ $r['company'] }}</b></td>
                  <td data-l="Role">{{ U::orDash($r['role_title'] ?? null) }}</td>
                  <td data-l="CTC" class="num nw">{{ ($r['ctc_lpa'] ?? null) === null ? '–' : U::num2($r['ctc_lpa']) . ' LPA' }}</td>
                  <td data-l="Location">{{ U::orDash($r['location'] ?? null) }}</td>
                  <td data-l="Offer date" class="nw">{{ U::date($r['offer_date'] ?? null) ?: '–' }}</td>
                  <td data-l="Joining date" class="nw">{{ U::date($r['joining_date'] ?? null) ?: '–' }}</td>
                  <td data-l="Status">@include('coordinator.partials.badge', ['kind' => 'plc', 'value' => $r['status']])</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="empty">{{ $hasFilter ? 'No placements match these filters.' : 'No placements recorded yet.' }}</p>
      @endif
      @include('coordinator.partials.pager', ['pg' => $pg])
    </section>
  </div>
</div>
@endsection
