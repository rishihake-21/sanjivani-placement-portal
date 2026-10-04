@extends('layouts.student')

@section('title', 'My Applications - TPMS')

@section('content')
<div class="page">
    <div class="ph">
        <div>
            <h1>My Applications</h1>
            <p class="meta">Track your placement applications</p>
        </div>
    </div>
    
    <div class="stack">
        @if ($applications->count())
            <section class="card">
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Role</th>
                                <th>Applied On</th>
                                <th>Status</th>
                                <th>Drive Date</th>
                                <th><span class="sr">Action</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($applications as $app)
                                <tr>
                                    <td data-l="Company"><b>{{ $app->drive->company_name }}</b></td>
                                    <td data-l="Role">{{ $app->drive->role }}</td>
                                    <td data-l="Applied On">{{ \Carbon\Carbon::parse($app->created_at)->format('d M Y') }}</td>
                                    <td data-l="Status">
                                        <span class="badge b-{{ 
                                            $app->status === 'applied' ? 'wait' : 
                                            ($app->status === 'shortlisted' ? 'ok' : 
                                            ($app->status === 'rejected' ? 'no' : 'neutral')) }}">
                                            {{ ucfirst($app->status) }}
                                        </span>
                                    </td>
                                    <td data-l="Drive Date">{{ \Carbon\Carbon::parse($app->drive->drive_date)->format('d M Y') }}</td>
                                    <td class="act">
                                        <button type="button" class="btn btn-secondary" data-act="view-app" data-id="{{ $app->id }}">View</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section class="card">
                <p class="empty">You haven't applied to any drives yet. <a href="{{ route('student.drives') }}" class="linkbtn">Browse drives</a></p>
            </section>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
// Applications page - view application action
(function() {
    'use strict';
    const $ = (s, r) => (r || document).querySelector(s);
    
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-act="view-app"]');
        if (btn) {
            alert('View application: ' + btn.dataset.id);
        }
    });
})();
</script>
@endpush