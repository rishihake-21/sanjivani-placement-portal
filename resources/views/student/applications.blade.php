@extends('layouts.student')

@section('title', 'My Applications')

@section('content')
    <div class="ph">
        <div>
            <h1>My Applications</h1>
            <p class="meta">Track the progress of your placement applications.</p>
        </div>
        <a href="{{ route('student.drives') }}" class="btn btn-primary">Browse drives</a>
    </div>

    <div class="stack">
        @if (request()->boolean('submitted'))
            <div class="notice ok" role="status">Your application was submitted. Check the status below for the latest recorded stage.</div>
        @endif

        @if ($applications->count())
            <section class="card">
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Role</th>
                                <th>Applied on</th>
                                <th>Current stage</th>
                                <th>Drive date</th>
                                <th><span class="sr">Details</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($applications as $app)
                                @php
                                    $stage = $app->stage->value;
                                    $stageLabel = str($stage)->lower()->replace('_', ' ')->title();
                                    $stageTone = match ($stage) {
                                        'APPLIED', 'APTITUDE', 'TECHNICAL', 'HR' => 'wait',
                                        'SHORTLISTED', 'SELECTED', 'OFFER_RECEIVED', 'PLACED' => 'ok',
                                        'REJECTED' => 'no',
                                        'WITHDRAWN' => 'neutral',
                                        default => 'neutral',
                                    };
                                @endphp
                                <tr>
                                    <td data-l="Company"><b>{{ $app->drive?->company?->name ?? 'Company to be announced' }}</b></td>
                                    <td data-l="Role">{{ $app->drive?->title ?? 'Placement drive' }}</td>
                                    <td data-l="Applied on">{{ $app->applied_at?->format('d M Y, g:i A') ?? 'Not recorded' }}</td>
                                    <td data-l="Current stage"><span class="badge b-{{ $stageTone }}">{{ $stageLabel }}</span></td>
                                    <td data-l="Drive date">{{ $app->drive?->drive_date?->format('d M Y') ?? 'To be announced' }}</td>
                                    <td class="act">
                                        <a href="#application-{{ $app->id }}" class="btn btn-secondary">View details</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            @foreach ($applications as $app)
                @php
                    $stage = $app->stage->value;
                    $stageLabel = str($stage)->lower()->replace('_', ' ')->title();
                @endphp
                <section class="card" id="application-{{ $app->id }}">
                    <div class="card-h">
                        <h2>{{ $app->drive?->company?->name ?? 'Company to be announced' }} — {{ $app->drive?->title ?? 'Placement drive' }}</h2>
                        <span class="badge b-neutral">Application #{{ $app->id }}</span>
                    </div>
                    <dl class="dl">
                        <div><dt>Submitted</dt><dd>{{ $app->applied_at?->format('d M Y, g:i A') ?? 'Not recorded' }}</dd></div>
                        <div><dt>Current stage</dt><dd>{{ $stageLabel }}</dd></div>
                        <div><dt>Last updated</dt><dd>{{ $app->stage_updated_at?->format('d M Y, g:i A') ?? 'Not recorded' }}</dd></div>
                        <div><dt>Application deadline</dt><dd>{{ $app->drive?->application_deadline?->format('d M Y, g:i A') ?? 'Not specified' }}</dd></div>
                    </dl>
                    <details style="margin-top:14px">
                        <summary style="cursor:pointer;font-weight:600">Application status history</summary>
                        <div class="list" style="margin-top:12px">
                            @forelse ($app->history as $event)
                                <div class="li">
                                    <div class="l">
                                        <div class="t">{{ str((string) $event->to_stage)->lower()->replace('_', ' ')->title() }}</div>
                                        @if ($event->remarks)
                                            <div class="meta">{{ $event->remarks }}</div>
                                        @endif
                                    </div>
                                    <div class="r"><span class="meta">{{ $event->created_at?->format('d M Y, g:i A') }}</span></div>
                                </div>
                            @empty
                                <p class="meta">No status history has been recorded yet.</p>
                            @endforelse
                        </div>
                    </details>
                </section>
            @endforeach
        @else
            <section class="card">
                <p class="empty">You haven't applied to any placement drives yet.</p>
                <p class="meta">When you submit an application, it will appear here with its current recruitment stage and status history.</p>
                <a href="{{ route('student.drives') }}" class="btn btn-primary">Explore placement drives</a>
            </section>
        @endif
    </div>
@endsection
