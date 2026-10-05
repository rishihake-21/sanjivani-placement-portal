@extends('layouts.coordinator')

@section('title', 'Student')
@section('nav', 'students')

{{-- Controller passes: $studentId, $department. Data: GET /api/coordinator/students/{id} and /students/{id}/progress. --}}
@section('content')
  <x-ui.page-header title="Student" meta="&nbsp;">
    <a class="btn btn-secondary" href="{{ route('coordinator.students') }}">All students</a>
    <button type="button" class="btn btn-primary" id="btn-reupload" disabled>Request re-upload</button>
  </x-ui.page-header>

  <div id="lock-note"></div>

  <div class="stack">
    <x-ui.card id="ready" title="Application readiness">
      <div id="ready-body"><div class="skel"></div><div class="skel" style="width:70%"></div></div>
    </x-ui.card>

    <x-ui.card id="profile" title="Profile">
      <x-slot:actions><span class="meta">Read only</span></x-slot:actions>
      <div id="profile-body"><div class="skel"></div><div class="skel" style="width:70%"></div></div>
    </x-ui.card>

    <x-ui.card id="verified" title="Verified values">
      <x-slot:actions><span class="meta">Only verified records count towards eligibility.</span></x-slot:actions>
      <div class="stat-grid">
        <x-ui.stat id="v-tenth" label="10th" />
        <x-ui.stat id="v-second" label="12th" />
        <x-ui.stat id="v-cgpa" label="CGPA" />
        <x-ui.stat id="v-active" label="Active backlogs" />
        <x-ui.stat id="v-total" label="Total backlogs" />
      </div>
      <div id="verified-note" style="margin-top:16px"></div>
    </x-ui.card>

    <x-ui.card id="academic" title="Academic records">
      <x-ui.table id="acad-rows" :cols="['Record', 'Status', 'Marks', 'Marksheet', 'Submitted', '']" />
    </x-ui.card>

    <x-ui.card id="experience" title="Experience">
      <x-ui.table id="exp-rows" :cols="['Experience', 'Dates', 'Status', 'Certificate', '']" />
    </x-ui.card>

    <x-ui.card id="documents" title="Documents">
      <x-ui.table id="doc-rows" :cols="['Document', 'Version', 'Size', 'Status', 'Uploaded', '']" />
    </x-ui.card>

    <x-ui.card id="progress" title="Placement progress">
      <div id="progress-body"><div class="skel"></div><div class="skel" style="width:70%"></div></div>
    </x-ui.card>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fillRows, notice, fail, fmtDate, fmtDT, num2, lpa, badge, dash } = TPMS;
    const C = TPMS.C;
    const id = @json($studentId);
    const drivesUrl = @json(route('coordinator.drives'));
    const ORDER = { TENTH: 0, TWELFTH: 1, DIPLOMA: 2, DEGREE_SEM: 3 };
    let d = null;

    const val = (v) => (v == null || v === '' ? dash : esc(v));
    const list = (a) => (a && a.length ? esc(a.join(', ')) : dash);
    const web = (u) => /^https?:\/\//i.test(u || '');
    const dl = (pairs) => '<dl class="dl">' + pairs.map(([l, v, wide]) => `<div${wide ? ' style="grid-column:1/-1"' : ''}><dt>${esc(l)}</dt><dd>${v}</dd></div>`).join('') + '</dl>';
    const head = (t) => `<p class="cap" style="margin:24px 0 8px">${esc(t)}</p>`;

    async function load() {
      try { d = await api('/coordinator/students/' + id); } catch (e) { fail(e); return; }
      render();
      loadProgress();
    }

    function render() {
      const s = d.student, comp = d.completeness || {}, ver = d.verified_academics || {};
      document.title = s.full_name + ' · TPMS';
      $('.ph h1').textContent = s.full_name;
      $('.ph .meta').innerHTML = `${esc(s.university_id)} &middot; ${esc(s.branch ? s.branch.name : '')} &middot; Semester ${esc(s.current_semester)} &middot; ${esc(C.ADMISSION[s.admission_type] || s.admission_type)} &middot; Graduating ${esc(s.graduation_year)}`;
      $('#btn-reupload').disabled = false;
      $('#lock-note').innerHTML = s.profile_locked ? '<div style="margin-bottom:24px">' + notice('neutral', 'lock', 'This profile is locked because the batch is completed. The student cannot edit it.') + '</div>' : '';

      /* readiness */
      const secs = C.SECTIONS.map(([k, letter, name]) => Object.assign({ letter, name }, (comp.sections || {})[k] || { status: 'INCOMPLETE', hint: '' }));
      const done = secs.filter((x) => x.status === 'COMPLETE').length;
      $('#ready-body').innerHTML = `
        <div class="ready"><p class="ready-state ${comp.can_apply ? 'ok' : 'no'}">${comp.can_apply ? 'Can apply to drives' : 'Cannot apply yet'}</p>
          <div class="ready-bar"><div class="seg" role="img" aria-label="${done} of ${secs.length} sections complete">${secs.map((x) => `<i class="${x.status === 'COMPLETE' ? 'on' : ''}"></i>`).join('')}</div>
          <p class="meta num">${done} of ${secs.length} sections complete</p></div></div>
        <div class="tbl-wrap" style="margin-top:16px"><table class="tbl"><thead><tr><th>Section</th><th>Status</th><th>Note</th></tr></thead><tbody>
          ${secs.map((x) => `<tr><td data-l="Section" class="nw"><span class="ltr">${x.letter}</span>${esc(x.name)}</td><td data-l="Status">${C.badge('sec', x.status)}</td><td data-l="Note">${x.status === 'COMPLETE' ? dash : esc(x.hint || '')}</td></tr>`).join('')}
        </tbody></table></div>`;

      /* profile (read only) */
      const link = (u) => (u && web(u) ? `<a class="linkbtn" href="${esc(u)}" target="_blank" rel="noopener noreferrer">${esc(u)}</a>` : val(u));
      $('#profile-body').innerHTML =
        head('Identity') + dl([
          ['University ID', val(s.university_id)], ['Full name', val(s.full_name)], ['Department', val(s.department && s.department.name)], ['Branch', val(s.branch && s.branch.name)],
          ['Admission type', val(C.ADMISSION[s.admission_type] || s.admission_type)], ['Admission year', val(s.admission_year)], ['Graduation year', val(s.graduation_year)], ['Current semester', val(s.current_semester)],
          ['Date of birth', s.date_of_birth ? fmtDate(s.date_of_birth) : dash], ['Gender', val(C.GENDER[s.gender] || s.gender)],
          ['Identity', s.identity_confirmed_at ? 'Confirmed ' + fmtDate(s.identity_confirmed_at) : 'Not confirmed'],
        ])
        + head('Contact') + dl([['Personal email', val(s.personal_email)], ['Phone', val(s.phone)], ['Current city', val(s.current_city)], ['Permanent city', val(s.permanent_city)]])
        + head('Skills and links') + dl([
          ['Skills', (s.skills || []).length ? s.skills.map((x) => `<span class="tag">${esc(x)}</span>`).join(' ') : dash, true],
          ['Languages', list(s.languages), true], ['GitHub', link(s.github_url)], ['LinkedIn', link(s.linkedin_url)], ['Portfolio', link(s.portfolio_url)],
        ])
        + head('Preferences') + dl([
          ['Preferred roles', list(s.preferred_roles), true], ['Preferred locations', list(s.preferred_locations), true],
          ['Placement', s.opted_out_of_placement ? 'Opted out' : 'Participating'],
          ...(s.opted_out_of_placement ? [['Reason for opting out', val(s.opt_out_reason)]] : []),
        ]);

      /* verified values */
      const pct = (x) => (x == null ? '&ndash;' : `<span class="num">${num2(x)}%</span>`);
      const lateral = s.admission_type === 'LATERAL';
      $('#v-second').closest('.stat').querySelector('.cap').textContent = lateral ? 'Diploma' : '12th';
      $('#v-tenth').innerHTML = pct(ver.tenth_percentage);
      $('#v-second').innerHTML = pct(lateral ? ver.diploma_percentage : ver.twelfth_percentage);
      $('#v-cgpa').innerHTML = ver.cgpa == null ? '&ndash;' : `<span class="num">${num2(ver.cgpa)}</span>`;
      $('#v-active').innerHTML = ver.active_backlogs == null ? '&ndash;' : `<span class="num">${esc(ver.active_backlogs)}</span>`;
      $('#v-total').innerHTML = ver.total_backlogs == null ? '&ndash;' : `<span class="num">${esc(ver.total_backlogs)}</span>`;
      $('#verified-note').innerHTML = !ver.is_complete && (ver.missing || []).length ? notice('wait', 'info', 'Not verified yet: ' + esc(ver.missing.join(', '))) : '';

      renderRecords();
    }

    /* academic records, experience, documents */
    function renderRecords() {
      const s = d.student;
      const recs = (s.academic_records || []).filter((r) => r.status !== 'SUPERSEDED')
        .sort((a, b) => ((ORDER[a.level] ?? 9) - (ORDER[b.level] ?? 9)) || ((a.semester || 0) - (b.semester || 0)));
      const exps = (s.experiences || []).filter((x) => x.status !== 'SUPERSEDED');
      const docs = (s.documents || []).filter((x) => x.status !== 'SUPERSEDED');
      const byId = (arr) => Object.fromEntries(arr.map((x) => [x.id, x]));
      const allRecs = byId(s.academic_records || []), allExps = byId(s.experiences || []);
      const who = { id: s.id, university_id: s.university_id, full_name: s.full_name };

      fillRows($('#acad-rows'), recs, (r) => {
        const sub = r.level === 'DEGREE_SEM' ? `Backlogs in term ${r.backlogs_in_term} &middot; Active after term ${r.active_backlogs_after_term}` : [r.institution_name, r.passing_year].filter(Boolean).map(esc).join(' &middot; ');
        const mk = C.marks(r);
        const rev = r.status === 'PENDING' && r.supersedes_id;
        const act = (r.status === 'PENDING' ? `<button type="button" class="btn btn-primary" data-review="academic:${r.id}">Review</button>` : '')
          + (r.status === 'VERIFIED' && r.locked ? ` <button type="button" class="btn btn-secondary" data-unlock="${r.id}">${TPMS.icon('lock', 'sm')} Unlock</button>` : '');
        return [
          `<span class="name">${esc(r.label)}</span>${sub ? `<span class="sub">${sub}</span>` : ''}`,
          `<div class="stk">${C.badge('rec', r.status)}${rev ? '<span class="tag">Revision</span>' : ''}${r.status === 'VERIFIED' && r.locked ? '<span class="tag">Locked</span>' : ''}${C.why(r.rejection)}</div>`,
          mk ? `<span class="num">${esc(mk)}</span>` : dash, C.fileLink(r.document),
          { html: r.submitted_at ? fmtDate(r.submitted_at) : dash, cls: 'nw' }, { html: act, cls: 'act' },
        ];
      }, 'No academic records yet.');

      fillRows($('#exp-rows'), exps, (x) => {
        const rev = x.status === 'PENDING' && x.supersedes_id;
        return [
          `<span class="name">${esc(C.expType(x.type))} at ${esc(x.organization)}</span><span class="sub">${esc(x.role_title || '')}</span>`,
          { html: esc(C.expDates(x)), cls: 'nw' },
          `<div class="stk">${C.badge('rec', x.status)}${rev ? '<span class="tag">Revision</span>' : ''}${C.why(x.rejection)}</div>`,
          C.fileLink(x.certificate),
          { html: x.status === 'PENDING' ? `<button type="button" class="btn btn-primary" data-review="experience:${x.id}">Review</button>` : '', cls: 'act' },
        ];
      }, 'No experience added.');

      fillRows($('#doc-rows'), docs, (x) => [
        `<span class="name">${esc(C.docType(x.document_type))}</span>${x.is_primary ? ' <span class="tag">Primary</span>' : ''}<span class="sub">${esc(x.original_name)}</span>`,
        `<span class="num">${esc(x.version)}</span>`, `<span class="num">${esc(C.size(x.size_bytes))}</span>`,
        `<div class="stk">${C.badge('doc', x.status)}${C.why(x.rejection)}</div>`,
        { html: fmtDate(x.uploaded_at), cls: 'nw' },
        { html: (x.status === 'PENDING' && C.STANDALONE.includes(x.document_type) ? `<button type="button" class="btn btn-primary" data-review="document:${x.uuid}">Review</button> ` : '')
          + `<a class="btn btn-secondary" href="${esc(C.fileUrl(x))}" target="_blank" rel="noopener">${TPMS.icon('down', 'sm')} Open</a>`, cls: 'act' },
      ], 'No documents uploaded yet.');

      /* review / unlock / re-upload */
      const open = (kind, item) => C.reviewDrawer({ kind, item, onDone: load });
      $('#content').onclick = (e) => {
        const r = e.target.closest('[data-review]');
        if (r) {
          const [kind, key] = r.dataset.review.split(':');
          if (kind === 'academic') { const it = allRecs[key]; open(kind, Object.assign({}, it, { student: who, previous_verified: it.supersedes_id ? (allRecs[it.supersedes_id] || null) : null })); }
          else if (kind === 'experience') { const it = allExps[key]; open(kind, Object.assign({}, it, { student: who, previous_verified: it.supersedes_id ? (allExps[it.supersedes_id] || null) : null })); }
          else { const it = docs.find((x) => x.uuid === key); open(kind, Object.assign({}, it, { student: who })); }
          return;
        }
        const u = e.target.closest('[data-unlock]');
        if (u) C.unlockDrawer(allRecs[u.dataset.unlock], load);
      };
      $('#btn-reupload').onclick = () => C.reuploadDrawer({ student: s, records: s.academic_records, experiences: s.experiences, documents: s.documents }, load);
    }

    /* placement progress */
    async function loadProgress() {
      const box = $('#progress-body');
      try {
        const p = (await api('/coordinator/students/' + id + '/progress')).data;
        const apps = p.applications.map((a) => `<tr>
            <td data-l="Company"><span class="name">${esc(a.company)}</span></td><td data-l="Drive">${esc(a.drive_title)}</td>
            <td data-l="Stage">${badge('stage', a.stage)}${(a.history || []).length ? '<div class="list tl" style="margin-top:8px">' + a.history.map((h) => `<div class="li"><div class="l"><div class="t">${h.from ? esc(TPMS.label('stage', h.from)) + ' &rarr; ' : ''}${esc(TPMS.label('stage', h.to))}</div><div class="meta">${esc(h.by || 'System')} &middot; ${fmtDT(h.at)}</div>${h.remarks ? `<div class="meta">${esc(h.remarks)}</div>` : ''}</div></div>`).join('') + '</div>' : ''}</td>
            <td data-l="Applied" class="nw">${fmtDate(a.applied_at)}</td><td data-l="Last change" class="nw">${a.stage_updated_at ? fmtDate(a.stage_updated_at) : ''}</td></tr>`).join('');
        const plcs = p.placements.map((x) => `<tr><td data-l="Company"><span class="name">${esc(x.company)}</span></td><td data-l="Role">${val(x.role_title)}</td><td data-l="CTC" class="nw">${lpa(x.ctc_lpa)}</td><td data-l="Location">${val(x.location)}</td><td data-l="Offer date" class="nw">${x.offer_date ? fmtDate(x.offer_date) : dash}</td><td data-l="Joining date" class="nw">${x.joining_date ? fmtDate(x.joining_date) : dash}</td><td data-l="Status">${badge('placement', x.status)}</td></tr>`).join('');
        box.innerHTML = `<p style="margin-bottom:16px"><span class="badge ${p.is_placed ? 'b-ok' : 'b-neutral'}">${p.is_placed ? 'Placed' : 'Not placed'}</span></p>
          <p class="cap" style="margin-bottom:8px">Applications</p>
          ${p.applications.length ? `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Company</th><th>Drive</th><th>Stage</th><th>Applied</th><th>Last change</th></tr></thead><tbody>${apps}</tbody></table></div>` : '<p class="empty">No applications yet.</p>'}
          <p class="cap" style="margin:24px 0 8px">Placements</p>
          ${p.placements.length ? `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Company</th><th>Role</th><th>CTC</th><th>Location</th><th>Offer date</th><th>Joining date</th><th>Status</th></tr></thead><tbody>${plcs}</tbody></table></div>` : '<p class="empty">No offers yet.</p>'}`;
      } catch (e) { box.innerHTML = notice('no', 'info', esc(e.message)); }
    }

    load();
  })();
</script>
@endpush
