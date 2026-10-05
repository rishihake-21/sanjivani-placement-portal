{{--
  Students of the coordinator's department. Route: coordinator.students  (GET, filters in the query string)
  $rows      GET /api/coordinator/students -> data. StudentSummaryResource rows:
             id, university_id, full_name, department (code), branch (code), admission_type, current_semester,
             graduation_year, pending_reviews (pending academic records + experiences)
  $pg        ['current_page','last_page','total','prev_url','next_url']  (urls keep the filters)
  $filters   current values: ['search','branch_id','semester','admission_type','only_pending']  (missing keys = empty)
  $branches  [['id','code','name'], ...]  the department's branches
--}}
@extends('layouts.coordinator')
@use('App\Support\StudentUi', 'U')
@section('title', 'Students')

@section('content')
@php
  $f = $filters;
  $hasFilter = ! empty($f['search']) || ! empty($f['branch_id']) || ! empty($f['semester']) || ! empty($f['admission_type']) || ! empty($f['only_pending']);
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>Students</h1>
      <p class="meta">{{ $department['name'] }}. Open a student to see the full profile and every record.</p>
    </div>
  </div>

  <div class="stack">
    <section class="card" aria-labelledby="h-stu">
      <div class="card-h"><h2 id="h-stu">Directory</h2></div>

      <form method="get" action="{{ route('coordinator.students') }}" class="fbar">
        <div class="field grow">
          <label for="f-search">Name or university ID</label>
          <input id="f-search" type="text" name="search" value="{{ $f['search'] ?? '' }}" autocomplete="off">
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
        <div class="field">
          <label for="f-sem">Semester</label>
          <select id="f-sem" name="semester">
            <option value="">All</option>
            @foreach (range(1, 8) as $s)
              <option value="{{ $s }}" @selected((string) ($f['semester'] ?? '') === (string) $s)>Semester {{ $s }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="f-adm">Admission type</label>
          <select id="f-adm" name="admission_type">
            <option value="">All</option>
            @foreach (U::ADMISSION as $code => $name)
              <option value="{{ $code }}" @selected(($f['admission_type'] ?? '') === $code)>{{ $name }}</option>
            @endforeach
          </select>
        </div>
        <label class="chk"><input type="checkbox" name="only_pending" value="1" @checked(! empty($f['only_pending']))> Only with pending reviews</label>
        <div class="row">
          <button type="submit" class="btn btn-primary">Filter</button>
          @if ($hasFilter)<a class="btn btn-secondary" href="{{ route('coordinator.students') }}">Reset</a>@endif
        </div>
      </form>

      @if (count($rows))
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>University ID</th><th>Name</th><th>Branch</th><th>Admission</th><th>Semester</th><th>Graduation year</th><th>Pending reviews</th><th><span class="sr">Action</span></th></tr></thead>
            <tbody>
              @foreach ($rows as $r)
                <tr>
                  <td data-l="University ID" class="nw">{{ $r['university_id'] }}</td>
                  <td data-l="Name"><b>{{ $r['full_name'] }}</b></td>
                  <td data-l="Branch">{{ U::orDash($r['branch'] ?? null) }}</td>
                  <td data-l="Admission">{{ U::admission($r['admission_type']) }}</td>
                  <td data-l="Semester" class="num">{{ $r['current_semester'] }}</td>
                  <td data-l="Graduation year" class="num">{{ $r['graduation_year'] }}</td>
                  <td data-l="Pending reviews">@if (! empty($r['pending_reviews']))<span class="badge b-wait">{{ $r['pending_reviews'] }}</span>@else<span class="meta">&ndash;</span>@endif</td>
                  <td class="act"><a class="btn btn-secondary" href="{{ route('coordinator.students.show', $r['id']) }}">View</a></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="empty">{{ $hasFilter ? 'No students match these filters.' : 'No students in this department yet.' }}</p>
      @endif
      @include('coordinator.partials.pager', ['pg' => $pg])
    </section>
  </div>
</div>
@endsection
