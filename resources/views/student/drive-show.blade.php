@extends('layouts.student')

@section('title', 'Drive Details - TPMS')

@section('content')
<div class="page">
    <div class="ph">
        <div>
            <a href="{{ route('student.drives') }}" class="linkbtn" style="margin-bottom:8px;display:inline-block"><svg class="icon sm" aria-hidden="true"><use href="#i-out"/></svg> Back to Drives</a>
            <h1>{{ $drive->company->name }} - {{ $drive->title }}</h1>
        </div>
        <span class="badge b-{{ $drive->status->value === 'PUBLISHED' ? 'ok' : ($drive->status->value === 'CLOSED' ? 'no' : 'wait') }}" style="margin-top:8px">{{ $drive->status->value }}</span>
    </div>
    
    <div class="stack">
        <section class="card">
            <div class="card-h"><h2>About this Drive</h2></div>
            <p class="meta">{{ $drive->description }}</p>
            
            @if ($drive->job_description)
                <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border-row)">
                    <h3 style="font:600 14px var(--font);margin:0 0 12px;color:var(--text-muted)">Job Description</h3>
                    <p style="color:var(--text);line-height:1.6">{{ $drive->job_description }}</p>
                </div>
            @endif
        </section>
        
        @if ($drive->eligibility_criteria)
            <section class="card">
                <div class="card-h"><h2>Eligibility Criteria</h2></div>
                <p style="color:var(--text);line-height:1.6">{{ $drive->eligibility_criteria }}</p>
            </section>
        @endif
        
        @if ($drive->selection_process)
            <section class="card">
                <div class="card-h"><h2>Selection Process</h2></div>
                <p style="color:var(--text);line-height:1.6">{{ $drive->selection_process }}</p>
            </section>
        @endif
        
        <div class="grid">
            <div class="c7">
                <section class="card">
                    <div class="card-h"><h2>Drive Details</h2></div>
                    <dl class="dl">
                        <div><dt>Package</dt><dd>{{ $drive->package_lpa }} LPA</dd></div>
                        <div><dt>Location</dt><dd>{{ $drive->location }}</dd></div>
                        <div><dt>Drive Date</dt><dd>{{ \Carbon\Carbon::parse($drive->drive_date)->format('d M Y') }}</dd></div>
                        <div><dt>Last Date to Apply</dt><dd>{{ \Carbon\Carbon::parse($drive->last_date_to_apply)->format('d M Y') }}</dd></div>
                        <div><dt>Department</dt><dd>{{ $drive->branches->pluck('department.name')->unique()->implode(', ') }}</dd></div>
                        <div><dt>Batch</dt><dd>{{ $drive->batch_year }}</dd></div>
                    </dl>
                </section>
            </div>
            <div class="c5">
                <section class="card">
                    <div class="card-h"><h2>Your Eligibility</h2></div>
                    @if ($isEligible)
                        <div class="notice ok" style="margin-bottom:16px">
                            <svg class="icon sm" aria-hidden="true"><use href="#i-check"/></svg>
                            <div>You meet the eligibility criteria for this drive.</div>
                        </div>
                    @else
                        <div class="notice no" style="margin-bottom:16px">
                            <svg class="icon sm" aria-hidden="true"><use href="#i-x"/></svg>
                            <div>You do not meet the eligibility criteria. Contact T&P Coordinator for details.</div>
                        </div>
                    @endif
                    
                    @if ($hasApplied)
                        <button type="button" class="btn btn-secondary" disabled style="width:100%">Already Applied</button>
                    @elseif ($isEligible && $drive->status->value === 'PUBLISHED')
                        <a href="{{ route('student.drives.apply', $drive->id) }}" class="btn btn-primary" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px"><svg class="icon sm" aria-hidden="true"><use href="#i-plus"/></svg> Apply Now</a>
                    @else
                        <button type="button" class="btn btn-secondary" disabled style="width:100%">Cannot Apply</button>
                    @endif
                </section>
            </div>
        </div>
    </div>
</div>
@endsection