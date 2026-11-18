@extends('layouts.student')

@section('title', 'Placement drives')

@section('content')
    <x-ui.page-header title="Placement drives"
        :meta="e('Published drives'.($student->branch ? ' · '.$student->branch->name.' · Batch '.$student->graduation_year : '').'. Closing soonest first, closed drives last.')">
        <a href="{{ route('student.documents') }}#sec-upload" class="btn btn-secondary">Review my resume</a>
    </x-ui.page-header>

    @if ($drives->count())
        <section class="card flush" aria-label="Drives open for applications">
            <ul class="drives">
                @foreach ($drives->sortBy(fn ($d) => [(int) ($d->application_deadline?->isPast() ?? false), $d->application_deadline?->timestamp ?? PHP_INT_MAX]) as $drive)
                    @php
                        $c = $drive->eligibility;
                        $crit = $c ? array_filter([
                            $c->min_cgpa !== null ? 'CGPA ≥ '.$c->min_cgpa : null,
                            $c->min_tenth_percentage !== null ? '10th ≥ '.$c->min_tenth_percentage.'%' : null,
                            $c->min_twelfth_percentage !== null ? '12th ≥ '.$c->min_twelfth_percentage.'%' : null,
                            $c->min_diploma_percentage !== null ? 'Diploma ≥ '.$c->min_diploma_percentage.'%' : null,
                            $c->max_active_backlogs !== null ? 'Active backlogs ≤ '.$c->max_active_backlogs : null,
                            $c->max_total_backlogs !== null ? 'Total backlogs ≤ '.$c->max_total_backlogs : null,
                        ]) : [];
                        $hours = $drive->application_deadline ? now()->diffInHours($drive->application_deadline, false) : null;
                        $left = match (true) {
                            $hours === null => null,
                            $hours < 0 => 'Closed',
                            $hours < 24 => 'Closes today',
                            $hours < 48 => '1 day left',
                            $hours < 24 * 7 => (int) ceil($hours / 24).' days left',
                            default => null,
                        };
                    @endphp
                    <li class="drive">
                        <div class="drive-main">
                            <div class="drive-co">{{ $drive->company?->name ?? 'Company to be announced' }}</div>
                            <a class="drive-t" href="{{ route('student.drives.show', $drive->id) }}">{{ $drive->title }}</a>
                            @if ($crit)
                                <ul class="crit" aria-label="Eligibility">@foreach ($crit as $item)<li>{{ $item }}</li>@endforeach</ul>
                            @else
                                <p class="meta" style="margin:8px 0 0">No academic thresholds listed</p>
                            @endif
                        </div>
                        <div class="drive-pay">
                            @if ($drive->ctc_lpa !== null)
                                {{ number_format((float) $drive->ctc_lpa, 2) }}@if ($drive->ctc_max_lpa !== null && (float) $drive->ctc_max_lpa !== (float) $drive->ctc_lpa)–{{ number_format((float) $drive->ctc_max_lpa, 2) }}@endif <small>LPA</small>
                            @else
                                <span class="meta">Not disclosed</span>
                            @endif
                        </div>
                        <div class="drive-due">
                            <span class="cap">Apply by</span>
                            @if ($drive->application_deadline)
                                <b>{{ $drive->application_deadline->format('d M Y') }}</b>
                                <span class="meta">{{ $drive->application_deadline->format('g:i A') }}@if ($left) <span class="due{{ $hours < 72 ? ' soon' : '' }}">{{ $left }}</span>@endif</span>
                            @else
                                <span class="meta">Deadline not set</span>
                            @endif
                            @if ($drive->status->value !== 'PUBLISHED')
                                <span class="badge b-{{ $drive->status->value === 'CLOSED' ? 'no' : 'wait' }}">{{ str_replace('_', ' ', $drive->status->value) }}</span>
                            @endif
                        </div>
                        <a href="{{ route('student.drives.show', $drive->id) }}" class="btn btn-secondary">View drive</a>
                    </li>
                @endforeach
            </ul>
        </section>
        <p class="meta" style="margin-top:12px">Eligibility and profile readiness are checked again when you submit an application.</p>
    @else
        <section class="card">
            <p class="empty">No published placement drives are available right now. Check again later or contact your T&amp;P coordinator if you think a drive should be visible.</p>
        </section>
    @endif
@endsection
