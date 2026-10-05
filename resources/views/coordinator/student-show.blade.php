{{--
  Student detail: read-only profile, readiness, verified values, every record, placement progress.
  Route: coordinator.students.show
  $student       GET /api/coordinator/students/{id} -> student, WITHOUT its three lists
  $records       that student's academic_records   (AcademicRecordResource arrays, superseded rows included)
  $experiences   that student's experiences        (ExperienceResource arrays)
  $documents     that student's documents          (DocumentResource arrays; add the numeric `id` to each row if
                 re-upload of documents should be offered, see partials/reupload-drawer)
  $completeness  completeness   (sections{key:{status,hint}}, can_apply, blockers)
  $verified      verified_academics (tenth_percentage, twelfth_percentage, diploma_percentage, cgpa, active_backlogs,
                 total_backlogs, is_complete, missing[])
  $progress      optional. GET /api/coordinator/students/{id}/progress -> {is_placed, applications[], placements[]}
  $reuploads     optional. This student's rows from GET /api/coordinator/reupload-requests
  Pending items can be reviewed here too (same drawer as the queue). Verified, locked academic records get an Unlock drawer.
--}}
@extends('layouts.coordinator')
@use('App\Support\StudentUi', 'U')
@section('title', $student['full_name'])

@section('content')
@php
  $who = ['id' => $student['id'], 'university_id' => $student['university_id'], 'full_name' => $student['full_name']];
  $order = ['TENTH' => 0, 'TWELFTH' => 1, 'DIPLOMA' => 2, 'DEGREE_SEM' => 3];

  $recById = [];
  foreach ($records as $r0) { $recById[$r0['id']] = $r0; }
  $expById = [];
  foreach ($experiences as $x0) { $expById[$x0['id']] = $x0; }
  $docById = [];
  foreach ($documents as $d0) { if (isset($d0['id'])) { $docById[$d0['id']] = $d0; } }

  $liveRecs = array_values(array_filter($records, fn ($r) => $r['status'] !== 'SUPERSEDED'));
  usort($liveRecs, fn ($a, $b) => [$order[$a['level']] ?? 9, (int) ($a['semester'] ?? 0)] <=> [$order[$b['level']] ?? 9, (int) ($b['semester'] ?? 0)]);
  $liveExps = array_values(array_filter($experiences, fn ($x) => $x['status'] !== 'SUPERSEDED'));
  $liveDocs = array_values(array_filter($documents, fn ($d) => $d['status'] !== 'SUPERSEDED'));
  $standalone = ['RESUME', 'CERT_OTHER'];

  // Everything that can be approved or rejected from this page, in the shape the review drawer expects.
  $reviews = [];
  foreach ($liveRecs as $r) {
      if ($r['status'] === 'PENDING') {
          $reviews[] = ['academic', $r + ['student' => $who, 'previous_verified' => ! empty($r['supersedes_id']) ? ($recById[$r['supersedes_id']] ?? null) : null]];
      }
  }
  foreach ($liveExps as $x) {
      if ($x['status'] === 'PENDING') {
          $reviews[] = ['experience', $x + ['student' => $who, 'previous_verified' => ! empty($x['supersedes_id']) ? ($expById[$x['supersedes_id']] ?? null) : null]];
      }
  }
  foreach ($liveDocs as $d) {
      if ($d['status'] === 'PENDING' && in_array($d['document_type'], $standalone, true)) {
          $reviews[] = ['document', $d + ['student' => $who]];
      }
  }

  $sections = U::sections($completeness);
  $done = U::sectionsDone($completeness);
  $canApply = ! empty($completeness['can_apply']);
  $secondLabel = $student['admission_type'] === 'LATERAL' ? 'Diploma' : '12th';
  $secondValue = $student['admission_type'] === 'LATERAL' ? ($verified['diploma_percentage'] ?? null) : ($verified['twelfth_percentage'] ?? null);
  $pct = fn ($v) => $v === null ? '–' : U::num2($v) . '%';
  $list = fn ($v) => empty($v) ? '–' : implode(', ', $v);
  $web = fn ($u) => preg_match('#^https?://#i', (string) $u) === 1;
  $locked = ! empty($student['profile_locked']);
  $subjectLabel = function ($rq) use ($recById, $expById, $docById) {
      $t = $rq['subject_type'];
      $id = $rq['subject_id'];
      if ($t === 'ACADEMIC_RECORD') { return 'Academic record' . (isset($recById[$id]) ? ': ' . $recById[$id]['label'] : ''); }
      if ($t === 'EXPERIENCE') { return 'Experience' . (isset($expById[$id]) ? ': ' . U::expType($expById[$id]['type']) . ' at ' . $expById[$id]['organization'] : ''); }
      return 'Document' . (isset($docById[$id]) ? ': ' . U::docType($docById[$id]['document_type']) : '');
  };
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>{{ $student['full_name'] }}</h1>
      <p class="meta">{{ $student['university_id'] }} &middot; {{ $student['branch']['name'] }} &middot; Semester {{ $student['current_semester'] }} &middot; {{ U::admission($student['admission_type']) }} &middot; Graduating {{ $student['graduation_year'] }}</p>
    </div>
    <div class="row wrap">
      <a class="btn btn-secondary" href="{{ route('coordinator.students') }}">Back to students</a>
      <button type="button" class="btn btn-primary" data-open="reupload">Request re-upload</button>
    </div>
  </div>

  <div class="stack">
    @if ($locked)
      @include('student.partials.notice', ['kind' => 'neutral', 'ic' => 'lock', 'text' => 'This profile is locked because the batch is completed. The student cannot edit it.'])
    @endif

    <section class="card" aria-labelledby="h-ready">
      <div class="card-h"><h2 id="h-ready">Application readiness</h2></div>
      <div class="ready">
        <p class="ready-state {{ $canApply ? 'ok' : 'no' }}">{{ $canApply ? 'Can apply to drives' : 'Cannot apply yet' }}</p>
        <div class="ready-bar">
          <div class="seg" role="img" aria-label="{{ $done }} of {{ count($sections) }} sections complete">
            @foreach ($sections as $s)<i class="{{ $s['status'] === 'COMPLETE' ? 'on' : '' }}"></i>@endforeach
          </div>
          <p class="meta num">{{ $done }} of {{ count($sections) }} sections complete</p>
        </div>
      </div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Section</th><th>Status</th><th>Note</th></tr></thead>
          <tbody>
            @foreach ($sections as $s)
              <tr>
                <td data-l="Section" class="nw"><span class="ltr">{{ $s['letter'] }}</span>{{ $s['name'] }}</td>
                <td data-l="Status">{!! U::badge('sec', $s['status']) !!}</td>
                <td data-l="Note">@if ($s['status'] === 'COMPLETE')<span class="meta">&ndash;</span>@else{{ $s['hint'] }}@endif</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    <section class="card" aria-labelledby="h-prof">
      <div class="card-h"><h2 id="h-prof">Profile</h2><span class="meta">Read only</span></div>
      <p class="sect-h">Identity</p>
      <dl class="dl">
        <div><dt>University ID</dt><dd>{{ $student['university_id'] }}</dd></div>
        <div><dt>Full name</dt><dd>{{ $student['full_name'] }}</dd></div>
        <div><dt>Department</dt><dd>{{ $student['department']['name'] }}</dd></div>
        <div><dt>Branch</dt><dd>{{ $student['branch']['name'] }}</dd></div>
        <div><dt>Admission type</dt><dd>{{ U::admission($student['admission_type']) }}</dd></div>
        <div><dt>Admission year</dt><dd>{{ $student['admission_year'] }}</dd></div>
        <div><dt>Graduation year</dt><dd>{{ $student['graduation_year'] }}</dd></div>
        <div><dt>Current semester</dt><dd>{{ $student['current_semester'] }}</dd></div>
        <div><dt>Date of birth</dt><dd>{{ ! empty($student['date_of_birth']) ? U::date($student['date_of_birth']) : '–' }}</dd></div>
        <div><dt>Gender</dt><dd>{{ ! empty($student['gender']) ? (U::GENDER[$student['gender']] ?? $student['gender']) : '–' }}</dd></div>
        <div><dt>Identity</dt><dd>{{ ! empty($student['identity_confirmed_at']) ? 'Confirmed ' . U::date($student['identity_confirmed_at']) : 'Not confirmed' }}</dd></div>
      </dl>

      <p class="sect-h">Contact</p>
      <dl class="dl">
        <div><dt>Personal email</dt><dd>{{ U::orDash($student['personal_email'] ?? null) }}</dd></div>
        <div><dt>Phone</dt><dd>{{ U::orDash($student['phone'] ?? null) }}</dd></div>
        <div><dt>Current city</dt><dd>{{ U::orDash($student['current_city'] ?? null) }}</dd></div>
        <div><dt>Permanent city</dt><dd>{{ U::orDash($student['permanent_city'] ?? null) }}</dd></div>
      </dl>

      <p class="sect-h">Skills and links</p>
      <dl class="dl">
        <div style="grid-column:1/-1"><dt>Skills</dt><dd>@if (empty($student['skills']))&ndash;@else @foreach ($student['skills'] as $sk)<span class="tag">{{ $sk }}</span> @endforeach @endif</dd></div>
        <div style="grid-column:1/-1"><dt>Languages</dt><dd>{{ $list($student['languages'] ?? []) }}</dd></div>
        @foreach ([['GitHub', 'github_url'], ['LinkedIn', 'linkedin_url'], ['Portfolio', 'portfolio_url']] as [$ln, $lk])
          <div><dt>{{ $ln }}</dt><dd>@if (! empty($student[$lk]) && $web($student[$lk]))<a class="linkbtn" href="{{ $student[$lk] }}" target="_blank" rel="noopener noreferrer">{{ $student[$lk] }}</a>@else{{ U::orDash($student[$lk] ?? null) }}@endif</dd></div>
        @endforeach
      </dl>

      <p class="sect-h">Preferences</p>
      <dl class="dl">
        <div style="grid-column:span 2"><dt>Preferred roles</dt><dd>{{ $list($student['preferred_roles'] ?? []) }}</dd></div>
        <div style="grid-column:span 2"><dt>Preferred locations</dt><dd>{{ $list($student['preferred_locations'] ?? []) }}</dd></div>
        <div><dt>Placement</dt><dd>{{ ! empty($student['opted_out_of_placement']) ? 'Opted out' : 'Participating' }}</dd></div>
        @if (! empty($student['opted_out_of_placement']))
          <div style="grid-column:span 3"><dt>Reason for opting out</dt><dd>{{ U::orDash($student['opt_out_reason'] ?? null) }}</dd></div>
        @endif
      </dl>
    </section>

    <section class="card" aria-labelledby="h-ver">
      <div class="card-h"><h2 id="h-ver">Verified values</h2><span class="meta">Only verified records count towards eligibility.</span></div>
      <div class="stat-grid">
        <div class="stat"><span class="cap">10th</span><div class="v">{{ $pct($verified['tenth_percentage'] ?? null) }}</div></div>
        <div class="stat"><span class="cap">{{ $secondLabel }}</span><div class="v">{{ $pct($secondValue) }}</div></div>
        <div class="stat"><span class="cap">CGPA</span><div class="v">{{ ($verified['cgpa'] ?? null) === null ? '–' : U::num2($verified['cgpa']) }}</div></div>
        <div class="stat"><span class="cap">Active backlogs</span><div class="v">{{ $verified['active_backlogs'] ?? '–' }}</div></div>
        <div class="stat"><span class="cap">Total backlogs</span><div class="v">{{ $verified['total_backlogs'] ?? '–' }}</div></div>
      </div>
      @if (empty($verified['is_complete']) && ! empty($verified['missing']))
        <div style="margin-top:16px">@include('student.partials.notice', ['kind' => 'wait', 'ic' => 'info', 'text' => 'Not verified yet: ' . implode(', ', $verified['missing'])])</div>
      @endif
    </section>

    <section class="card" aria-labelledby="h-acad">
      <div class="card-h"><h2 id="h-acad">Academic records</h2></div>
      @if (count($liveRecs))
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>Record</th><th>Status</th><th>Marks</th><th>Marksheet</th><th>Submitted</th><th><span class="sr">Action</span></th></tr></thead>
            <tbody>
              @foreach ($liveRecs as $rec)
                @php
                  $sub = $rec['level'] === 'DEGREE_SEM'
                      ? 'Backlogs in term ' . $rec['backlogs_in_term'] . ' · Active after term ' . $rec['active_backlogs_after_term']
                      : implode(' · ', array_filter([$rec['institution_name'] ?? null, $rec['passing_year'] ?? null]));
                  $mk = U::marks($rec);
                  $mf = $rec['document'] ?? null;
                  $isRev = $rec['status'] === 'PENDING' && ! empty($rec['supersedes_id']);
                @endphp
                <tr>
                  <td data-l="Record"><b>{{ $rec['label'] }}</b>@if ($sub)<span class="sub">{{ $sub }}</span>@endif</td>
                  <td data-l="Status"><div class="stk">{!! U::badge('rec', $rec['status']) !!}@if ($isRev)<span class="tag">Revision</span>@endif @if ($rec['status'] === 'VERIFIED' && ! empty($rec['locked']))<span class="tag">Locked</span>@endif @include('student.partials.rej-why', ['rej' => $rec['rejection'] ?? null])</div></td>
                  <td data-l="Marks">@if ($mk !== null)<span class="num">{{ $mk }}</span>@else<span class="meta">&ndash;</span>@endif</td>
                  <td data-l="Marksheet">@if ($mf)<a class="linkbtn" href="{{ route('coordinator.documents.download', $mf['uuid']) }}" target="_blank" rel="noopener">{{ $mf['original_name'] }}</a>@else<span class="meta">&ndash;</span>@endif</td>
                  <td data-l="Submitted" class="nw">{{ U::date($rec['submitted_at'] ?? null) ?: '–' }}</td>
                  <td class="act">
                    @if ($rec['status'] === 'PENDING')<button type="button" class="btn btn-primary" data-open="review-academic-{{ $rec['id'] }}">Review</button>@endif
                    @if ($rec['status'] === 'VERIFIED' && ! empty($rec['locked']))<button type="button" class="btn btn-secondary" data-open="unlock-{{ $rec['id'] }}">{!! U::icon('lock', 'sm') !!} Unlock</button>@endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="empty">No academic records yet.</p>
      @endif
    </section>

    <section class="card" aria-labelledby="h-exp">
      <div class="card-h"><h2 id="h-exp">Experience</h2></div>
      @if (count($liveExps))
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>Experience</th><th>Dates</th><th>Status</th><th>Certificate</th><th><span class="sr">Action</span></th></tr></thead>
            <tbody>
              @foreach ($liveExps as $ex)
                @php
                  $cf = $ex['certificate'] ?? null;
                  $exRev = $ex['status'] === 'PENDING' && ! empty($ex['supersedes_id']);
                @endphp
                <tr>
                  <td data-l="Experience"><b>{{ U::expType($ex['type']) }} at {{ $ex['organization'] }}</b><span class="sub">{{ U::orDash($ex['role_title'] ?? null) }}</span></td>
                  <td data-l="Dates" class="nw">{{ U::expDates($ex) }}</td>
                  <td data-l="Status"><div class="stk">{!! U::badge('rec', $ex['status']) !!}@if ($exRev)<span class="tag">Revision</span>@endif @include('student.partials.rej-why', ['rej' => $ex['rejection'] ?? null])</div></td>
                  <td data-l="Certificate">@if ($cf)<a class="linkbtn" href="{{ route('coordinator.documents.download', $cf['uuid']) }}" target="_blank" rel="noopener">{{ $cf['original_name'] }}</a>@else<span class="meta">&ndash;</span>@endif</td>
                  <td class="act">@if ($ex['status'] === 'PENDING')<button type="button" class="btn btn-primary" data-open="review-experience-{{ $ex['id'] }}">Review</button>@endif</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="empty">No experience added.</p>
      @endif
    </section>

    <section class="card" aria-labelledby="h-docs">
      <div class="card-h"><h2 id="h-docs">Documents</h2></div>
      @if (count($liveDocs))
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>Document</th><th>Version</th><th>Size</th><th>Status</th><th>Uploaded</th><th><span class="sr">Action</span></th></tr></thead>
            <tbody>
              @foreach ($liveDocs as $dc)
                <tr>
                  <td data-l="Document"><b>{{ U::docType($dc['document_type']) }}</b>@if (! empty($dc['is_primary'])) <span class="tag">Primary</span>@endif<span class="sub">{{ $dc['original_name'] }}</span></td>
                  <td data-l="Version" class="num">{{ $dc['version'] }}</td>
                  <td data-l="Size" class="num">{{ U::size($dc['size_bytes']) }}</td>
                  <td data-l="Status"><div class="stk">{!! U::badge('doc', $dc['status']) !!}@include('student.partials.rej-why', ['rej' => $dc['rejection'] ?? null])</div></td>
                  <td data-l="Uploaded" class="nw">{{ U::date($dc['uploaded_at']) }}</td>
                  <td class="act">
                    @if ($dc['status'] === 'PENDING' && in_array($dc['document_type'], $standalone, true))<button type="button" class="btn btn-primary" data-open="review-document-{{ $dc['uuid'] }}">Review</button>@endif
                    <a class="btn btn-secondary" href="{{ route('coordinator.documents.download', $dc['uuid']) }}" target="_blank" rel="noopener">{!! U::icon('down', 'sm') !!} Open</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="empty">No documents uploaded yet.</p>
      @endif
    </section>

    @if (isset($progress))
      <section class="card" aria-labelledby="h-prog">
        <div class="card-h">
          <h2 id="h-prog">Placement progress</h2>
          <span class="badge {{ ! empty($progress['is_placed']) ? 'b-ok' : 'b-neutral' }}">{{ ! empty($progress['is_placed']) ? 'Placed' : 'Not placed' }}</span>
        </div>

        <p class="sect-h">Applications</p>
        @if (count($progress['applications']))
          <div class="tbl-wrap">
            <table class="tbl">
              <thead><tr><th>Company</th><th>Drive</th><th>Stage</th><th>Applied</th><th>Last change</th></tr></thead>
              <tbody>
                @foreach ($progress['applications'] as $ap)
                  <tr>
                    <td data-l="Company"><b>{{ $ap['company'] }}</b></td>
                    <td data-l="Drive">{{ $ap['drive_title'] }}</td>
                    <td data-l="Stage">
                      @include('coordinator.partials.badge', ['kind' => 'stage', 'value' => $ap['stage']])
                      @if (! empty($ap['history']))
                        <ul class="trail">
                          @foreach ($ap['history'] as $h)
                            <li>{{ U::date($h['at']) }} &middot; @if (! empty($h['from']))@include('coordinator.partials.badge', ['kind' => 'stage', 'value' => $h['from']]) &rarr; @endif @include('coordinator.partials.badge', ['kind' => 'stage', 'value' => $h['to']])@if (! empty($h['by'])) &middot; {{ $h['by'] }}@endif @if (! empty($h['remarks']))<br>{{ $h['remarks'] }}@endif</li>
                          @endforeach
                        </ul>
                      @endif
                    </td>
                    <td data-l="Applied" class="nw">{{ U::date($ap['applied_at']) }}</td>
                    <td data-l="Last change" class="nw">{{ U::date($ap['stage_updated_at']) ?: '–' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <p class="empty">No applications yet.</p>
        @endif

        <p class="sect-h">Placements</p>
        @if (count($progress['placements']))
          <div class="tbl-wrap">
            <table class="tbl">
              <thead><tr><th>Company</th><th>Role</th><th>CTC</th><th>Location</th><th>Offer date</th><th>Joining date</th><th>Status</th></tr></thead>
              <tbody>
                @foreach ($progress['placements'] as $pl)
                  <tr>
                    <td data-l="Company"><b>{{ $pl['company'] }}</b></td>
                    <td data-l="Role">{{ U::orDash($pl['role_title'] ?? null) }}</td>
                    <td data-l="CTC" class="num nw">{{ ($pl['ctc_lpa'] ?? null) === null ? '–' : U::num2($pl['ctc_lpa']) . ' LPA' }}</td>
                    <td data-l="Location">{{ U::orDash($pl['location'] ?? null) }}</td>
                    <td data-l="Offer date" class="nw">{{ U::date($pl['offer_date'] ?? null) ?: '–' }}</td>
                    <td data-l="Joining date" class="nw">{{ U::date($pl['joining_date'] ?? null) ?: '–' }}</td>
                    <td data-l="Status">@include('coordinator.partials.badge', ['kind' => 'plc', 'value' => $pl['status']])</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <p class="empty">No offers yet.</p>
        @endif
      </section>
    @endif

    @if (isset($reuploads))
      <section class="card" aria-labelledby="h-ru">
        <div class="card-h"><h2 id="h-ru">Re-upload requests</h2></div>
        @if (count($reuploads))
          <div class="tbl-wrap">
            <table class="tbl">
              <thead><tr><th>Item</th><th>Reason</th><th>Status</th><th>Requested</th><th><span class="sr">Action</span></th></tr></thead>
              <tbody>
                @foreach ($reuploads as $rq)
                  <tr>
                    <td data-l="Item"><b>{{ $subjectLabel($rq) }}</b></td>
                    <td data-l="Reason">{{ $rq['reason'] }}</td>
                    <td data-l="Status">@include('coordinator.partials.badge', ['kind' => 'req', 'value' => $rq['status']])</td>
                    <td data-l="Requested" class="nw">{{ U::date($rq['created_at']) }}</td>
                    <td class="act">@if ($rq['status'] === 'OPEN')<button type="button" class="btn btn-secondary" data-post="{{ route('coordinator.x.reupload.cancel', $rq['id']) }}" data-done="Re-upload request cancelled.">Cancel request</button>@endif</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <p class="empty">No re-upload requests for this student.</p>
        @endif
      </section>
    @endif
  </div>
</div>

@foreach ($reviews as [$rk, $ri])
  @include('coordinator.partials.review-drawer', ['kind' => $rk, 'item' => $ri])
@endforeach
@foreach ($liveRecs as $ur)
  @if ($ur['status'] === 'VERIFIED' && ! empty($ur['locked']))
    @include('coordinator.partials.unlock-drawer', ['rec' => $ur])
  @endif
@endforeach
@include('coordinator.partials.reupload-drawer', ['student' => $student, 'records' => $records, 'experiences' => $experiences, 'documents' => $documents])
@endsection
