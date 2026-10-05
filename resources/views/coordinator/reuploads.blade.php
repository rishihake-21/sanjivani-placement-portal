{{--
  Re-upload requests of the department. Route: coordinator.reuploads  (GET ?status=OPEN|FULFILLED|CANCELLED)
  $rows    GET /api/coordinator/reupload-requests -> data. Row: id, subject_type, subject_id, reason, status,
           created_at, closed_at, student{id,university_id,full_name}
  $pg      ['current_page','last_page','total','prev_url','next_url']
  $status  current status filter ('' = all)
  Cancel posts (no body) to coordinator.x.reupload.cancel.  New requests are made from the student detail page.
--}}
@extends('layouts.coordinator')
@use('App\Support\StudentUi', 'U')
@section('title', 'Re-upload requests')

@section('content')
@php
  $subject = ['ACADEMIC_RECORD' => 'Academic record', 'EXPERIENCE' => 'Experience', 'DOCUMENT' => 'Document'];
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>Re-upload requests</h1>
      <p class="meta">Items you sent back to a student. A request closes when the student uploads again.</p>
    </div>
  </div>

  <div class="stack">
    <section class="card" aria-labelledby="h-ru">
      <div class="card-h"><h2 id="h-ru">Requests</h2></div>

      <form method="get" action="{{ route('coordinator.reuploads') }}" class="fbar">
        <div class="field">
          <label for="f-status">Status</label>
          <select id="f-status" name="status">
            <option value="">All</option>
            @foreach (['OPEN' => 'Open', 'FULFILLED' => 'Fulfilled', 'CANCELLED' => 'Cancelled'] as $code => $name)
              <option value="{{ $code }}" @selected(($status ?? '') === $code)>{{ $name }}</option>
            @endforeach
          </select>
        </div>
        <div class="row">
          <button type="submit" class="btn btn-primary">Filter</button>
          @if (! empty($status))<a class="btn btn-secondary" href="{{ route('coordinator.reuploads') }}">Reset</a>@endif
        </div>
      </form>

      @if (count($rows))
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>Student</th><th>Item</th><th>Reason</th><th>Status</th><th>Requested</th><th>Closed</th><th><span class="sr">Action</span></th></tr></thead>
            <tbody>
              @foreach ($rows as $r)
                <tr>
                  <td data-l="Student"><a class="linkbtn" href="{{ route('coordinator.students.show', $r['student']['id']) }}"><b>{{ $r['student']['full_name'] }}</b></a><span class="sub">{{ $r['student']['university_id'] }}</span></td>
                  <td data-l="Item"><b>{{ $subject[$r['subject_type']] ?? $r['subject_type'] }}</b><span class="sub">#{{ $r['subject_id'] }}</span></td>
                  <td data-l="Reason">{{ $r['reason'] }}</td>
                  <td data-l="Status">@include('coordinator.partials.badge', ['kind' => 'req', 'value' => $r['status']])</td>
                  <td data-l="Requested" class="nw">{{ U::date($r['created_at']) }}</td>
                  <td data-l="Closed" class="nw">{{ U::date($r['closed_at'] ?? null) ?: '–' }}</td>
                  <td class="act">@if ($r['status'] === 'OPEN')<button type="button" class="btn btn-secondary" data-post="{{ route('coordinator.x.reupload.cancel', $r['id']) }}" data-done="Re-upload request cancelled.">Cancel request</button>@endif</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="empty">{{ ! empty($status) ? 'No requests with this status.' : 'No re-upload requests yet.' }}</p>
      @endif
      @include('coordinator.partials.pager', ['pg' => $pg])
    </section>
  </div>
</div>
@endsection
