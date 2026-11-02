@extends('layouts.student')

@section('title', 'Drive Details')

@section('content')
    <div class="ph">
        <div>
            <a href="{{ route('student.drives') }}" class="linkbtn" style="margin-bottom:8px;display:inline-block">← Back to Drives</a>
            <h1>{{ $drive->company?->name ?? 'Company to be announced' }} — {{ $drive->title }}</h1>
            <p class="meta">{{ $drive->employment_type ? str_replace('_', ' ', $drive->employment_type) . ' · ' : '' }}Batch {{ $drive->graduation_year }}</p>
        </div>
        <span class="badge b-{{ $drive->status->value === 'PUBLISHED' ? 'ok' : ($drive->status->value === 'CLOSED' ? 'no' : 'wait') }}">{{ str_replace('_', ' ', $drive->status->value) }}</span>
    </div>

    <div class="stack">
        @if (session('status'))
            <div class="notice ok" role="status">{{ session('status') }}</div>
        @endif

        <section class="card">
            <div class="card-h"><h2>About this drive</h2></div>
            @if ($drive->description)
                <p style="color:var(--text);line-height:1.6;white-space:pre-line">{{ $drive->description }}</p>
            @else
                <p class="meta">No additional company description has been provided.</p>
            @endif
            @if ($drive->additional_requirements)
                <div style="margin-top:18px;padding-top:18px;border-top:1px solid var(--border-row)">
                    <h3 style="font:600 14px var(--font);margin:0 0 8px;color:var(--text-muted)">Additional requirements</h3>
                    <p style="color:var(--text);line-height:1.6;white-space:pre-line">{{ $drive->additional_requirements }}</p>
                </div>
            @endif
        </section>

        <div class="grid">
            <div class="c7">
                <section class="card">
                    <div class="card-h"><h2>Drive details</h2></div>
                    <dl class="dl">
                        <div><dt>Package</dt><dd>
                            @if ($drive->ctc_lpa !== null)
                                {{ number_format((float) $drive->ctc_lpa, 2) }}@if ($drive->ctc_max_lpa !== null && (float) $drive->ctc_max_lpa !== (float) $drive->ctc_lpa)–{{ number_format((float) $drive->ctc_max_lpa, 2) }}@endif LPA
                            @else
                                Not disclosed
                            @endif
                        </dd></div>
                        <div><dt>Employment type</dt><dd>{{ $drive->employment_type ? str_replace('_', ' ', $drive->employment_type) : 'Not specified' }}</dd></div>
                        <div><dt>Location</dt><dd>{{ $drive->location ?: 'Not specified' }}</dd></div>
                        <div><dt>Drive date</dt><dd>{{ $drive->drive_date?->format('d M Y') ?? 'To be announced' }}</dd></div>
                        <div><dt>Application deadline</dt><dd>{{ $drive->application_deadline?->format('d M Y, g:i A') ?? 'Not specified' }}</dd></div>
                        <div><dt>Eligible branches</dt><dd>{{ $drive->branches->pluck('name')->filter()->unique()->implode(', ') ?: 'Check with the T&P office' }}</dd></div>
                        <div><dt>Graduation year</dt><dd>{{ $drive->graduation_year }}</dd></div>
                    </dl>
                </section>

                <section class="card">
                    <div class="card-h"><h2>Eligibility criteria</h2></div>
                    @if ($drive->eligibility)
                        <div class="list">
                            @foreach ([
                                'Minimum CGPA' => [$drive->eligibility->min_cgpa, ''],
                                '10th percentage' => [$drive->eligibility->min_tenth_percentage, '%'],
                                '12th percentage' => [$drive->eligibility->min_twelfth_percentage, '%'],
                                'Diploma percentage' => [$drive->eligibility->min_diploma_percentage, '%'],
                                'Maximum active backlogs' => [$drive->eligibility->max_active_backlogs, ''],
                                'Maximum total backlogs' => [$drive->eligibility->max_total_backlogs, ''],
                            ] as $label => $criterion)
                                @if ($criterion[0] !== null)
                                    <div class="li"><div class="l"><div class="t">{{ $label }}</div></div><div class="r"><strong>{{ $criterion[0] }}{{ $criterion[1] }}</strong></div></div>
                                @endif
                            @endforeach
                            @if ($drive->eligibility->diploma_counts_as_twelfth)
                                <div class="li"><div class="l"><div class="t">Lateral-entry rule</div></div><div class="r">Diploma may count in place of 12th where applicable</div></div>
                            @endif
                        </div>
                    @else
                        <p class="meta">No structured academic thresholds have been configured for this drive. The application service will still validate the current eligibility rules.</p>
                    @endif
                </section>
            </div>

            <div class="c5">
                <section class="card">
                    <div class="card-h"><h2>Your application</h2></div>
                    @if ($hasApplied)
                        <div class="notice ok" style="margin-bottom:16px">
                            <strong>Application already submitted</strong>
                            <div class="meta" style="margin-top:4px">You have already applied to this drive. You can track it in My Applications.</div>
                        </div>
                        <a href="{{ route('student.applications') }}" class="btn btn-secondary" style="width:100%;display:flex;justify-content:center">View my applications</a>
                    @elseif ($drive->status->value !== 'PUBLISHED')
                        <div class="notice wait" style="margin-bottom:16px">This drive is not currently accepting applications.</div>
                        <button type="button" class="btn btn-secondary" disabled style="width:100%">Applications closed</button>
                    @elseif (!$drive->application_deadline || $drive->application_deadline->lte(now()))
                        <div class="notice no" style="margin-bottom:16px">The application deadline has passed or has not been configured.</div>
                        <button type="button" class="btn btn-secondary" disabled style="width:100%">Cannot apply</button>
                    @else
                        <div class="notice wait" style="margin-bottom:16px">
                            Eligibility is based on verified academic records. The server checks eligibility and profile readiness again when you submit.
                        </div>
                        <a href="{{ route('student.drives.apply', $drive->id) }}" class="btn btn-primary" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px">Review application →</a>
                    @endif
                </section>
            </div>
        </div>
    </div>
@endsection
