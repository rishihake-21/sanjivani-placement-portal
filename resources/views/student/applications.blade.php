@extends('layouts.student')

@section('title', 'My applications')

@section('content')
    <x-ui.page-header title="My applications" meta="Where each application stands in the recruitment process.">
        <a href="{{ route('student.drives') }}" class="btn btn-primary">Browse drives</a>
    </x-ui.page-header>

    @if (request()->boolean('submitted'))
        <div class="notice ok" role="status" style="margin-bottom:16px">Your application was submitted. Check the status below for the latest recorded stage.</div>
    @endif

    @if ($applications->count())
        @php
            $steps = ['APPLIED' => 'Applied', 'SHORTLISTED' => 'Shortlisted', 'APTITUDE' => 'Aptitude', 'TECHNICAL' => 'Technical', 'HR' => 'HR', 'SELECTED' => 'Selected', 'OFFER_RECEIVED' => 'Offer', 'PLACED' => 'Placed'];
            $keys = array_keys($steps);
            $pretty = fn ($s) => str((string) $s)->lower()->replace('_', ' ')->title();
        @endphp
        <div class="stack">
            @foreach ($applications as $app)
                @php
                    $stage = $app->stage->value;
                    $ended = in_array($stage, ['REJECTED', 'WITHDRAWN'], true);
                    // A rejected or withdrawn application stops somewhere on the track: show the furthest stage it reached.
                    $at = $ended
                        ? ($app->history->map(fn ($e) => array_search((string) $e->to_stage, $keys, true))->filter(fn ($i) => $i !== false)->max() ?? 0)
                        : (int) array_search($stage, $keys, true);
                    $tone = match ($stage) {
                        'SHORTLISTED', 'SELECTED', 'OFFER_RECEIVED', 'PLACED' => 'ok',
                        'REJECTED' => 'no',
                        'WITHDRAWN' => 'neutral',
                        default => 'wait',
                    };
                @endphp
                <section class="card" id="application-{{ $app->id }}">
                    <div class="card-h">
                        <div>
                            <h2>{{ $app->drive?->company?->name ?? 'Company to be announced' }}</h2>
                            <p class="meta" style="margin:2px 0 0">{{ $app->drive?->title ?? 'Placement drive' }}</p>
                        </div>
                        <div class="acts">
                            <span class="badge b-{{ $tone }}">{{ $pretty($stage) }}</span>
                            @if ($app->drive_id)<a class="btn btn-quiet btn-sm" href="{{ route('student.drives.show', $app->drive_id) }}">View drive</a>@endif
                        </div>
                    </div>

                    <ol class="track{{ $ended ? ' ended' : '' }}" aria-label="Application progress">
                        @foreach ($steps as $key => $label)
                            @php($i = array_search($key, $keys, true))
                            <li class="{{ $i < $at ? 'done' : ($i === $at ? 'now' : '') }}" @if ($i === $at) aria-current="step" @endif>{{ $label }}</li>
                        @endforeach
                    </ol>

                    <dl class="dl" style="margin-top:20px">
                        <div><dt>Submitted</dt><dd>{{ $app->applied_at?->format('d M Y, g:i A') ?? 'Not recorded' }}</dd></div>
                        <div><dt>Last updated</dt><dd>{{ $app->stage_updated_at?->format('d M Y, g:i A') ?? 'Not recorded' }}</dd></div>
                        <div><dt>Drive date</dt><dd>{{ $app->drive?->drive_date?->format('d M Y') ?? 'To be announced' }}</dd></div>
                        <div><dt>Application #</dt><dd>{{ $app->id }}</dd></div>
                    </dl>

                    <details class="history">
                        <summary>Status history</summary>
                        <div class="list">
                            @forelse ($app->history as $event)
                                <div class="li">
                                    <div class="l">
                                        <div class="t">{{ $pretty($event->to_stage) }}</div>
                                        @if ($event->remarks)<div class="meta">{{ $event->remarks }}</div>@endif
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
        </div>
    @else
        <section class="card">
            <p class="empty">You haven't applied to any placement drives yet.</p>
            <p class="meta">When you submit an application, it will appear here with its current recruitment stage and status history.</p>
            <a href="{{ route('student.drives') }}" class="btn btn-primary">Explore placement drives</a>
        </section>
    @endif
@endsection
