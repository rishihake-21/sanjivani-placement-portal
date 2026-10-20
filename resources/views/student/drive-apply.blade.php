@extends('layouts.student')

@section('title', 'Apply to Drive - TPMS')

@section('content')
<div class="page">
    <div class="ph">
        <div>
            <a href="{{ route('student.drives.show', $drive) }}" class="linkbtn" style="margin-bottom:8px;display:inline-block">← Back to drive</a>
            <h1>Review application</h1>
            <p class="meta">{{ $drive->company?->name ?? 'Company to be announced' }} · {{ $drive->title }}</p>
        </div>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card-h"><h2>Before you submit</h2></div>
            <dl class="dl">
                <div><dt>Package</dt><dd>
                    @if ($drive->ctc_lpa !== null)
                        {{ number_format((float) $drive->ctc_lpa, 2) }}@if ($drive->ctc_max_lpa !== null && (float) $drive->ctc_max_lpa !== (float) $drive->ctc_lpa)–{{ number_format((float) $drive->ctc_max_lpa, 2) }}@endif LPA
                    @else
                        Not disclosed
                    @endif
                </dd></div>
                <div><dt>Application deadline</dt><dd>{{ $drive->application_deadline?->format('d M Y, g:i A') ?? 'Not specified' }}</dd></div>
                <div><dt>Drive date</dt><dd>{{ $drive->drive_date?->format('d M Y') ?? 'To be announced' }}</dd></div>
            </dl>
        </section>

        @if ($hasApplied)
            <section class="card">
                <div class="notice ok">
                    <strong>Already applied</strong>
                    <p class="meta">Your application was already submitted{{ $application?->applied_at ? ' on ' . $application->applied_at->format('d M Y, g:i A') : '' }}.</p>
                </div>
                <a href="{{ route('student.applications') }}" class="btn btn-primary" style="margin-top:12px">View my applications</a>
            </section>
        @else
            @php($uiCanSubmit = $eligibility?->isEligible() && $resume && $resume->status === 'APPROVED' && $drive->status->value === 'PUBLISHED' && $drive->application_deadline && $drive->application_deadline->gt(now()))
            <section class="card">
                <div class="card-h"><h2>Eligibility review</h2></div>
                <p class="meta">Eligibility is evaluated using verified academic information. Pending verification is different from failing a criterion.</p>
                @if ($eligibility)
                    <div class="notice {{ $eligibility->status === \App\Support\EligibilityResult::ELIGIBLE ? 'ok' : ($eligibility->status === \App\Support\EligibilityResult::PENDING ? 'wait' : 'no') }}" style="margin-bottom:16px">
                        <strong>
                            @if ($eligibility->status === \App\Support\EligibilityResult::ELIGIBLE)
                                Eligible based on currently verified records
                            @elseif ($eligibility->status === \App\Support\EligibilityResult::PENDING)
                                Verification pending
                            @else
                                One or more criteria are not met
                            @endif
                        </strong>
                    </div>
                    <div class="list">
                        @foreach ($eligibility->checks as $check)
                            <div class="li">
                                <div class="l">
                                    <div class="t">{{ $check['label'] }}</div>
                                    @if ($check['message'])
                                        <div class="meta">{{ $check['message'] }}</div>
                                    @elseif ($check['actual'] !== null)
                                        <div class="meta">Your verified value: {{ $check['actual'] }} · Required: {{ $check['required'] ?? 'As specified' }}</div>
                                    @endif
                                </div>
                                <div class="r">
                                    <span class="badge b-{{ $check['result'] === 'PASS' ? 'ok' : ($check['result'] === 'PENDING' ? 'wait' : 'no') }}">{{ $check['result'] === 'PASS' ? 'Met' : ($check['result'] === 'PENDING' ? 'Pending' : ($check['result'] === 'NA' ? 'Not applicable' : 'Not met')) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card">
                <div class="card-h"><h2>Resume</h2></div>
                @if ($resume)
                    <div class="row wrap" style="align-items:center;gap:12px">
                        <div style="flex:1;min-width:180px">
                            <strong>{{ $resume->original_name }}</strong>
                            <p class="meta" style="margin:5px 0 0">Version {{ $resume->version }} · {{ $resume->status }}</p>
                        </div>
                        <span class="badge b-{{ $resume->status === 'APPROVED' ? 'ok' : ($resume->status === 'PENDING' ? 'wait' : 'no') }}">{{ $resume->status }}</span>
                    </div>
                    @if ($resume->status === 'APPROVED')
                        <p class="meta" style="margin-top:12px">This is your current primary resume. The server will verify that the required resume is approved before accepting the application.</p>
                    @else
                        <div class="notice wait" style="margin-top:12px">Your primary resume is not approved yet. You can review the page, but submission will be blocked until your profile meets the requirements.</div>
                    @endif
                    <a href="{{ route('student.documents') }}" class="linkbtn" style="display:inline-block;margin-top:10px">Manage documents</a>
                @else
                    <div class="notice no">No primary resume found. Upload a resume and wait for approval before applying.</div>
                    <a href="{{ route('student.documents') }}#sec-upload" class="btn btn-secondary" style="margin-top:12px">Upload resume</a>
                @endif
            </section>

            <section class="card">
                <div class="card-h"><h2>Declarations</h2></div>
                <p class="meta">Confirm these statements before continuing. The application endpoint remains authoritative for eligibility and profile readiness.</p>
                <div class="field"><label class="chk"><input type="checkbox" id="confirm-eligibility"> I confirm that I have reviewed the eligibility criteria.</label></div>
                <div class="field"><label class="chk"><input type="checkbox" id="confirm-accuracy"> I confirm that my profile information is accurate to the best of my knowledge.</label></div>
                <div class="field"><label class="chk"><input type="checkbox" id="consent-sharing"> I consent to sharing my verified profile and approved resume with {{ $drive->company?->name ?? 'the recruiting company' }} for this recruitment process.</label></div>
                <p class="meta">These checkboxes control this page's submit button; declaration persistence is not implemented by this UI-only change.</p>
            </section>

            <section class="card">
                <div id="application-feedback" class="notice" role="status" aria-live="polite" hidden></div>
                @unless ($uiCanSubmit)
                    <div class="notice wait" style="margin-bottom:14px">Submission is not available yet. Confirm eligibility, wait for your primary resume to be approved, and check that the drive is still accepting applications. Your profile may have additional readiness requirements.</div>
                @endunless
                <div class="row wrap" style="justify-content:flex-end;gap:10px">
                    <a href="{{ route('student.drives.show', $drive) }}" class="btn btn-secondary">Cancel</a>
                    <button type="button" class="btn btn-primary" id="btn-submit-application" @disabled(!$uiCanSubmit)>Submit application</button>
                </div>
            </section>
        @endif
    </div>
</div>

@if (!$hasApplied)
<script>
(function () {
    'use strict';
    const checkboxes = [
        document.getElementById('confirm-eligibility'),
        document.getElementById('confirm-accuracy'),
        document.getElementById('consent-sharing')
    ];
    const submitButton = document.getElementById('btn-submit-application');
    const feedback = document.getElementById('application-feedback');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const uiCanSubmit = @json((bool) $uiCanSubmit);

    function updateButton() {
        if (!submitButton) return;
        submitButton.disabled = !uiCanSubmit || !checkboxes.every((box) => box && box.checked) || submitButton.dataset.loading === 'true';
    }

    function showFeedback(message, type) {
        feedback.hidden = false;
        feedback.className = 'notice ' + type;
        feedback.textContent = message;
    }

    checkboxes.forEach((box) => box?.addEventListener('change', updateButton));

    submitButton?.addEventListener('click', async function () {
        if (submitButton.disabled) return;
        submitButton.dataset.loading = 'true';
        submitButton.textContent = 'Submitting…';
        submitButton.disabled = true;
        feedback.hidden = true;

        try {
            const response = await fetch(@json(url('/api/student/drives/' . $drive->id . '/apply')), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrf ? {'X-CSRF-TOKEN': csrf} : {})
                },
                body: JSON.stringify({})
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = payload.errors ? Object.values(payload.errors).flat() : [];
                const message = errors.length ? errors.join(' ') : (payload.message || 'We could not submit your application. Please try again or contact the T&P office.');
                showFeedback(message, response.status === 409 ? 'wait' : 'no');
                return;
            }

            showFeedback('Application submitted successfully. Your application reference is #' + (payload.data?.id ?? 'created') + '.', 'ok');
            submitButton.textContent = 'Application submitted';
            const applicationsLink = document.createElement('a');
            applicationsLink.href = @json(route('student.applications'));
            applicationsLink.className = 'btn btn-secondary';
            applicationsLink.style.marginTop = '12px';
            applicationsLink.textContent = 'View my applications';
            feedback.appendChild(document.createElement('br'));
            feedback.appendChild(applicationsLink);
        } catch (error) {
            showFeedback('A network error prevented submission. Please check your connection and try again.', 'no');
        } finally {
            if (submitButton.dataset.loading === 'true' && submitButton.textContent !== 'Application submitted') {
                submitButton.dataset.loading = 'false';
                submitButton.textContent = 'Submit application';
                updateButton();
            }
        }
    });
})();
</script>
@endif
@endsection
