@extends('layouts.student')

@section('title', 'Placement Drives')

@section('content')
    <div class="ph">
        <div>
            <h1>Placement Drives</h1>
            <p class="meta">
                Drives currently published for review.
                @if ($student->branch)
                    Your branch: {{ $student->branch->name }} · Batch {{ $student->graduation_year }}
                @endif
            </p>
        </div>
    </div>

    <section class="card" style="margin-bottom:16px">
        <div class="row wrap" style="align-items:flex-start;gap:12px">
            <div style="flex:1;min-width:220px">
                <strong>Before you apply</strong>
                <p class="meta" style="margin:6px 0 0">Open a drive to review its criteria. Eligibility and profile readiness are checked again when you submit an application.</p>
            </div>
            <a href="{{ route('student.documents') }}#sec-upload" class="btn btn-secondary">Review my resume</a>
        </div>
    </section>

    <div class="stack">
        @if ($drives->count())
            <section class="card">
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Role</th>
                                <th>Package</th>
                                <th>Key criteria</th>
                                <th>Apply by</th>
                                <th>Status</th>
                                <th><span class="sr">Action</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($drives as $drive)
                                <tr>
                                    <td data-l="Company"><b>{{ $drive->company?->name ?? 'Company to be announced' }}</b></td>
                                    <td data-l="Role">{{ $drive->title }}</td>
                                    <td data-l="Package">
                                        @if ($drive->ctc_lpa !== null)
                                            {{ number_format((float) $drive->ctc_lpa, 2) }}@if ($drive->ctc_max_lpa !== null && (float) $drive->ctc_max_lpa !== (float) $drive->ctc_lpa)–{{ number_format((float) $drive->ctc_max_lpa, 2) }}@endif LPA
                                        @else
                                            <span class="meta">Not disclosed</span>
                                        @endif
                                    </td>
                                    <td data-l="Key criteria">
                                        @php($criteria = $drive->eligibility)
                                        @if ($criteria)
                                            <div class="meta">
                                                @if ($criteria->min_cgpa !== null)CGPA ≥ {{ $criteria->min_cgpa }}<br>@endif
                                                @if ($criteria->min_tenth_percentage !== null)10th ≥ {{ $criteria->min_tenth_percentage }}%<br>@endif
                                                @if ($criteria->min_twelfth_percentage !== null)12th ≥ {{ $criteria->min_twelfth_percentage }}%<br>@endif
                                                @if ($criteria->min_diploma_percentage !== null)Diploma ≥ {{ $criteria->min_diploma_percentage }}%<br>@endif
                                                @if ($criteria->max_active_backlogs !== null)Active backlogs ≤ {{ $criteria->max_active_backlogs }}<br>@endif
                                                @if ($criteria->max_total_backlogs !== null)Total backlogs ≤ {{ $criteria->max_total_backlogs }}@endif
                                            </div>
                                        @else
                                            <span class="meta">No academic thresholds listed</span>
                                        @endif
                                    </td>
                                    <td data-l="Apply by">
                                        @if ($drive->application_deadline)
                                            <span>{{ $drive->application_deadline->format('d M Y, g:i A') }}</span>
                                        @else
                                            <span class="meta">Deadline not set</span>
                                        @endif
                                    </td>
                                    <td data-l="Status">
                                        <span class="badge b-{{ $drive->status->value === 'PUBLISHED' ? 'ok' : ($drive->status->value === 'CLOSED' ? 'no' : 'wait') }}">{{ str_replace('_', ' ', $drive->status->value) }}</span>
                                    </td>
                                    <td class="act">
                                        <a href="{{ route('student.drives.show', $drive->id) }}" class="btn btn-secondary">View drive</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section class="card">
                <p class="empty">No published placement drives are available right now. Check again later or contact your T&P coordinator if you think a drive should be visible.</p>
            </section>
        @endif
    </div>
@endsection
