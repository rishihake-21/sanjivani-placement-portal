@extends('layouts.student')

@section('title', 'Apply to Drive - TPMS')

@section('content')
<div class="app" id="app" data-page="drives.apply">
    <header class="topbar">
        <div class="tb-l">
            <button type="button" class="icon-btn" data-act="toggle-nav" aria-label="Collapse sidebar" aria-expanded="true">
                <svg class="icon" aria-hidden="true"><use href="#i-menu"/></svg>
            </button>
            <span class="wordmark">Sanjivani University &middot; TPMS</span>
        </div>
        <div class="tb-r">
            <button type="button" class="icon-btn" data-act="nav" data-page="notifications" aria-label="Notifications, 3 unread">
                <svg class="icon" aria-hidden="true"><use href="#i-bell"/></svg>
                <span class="count">3</span>
            </button>
            <div class="who">
                <b>{{ auth()->user()->name }}</b>
                <span>Student</span>
            </div>
        </div>
    </header>
    <div class="body">
        <nav class="sidebar" aria-label="Student navigation">
            <button type="button" class="nav-item" data-act="nav" data-page="dashboard" data-tip="Dashboard">
                <svg class="icon" aria-hidden="true"><use href="#i-dash"/></svg>
                <span class="nav-label">Dashboard</span>
            </button>
            <button type="button" class="nav-item" data-act="nav" data-page="profile" data-tip="Profile">
                <svg class="icon" aria-hidden="true"><use href="#i-user"/></svg>
                <span class="nav-label">Profile</span>
            </button>
            <button type="button" class="nav-item" data-act="nav" data-page="academic" data-tip="Academic records">
                <svg class="icon" aria-hidden="true"><use href="#i-book"/></svg>
                <span class="nav-label">Academic records</span>
            </button>
            <button type="button" class="nav-item" data-act="nav" data-page="experience" data-tip="Experience">
                <svg class="icon" aria-hidden="true"><use href="#i-brief"/></svg>
                <span class="nav-label">Experience</span>
            </button>
            <button type="button" class="nav-item" data-act="nav" data-page="documents" data-tip="Documents">
                <svg class="icon" aria-hidden="true"><use href="#i-file"/></svg>
                <span class="nav-label">Documents</span>
            </button>
            <button type="button" class="nav-item" data-act="nav" data-page="notifications" data-tip="Notifications">
                <svg class="icon" aria-hidden="true"><use href="#i-bell"/></svg>
                <span class="nav-label">Notifications</span>
                <span class="nav-count">3</span>
            </button>
            <div class="nav-gap"></div>
            <div class="nav-sep"></div>
            <button type="button" class="nav-item" data-act="signout" data-tip="Sign out">
                <svg class="icon" aria-hidden="true"><use href="#i-out"/></svg>
                <span class="nav-label">Sign out</span>
            </button>
        </nav>
        <div class="nav-scrim" data-act="close-overlay"></div>
        <main class="content" id="content" tabindex="-1">
            <div class="page">
                <div class="ph">
                    <div>
                        <a href="{{ route('student.drives.show', $drive) }}" class="linkbtn" style="margin-bottom:8px;display:inline-block"><svg class="icon sm" aria-hidden="true"><use href="#i-out"/></svg> Back to Drive</a>
                        <h1>Apply to {{ $drive->title }}</h1>
                        <p class="meta">{{ $drive->company->name }} &middot; {{ $drive->ctc_lpa }} LPA &middot; Apply by {{ \Carbon\Carbon::parse($drive->application_deadline)->format('d M Y') }}</p>
                    </div>
                </div>

                @if ($eligibility && !$eligibility->eligible)
                    <div class="notice no" style="margin-bottom:24px">
                        <svg class="icon sm" aria-hidden="true"><use href="#i-x"/></svg>
                        <div>
                            <strong>You are not eligible for this drive.</strong>
                            @foreach ($eligibility->reasons as $reason)
                                <div class="meta" style="margin-top:4px">• {{ $reason }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($hasApplied)
                    <div class="notice ok" style="margin-bottom:24px">
                        <svg class="icon sm" aria-hidden="true"><use href="#i-check"/></svg>
                        <div>You have already applied to this drive on {{ \Carbon\Carbon::parse($application->created_at)->format('d M Y') }}.</div>
                    </div>
                @else
                    <div class="stack">
                        <section class="card" aria-labelledby="h-eligibility">
                            <div class="card-h"><h2 id="h-eligibility">Eligibility check</h2></div>
                            <div class="grid">
                                <div class="c6">
                                    <h3 style="font:600 13px var(--font);color:var(--text-muted);margin:0 0 12px">Academic requirements</h3>
                                    <div class="list">
                                        <div class="li">
                                            <div class="l">
                                                <div class="t">Minimum CGPA</div>
                                            </div>
                                            <div class="r">
                                                @if ($eligibility && $eligibility->cgpa_ok)
                                                    <span class="badge b-ok">Met ({{ $student->verified_cgpa ?? 'N/A' }} ≥ {{ $drive->eligibility->min_cgpa }})</span>
                                                @else
                                                    <span class="badge b-no">Not met</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="li">
                                            <div class="l">
                                                <div class="t">10th Percentage</div>
                                            </div>
                                            <div class="r">
                                                @if ($eligibility && $eligibility->tenth_ok)
                                                    <span class="badge b-ok">Met ({{ $student->verified_tenth_percentage ?? 'N/A' }}% ≥ {{ $drive->eligibility->min_tenth_percentage }}%)</span>
                                                @else
                                                    <span class="badge b-no">Not met</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="li">
                                            <div class="l">
                                                <div class="t">12th Percentage</div>
                                            </div>
                                            <div class="r">
                                                @if ($eligibility && $eligibility->twelfth_ok)
                                                    <span class="badge b-ok">Met ({{ $student->verified_twelfth_percentage ?? 'N/A' }}% ≥ {{ $drive->eligibility->min_twelfth_percentage }}%)</span>
                                                @else
                                                    <span class="badge b-no">Not met</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="li">
                                            <div class="l">
                                                <div class="t">Active backlogs</div>
                                            </div>
                                            <div class="r">
                                                @if ($eligibility && $eligibility->backlogs_ok)
                                                    <span class="badge b-ok">Met ({{ $student->verified_active_backlogs ?? 0 }} ≤ {{ $drive->eligibility->max_active_backlogs }})</span>
                                                @else
                                                    <span class="badge b-no">Not met</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="li">
                                            <div class="l">
                                                <div class="t">Total backlogs</div>
                                            </div>
                                            <div class="r">
                                                @if ($eligibility && $eligibility->total_backlogs_ok)
                                                    <span class="badge b-ok">Met ({{ $student->verified_total_backlogs ?? 0 }} ≤ {{ $drive->eligibility->max_total_backlogs }})</span>
                                                @else
                                                    <span class="badge b-no">Not met</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="c6">
                                    <h3 style="font:600 13px var(--font);color:var(--text-muted);margin:0 0 12px">Branch eligibility</h3>
                                    <div class="list">
                                        @foreach ($drive->branches as $branch)
                                            <div class="li">
                                                <div class="l">
                                                    <div class="t">{{ $branch->code }} - {{ $branch->name }}</div>
                                                </div>
                                                <div class="r">
                                                    @if ($student->branch_id === $branch->id)
                                                        <span class="badge b-ok">Your branch</span>
                                                    @else
                                                        <span class="badge b-neutral">{{ $branch->department->code }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="card" aria-labelledby="h-profile">
                            <div class="card-h"><h2 id="h-profile">Your profile snapshot</h2></div>
                            <div class="grid">
                                <div class="c6">
                                    <h3 style="font:600 13px var(--font);color:var(--text-muted);margin:0 0 12px">Identity & Contact</h3>
                                    <dl class="dl">
                                        <div><dt>University ID</dt><dd>{{ $student->university_id }}</dd></div>
                                        <div><dt>Full name</dt><dd>{{ $student->full_name }}</dd></div>
                                        <div><dt>Email</dt><dd>{{ $student->user->email }}</dd></div>
                                        <div><dt>Phone</dt><dd>{{ $student->phone }}</dd></div>
                                        <div><dt>Current city</dt><dd>{{ $student->current_city }}</dd></div>
                                    </dl>
                                </div>
                                <div class="c6">
                                    <h3 style="font:600 13px var(--font);color:var(--text-muted);margin:0 0 12px">Verified academics</h3>
                                    <dl class="dl">
                                        <div><dt>10th</dt><dd>{{ $student->verified_tenth_percentage ?? 'Not verified' }}%</dd></div>
                                        <div><dt>12th</dt><dd>{{ $student->verified_twelfth_percentage ?? 'Not verified' }}%</dd></div>
                                        <div><dt>CGPA</dt><dd>{{ $student->verified_cgpa ?? 'Not verified' }}</dd></div>
                                        <div><dt>Active backlogs</dt><dd>{{ $student->verified_active_backlogs ?? 'Not verified' }}</dd></div>
                                        <div><dt>Total backlogs</dt><dd>{{ $student->verified_total_backlogs ?? 'Not verified' }}</dd></div>
                                    </dl>
                                </div>
                            </div>
                        </section>

                        <section class="card" aria-labelledby="h-resume">
                            <div class="card-h"><h2 id="h-resume">Resume</h2></div>
                            @if ($resume)
                                <div class="row wrap">
                                    <div class="l" style="flex:1">
                                        <div class="t">{{ $resume->original_name }}</div>
                                        <div class="meta">Version {{ $resume->version }} &middot; {{ \Carbon\Carbon::parse($resume->uploaded_at)->format('d M Y') }} &middot; Status: <span class="badge b-{{ $resume->status === 'APPROVED' ? 'ok' : ($resume->status === 'PENDING' ? 'wait' : 'no') }}">{{ $resume->status }}</span></div>
                                    </div>
                                    <div class="r">
                                        <button type="button" class="btn btn-secondary" data-act="download" data-uuid="{{ $resume->uuid }}"><svg class="icon sm" aria-hidden="true"><use href="#i-down"/></svg> View</button>
                                    </div>
                                </div>
                                @if ($resume->status !== 'APPROVED')
                                    <div class="notice wait" style="margin-top:12px">
                                        <svg class="icon sm" aria-hidden="true"><use href="#i-info"/></svg>
                                        <div>Your current resume is <strong>{{ strtolower($resume->status) }}</strong>. It will be shared with the company upon application. Only the latest approved resume will be shared.</div>
                                    </div>
                                @endif
                            @else
                                <div class="notice no">
                                    <svg class="icon sm" aria-hidden="true"><use href="#i-x"/></svg>
                                    <div>No resume uploaded. <a href="{{ route('student.documents') }}#sec-upload" class="linkbtn">Upload a resume</a> before applying.</div>
                                </div>
                            @endif
                        </section>

                        <section class="card" aria-labelledby="h-declaration">
                            <div class="card-h"><h2 id="h-declaration">Declaration</h2></div>
                            <div class="field">
                                <label class="chk">
                                    <input type="checkbox" id="confirm-eligibility" data-act="confirm-eligibility"> I confirm that I meet all eligibility criteria for this drive.
                                </label>
                            </div>
                            <div class="field">
                                <label class="chk">
                                    <input type="checkbox" id="confirm-accuracy" data-act="confirm-accuracy"> I declare that all information in my profile is accurate and up to date.
                                </label>
                            </div>
                            <div class="field">
                                <label class="chk">
                                    <input type="checkbox" id="consent-sharing" data-act="consent-sharing"> I consent to sharing my verified profile (academics, resume, experience) with {{ $drive->company->name }} for this recruitment process.
                                </label>
                            </div>
                        </section>

                        <div class="row wrap" style="justify-content:flex-end;margin-top:16px">
                            <button type="button" class="btn btn-secondary" data-act="nav" data-page="drives">Cancel</button>
                            <button type="button" class="btn btn-primary" id="btn-submit-application" disabled><svg class="icon sm" aria-hidden="true"><use href="#i-check"/></svg> Submit Application</button>
                        </div>
                    </div>
                @endif
            </div>
        </main>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Drive Apply page JavaScript
(function() {
    'use strict';

    const $ = (s, r) => (r || document).querySelector(s);
    const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));

    // Navigation
    $$('[data-act="nav"], [data-act="goto"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const page = btn.dataset.page;
            const anchor = btn.dataset.anchor;
            const routes = {
                'dashboard': '{{ route('student.dashboard') }}',
                'profile': '{{ route('student.profile') }}',
                'academic': '{{ route('student.academic') }}',
                'experience': '{{ route('student.experience') }}',
                'documents': '{{ route('student.documents') }}',
                'notifications': '{{ route('student.notifications') }}',
                'drives': '{{ route('student.drives') }}',
            };
            if (routes[page]) {
                let url = routes[page];
                if (anchor) url += '#' + anchor;
                window.location.href = url;
            }
        });
    });

    // Download document
    $$('[data-act="download"]').forEach(btn => {
        btn.addEventListener('click', () => {
            alert('Download: ' + btn.dataset.uuid);
        });
    });

    // Declaration checkboxes
    const checkboxes = $$('#confirm-eligibility, #confirm-accuracy, #consent-sharing');
    const submitBtn = $('#btn-submit-application');

    function updateSubmitBtn() {
        const allChecked = checkboxes.every(cb => cb.checked);
        submitBtn.disabled = !allChecked;
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSubmitBtn);
    });

    // Submit application
    if (submitBtn) {
        submitBtn.addEventListener('click', () => {
            if (!submitBtn.disabled) {
                alert('Application submitted successfully!');
                // In real app: POST to /student/applications with drive_id
                window.location.href = '{{ route('student.applications') }}';
            }
        });
    }

    // Sidebar toggle
    $('[data-act="toggle-nav"]').addEventListener('click', () => {
        $('#app').classList.toggle('collapsed');
    });

    // Sign out
    $('[data-act="signout"]').addEventListener('click', () => {
        if (confirm('Sign out?')) window.location.href = '{{ route('logout') }}';
    });
})();
</script>
@endpush