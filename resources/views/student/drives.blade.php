@extends('layouts.student')

@section('title', 'Placement Drives - TPMS')

@section('content')
<div class="page">
    <div class="ph">
        <div>
            <h1>Placement Drives</h1>
            <p class="meta">Available drives for {{ $student->branch->name }} (Semester {{ $student->current_semester }})</p>
        </div>
    </div>
    
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
                                <th>Eligibility</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th><span class="sr">Action</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($drives as $drive)
                                <tr>
                                    <td data-l="Company"><b>{{ $drive->company->name }}</b></td>
                                    <td data-l="Role">{{ $drive->title }}</td>
                                    <td data-l="Package">{{ $drive->ctc_lpa }} LPA</td>
                                    <td data-l="Eligibility">
                                        @if ($drive->eligibility)
                                            CGPA ≥ {{ $drive->eligibility->min_cgpa }}, 10th ≥ {{ $drive->eligibility->min_tenth_percentage }}%, 12th ≥ {{ $drive->eligibility->min_twelfth_percentage }}%
                                        @else
                                            <span class="meta">Not specified</span>
                                        @endif
                                    </td>
                                    <td data-l="Date">
                                        @if ($drive->application_deadline)
                                            {{ \Carbon\Carbon::parse($drive->application_deadline)->format('d M Y') }}
                                        @else
                                            <span class="meta">Not specified</span>
                                        @endif
                                    </td>
                                    <td data-l="Status">
                                        <span class="badge b-{{ $drive->status->value === 'PUBLISHED' ? 'ok' : ($drive->status->value === 'CLOSED' ? 'no' : 'wait') }}">{{ $drive->status->value }}</span>
                                    </td>
                                    <td class="act">
                                        <a href="{{ route('student.drives.show', $drive->id) }}" class="btn btn-secondary">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section class="card">
                <p class="empty">No placement drives available for your branch and semester at the moment.</p>
            </section>
        @endif
    </div>
</div>
@endsection