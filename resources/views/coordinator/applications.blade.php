{{--
  Applications of the department's students (read only). Route: coordinator.applications
  $rows      GET /api/coordinator/applications -> data. Row: id, student{id,university_id,full_name,branch}, company,
             drive{id,title}, stage, applied_at, stage_updated_at
  $pg        ['current_page','last_page','total','prev_url','next_url']
  $filters   ['drive_id','stage','branch_id','search']
  $drives    optional [['id','company','title'], ...] for the drive select (omit to hide it)
  $branches  [['id','code','name'], ...]
--}}
@extends('layouts.coordinator')
@use('App\Support\StudentUi', 'U')
@section('title', 'Applications')

@section('content')
@php
  $f = $filters;
  $hasFilter = ! empty($f['drive_id']) || ! empty($f['stage']) || ! empty($f['branch_id']) || ! empty($f['search']);
  $stageNames = ['APPLIED' => 'Applied', 'SHORTLISTED' => 'Shortlisted', 'APTITUDE' => 'Aptitude', 'TECHNICAL' => 'Technical', 'HR' => 'HR', 'SELECTED' => 'Selected', 'REJECTED' => 'Rejected', 'OFFER_RECEIVED' => 'Offer received', 'PLACED' => 'Placed', 'WITHDRAWN' => 'Withdrawn'];
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>Applications</h1>
      <p class="meta">Where your students are in each drive. Stages are updated by the T&amp;P officer.</p>
    </div>
  </div>

  <div class="stack">
    <section class="card" aria-labelledby="h-ap">
      <div class="card-h"><h2 id="h-ap">All applications</h2></div>

      <form method="get" action="{{ route('coordinator.applications') }}" class="fbar">
        <div class="field grow">
          <label for="f-search">Name or university ID</label>
          <input id="f-search" type="text" name="search" value="{{ $f['search'] ?? '' }}" autocomplete="off">
        </div>
        @if (! empty($drives))
          <div class="field">
            <label for="f-drive">Drive</label>
            <select id="f-drive" name="drive_id">
              <option value="">All drives</option>
              @foreach ($drives as $d)
                <option value="{{ $d['id'] }}" @selected((string) ($f['drive_id'] ?? '') === (string) $d['id'])>{{ $d['company'] }} &middot; {{ $d['title'] }}</option>
              @endforeach
            </select>
          </div>
        @elseif (! empty($f['drive_id']))
          <input type="hidden" name="drive_id" value="{{ $f['drive_id'] }}">
        @endif
        <div class="field">
          <label for="f-stage">Stage</label>
          <select id="f-stage" name="stage">
            <option value="">All stages</option>
            @foreach ($stageNames as $code => $name)
              <option value="{{ $code }}" @selected(($f['stage'] ?? '') === $code)>{{ $name }}</option>
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
        <div class="row">
          <button type="submit" class="btn btn-primary">Filter</button>
          @if ($hasFilter)<a class="btn btn-secondary" href="{{ route('coordinator.applications') }}">Reset</a>@endif
        </div>
      </form>

      @if (count($rows))
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>Student</th><th>Branch</th><th>Company</th><th>Drive</th><th>Stage</th><th>Applied</th><th>Last change</th><th><span class="sr">Action</span></th></tr></thead>
            <tbody>
              @foreach ($rows as $r)
                <tr>
                  <td data-l="Student"><b>{{ $r['student']['full_name'] }}</b><span class="sub">{{ $r['student']['university_id'] }}</span></td>
                  <td data-l="Branch">{{ U::orDash($r['student']['branch'] ?? null) }}</td>
                  <td data-l="Company"><b>{{ $r['company'] }}</b></td>
                  <td data-l="Drive">{{ $r['drive']['title'] }}</td>
                  <td data-l="Stage">@include('coordinator.partials.badge', ['kind' => 'stage', 'value' => $r['stage']])</td>
                  <td data-l="Applied" class="nw">{{ U::date($r['applied_at']) }}</td>
                  <td data-l="Last change" class="nw">{{ U::date($r['stage_updated_at'] ?? null) ?: '–' }}</td>
                  <td class="act"><a class="btn btn-secondary" href="{{ route('coordinator.applications.show', $r['id']) }}">View</a></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="empty">{{ $hasFilter ? 'No applications match these filters.' : 'No applications yet.' }}</p>
      @endif
      @include('coordinator.partials.pager', ['pg' => $pg])
    </section>
  </div>
</div>
@endsection
