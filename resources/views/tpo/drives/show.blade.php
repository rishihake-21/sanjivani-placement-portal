@extends('layouts.tpo')

@section('title', 'Drive')
@section('nav', 'drives')

@section('content')
  <x-ui.page-header title="Drive" meta="&nbsp;">
    <span id="head-actions" class="row wrap"></span>
  </x-ui.page-header>

  <div id="cancel-note"></div>

  <div class="stack">
    {{-- overview --}}
    <x-ui.card id="overview" title="Overview">
      <dl class="dl" id="overview-dl"><div><dd><div class="skel"></div></dd></div></dl>
      <div style="margin-top:16px" id="draft-note"></div>
    </x-ui.card>

    <div class="grid">
      <x-ui.card id="criteria" title="Eligibility criteria" class="c6">
        <dl class="kv" id="criteria-dl"><dt></dt><dd><div class="skel"></div></dd></dl>
      </x-ui.card>
      <x-ui.card id="text" title="Description and requirements" class="c6">
        <div id="text-body"><div class="skel"></div><div class="skel" style="width:70%"></div></div>
      </x-ui.card>
    </div>

    {{-- eligibility summary --}}
    <x-ui.card id="eligibility" title="Eligible students">
      <x-slot:actions><button type="button" class="linkbtn" id="refresh-elig">Refresh</button></x-slot:actions>
      <p class="meta" style="margin-bottom:16px">Checked live against verified marks. Students with unverified marks show as verification pending, not as rejected.</p>
      <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-ui.stat id="e-candidates" label="Candidates" />
        <x-ui.stat id="e-eligible" label="Eligible" />
        <x-ui.stat id="e-pending" label="Verification pending" />
        <x-ui.stat id="e-not" label="Not eligible" />
      </div>
      <div id="e-failed" style="margin-top:16px"></div>

      <div style="margin-top:24px">
        <x-ui.filter-bar id="elig-filters">
          <div class="field grow">
            <label for="elig-search">Search</label>
            <input id="elig-search" type="search" placeholder="Name or university ID">
          </div>
          <div class="field"><label for="elig-status">Outcome</label>
            <select id="elig-status">
              <option value="">All</option>
              <option value="ELIGIBLE">Eligible</option>
              <option value="VERIFICATION_PENDING">Verification pending</option>
              <option value="NOT_ELIGIBLE">Not eligible</option>
            </select></div>
          <div class="field"><label for="elig-branch">Branch</label><select id="elig-branch"><option value="">All</option></select></div>
        </x-ui.filter-bar>
        <x-ui.table id="elig-rows" :cols="['Student', 'Branch', 'Semester', 'Outcome', 'Application']" />
        <div class="pager" id="elig-pager"></div>
      </div>
    </x-ui.card>

    {{-- applications --}}
    <x-ui.card id="applications" title="Applications">
      <x-slot:actions><span class="meta num" id="app-count"></span></x-slot:actions>
      <div id="stage-chips" class="row wrap" style="margin-bottom:16px"></div>

      <x-ui.filter-bar>
        <div class="field grow">
          <label for="app-search">Search</label>
          <input id="app-search" type="search" placeholder="Name or university ID">
        </div>
        <div class="field"><label for="app-stage">Stage</label><select id="app-stage"><option value="">All stages</option></select></div>
      </x-ui.filter-bar>

      <div class="bulk" id="bulk" hidden>
        <div class="field"><span class="lbl"><span id="bulk-n" class="num">0</span> selected</span>
          <select id="bulk-stage" data-skip aria-label="Move selected to stage"></select></div>
        <div class="field grow"><label for="bulk-remarks">Remarks</label>
          <input id="bulk-remarks" type="text" maxlength="500" placeholder="Optional. Students see this."></div>
        <label class="chk" for="bulk-force"><input type="checkbox" id="bulk-force"><span>Correction (needs remarks)</span></label>
        <button type="button" class="btn btn-primary" id="bulk-go">Move</button>
      </div>
      <div id="bulk-errors"></div>

      <x-ui.table id="app-rows" selectable :cols="['Student', 'Branch', 'Stage', 'Applied', 'Updated', '']" />
      <div class="pager" id="app-pager"></div>
    </x-ui.card>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    const T = TPMS;
    const { $, $$, api, esc, fillRows, pager, badge, label, lpa, fmtDT, fmtDate, dash, notice, fail, toast, flash, confirmAction, debounce, SETTABLE, EMPLOYMENT } = T;
    const id = @json($driveId);
    const listUrl = @json(route('tpo.drives'));
    const appUrl = @json(url('/tpo/applications'));
    const editUrl = listUrl + '/' + id + '/edit';
    const n = (v) => `<span class="num">${esc(v == null ? 0 : v)}</span>`;
    const CRITERIA = {
      min_cgpa: ['Minimum CGPA', (v) => num(v)], min_tenth_percentage: ['Minimum 10th %', (v) => num(v) + '%'],
      min_twelfth_percentage: ['Minimum 12th %', (v) => num(v) + '%'], min_diploma_percentage: ['Minimum diploma %', (v) => num(v) + '%'],
      max_active_backlogs: ['Maximum active backlogs', (v) => v], max_total_backlogs: ['Maximum total backlogs', (v) => v],
    };
    const FAILED_LABEL = {
      min_cgpa: 'CGPA', min_tenth_percentage: '10th %', min_twelfth_percentage: '12th %', min_diploma_percentage: 'Diploma %',
      max_active_backlogs: 'Active backlogs', max_total_backlogs: 'Total backlogs', branch: 'Branch', graduation_year: 'Batch',
      placement_opt_out: 'Opted out', already_placed: 'Already placed',
    };
    const num = (v) => Number(v).toFixed(2);
    let drive = null;
    const kv = (t, v) => `<dt>${t}</dt><dd>${v == null || v === '' ? '&ndash;' : v}</dd>`;
    const dl = (t, v) => `<div><dt>${t}</dt><dd>${v == null || v === '' ? '&ndash;' : v}</dd></div>`;

    /* ---------------- drive details ---------------- */
    async function loadDrive() {
      try { drive = (await api('/tpo/drives/' + id)).data; } catch (e) { fail(e); return; }
      render();
      if (drive.branches.length) { loadSummary(); loadEligible(); } else {
        $('#e-candidates').textContent = '0'; ['e-eligible', 'e-pending', 'e-not'].forEach((x) => { $('#' + x).textContent = '0'; });
        fillRows($('#elig-rows'), [], null, 'Select target branches to see who is eligible.');
      }
      loadApplications();
    }

    function render() {
      const d = drive;
      document.title = d.title + ' · TPMS';
      $('.ph h1').textContent = d.title;
      $('.ph .meta').innerHTML = `${esc(d.company ? d.company.name : '')} &middot; Batch ${esc(d.graduation_year)} &middot; ${badge('drive', d.status)}`;

      const acts = [];
      if (d.status === 'DRAFT') acts.push(`<a class="btn btn-secondary" href="${editUrl}">Edit</a>`, `<button type="button" class="btn btn-danger" data-do="delete">Delete draft</button>`, `<button type="button" class="btn btn-primary" data-do="publish">Publish</button>`);
      if (d.status === 'PUBLISHED') acts.push(`<a class="btn btn-secondary" href="${editUrl}">Edit schedule and text</a>`, `<button type="button" class="btn btn-secondary" data-do="close">Close applications</button>`, `<button type="button" class="btn btn-danger" data-do="cancel">Cancel drive</button>`);
      $('#head-actions').innerHTML = acts.join('');

      $('#cancel-note').innerHTML = d.status === 'CANCELLED' ? `<div style="margin-bottom:24px">${notice('no', 'info', 'This drive was cancelled: ' + esc(d.cancel_reason || ''))}</div>` : '';
      $('#draft-note').innerHTML = d.status === 'DRAFT'
        ? notice('wait', 'info', 'Draft. Students cannot see this drive yet. Publishing needs at least one target branch and a deadline in the future.') : '';

      $('#overview-dl').innerHTML = [
        dl('Company', esc(d.company ? d.company.name : '')), dl('Employment', esc(EMPLOYMENT[d.employment_type] || d.employment_type)),
        dl('CTC', d.ctc_lpa == null ? '' : `${num(d.ctc_lpa)}${d.ctc_max_lpa ? ' to ' + num(d.ctc_max_lpa) : ''} LPA`),
        dl('Location', esc(d.location || '')),
        dl('Drive date', d.drive_date ? fmtDate(d.drive_date) : ''), dl('Application deadline', d.application_deadline ? fmtDT(d.application_deadline) : ''),
        dl('Published', d.published_at ? fmtDT(d.published_at) : ''), dl('Placed students may apply', d.allow_placed_students ? 'Yes' : 'No'),
        `<div style="grid-column:1/-1"><dt>Target branches</dt><dd>${d.branches.length ? d.branches.map((b) => `<span class="tag">${esc(b.name)}</span>`).join(' ') : '&ndash;'}</dd></div>`,
      ].join('');

      const e = d.eligibility || {};
      const rows = Object.entries(CRITERIA).filter(([k]) => e[k] != null).map(([k, [name, f]]) => kv(name, esc(f(e[k]))));
      if (e.min_twelfth_percentage != null) rows.push(kv('Lateral entry', e.diploma_counts_as_twelfth ? 'Diploma % counts as 12th' : 'Real 12th marks required'));
      $('#criteria-dl').innerHTML = rows.length ? rows.join('') : '<dt></dt><dd class="meta">No marks criteria. Only batch and branch are checked.</dd>';

      $('#text-body').innerHTML = `<p class="cap">Job description</p><p class="prose" style="margin:4px 0 16px">${d.description ? esc(d.description) : '<span class="meta">Not provided.</span>'}</p>
        <p class="cap">Additional requirements</p><p class="prose" style="margin-top:4px">${d.additional_requirements ? esc(d.additional_requirements) : '<span class="meta">None.</span>'}</p>`;

      const sel = $('#elig-branch');
      sel.innerHTML = '<option value="">All</option>' + d.branches.map((b) => `<option value="${b.id}">${esc(b.name)}</option>`).join('');
      $('#app-stage').innerHTML = '<option value="">All stages</option>' + Object.keys(T.NEXT).map((s) => `<option value="${s}">${esc(label('stage', s))}</option>`).join('');
      $('#bulk-stage').innerHTML = SETTABLE.map((s) => `<option value="${s}">${esc(label('stage', s))}</option>`).join('');
      $('#applications').hidden = d.status === 'DRAFT';
      $('#eligibility .card-h h2').textContent = d.status === 'DRAFT' ? 'Eligible students (preview)' : 'Eligible students';
    }

    /* ---------------- header actions ---------------- */
    $('#head-actions').addEventListener('click', async (ev) => {
      const b = ev.target.closest('[data-do]');
      if (!b) return;
      const act = b.dataset.do;
      const cfg = {
        publish: { title: 'Publish drive', kind: 'wait', message: `Eligible students of the ${esc(drive.graduation_year)} batch in the selected branches are notified. After publishing, the criteria and target branches are locked.`, confirmLabel: 'Publish', path: '/publish', method: 'POST', done: 'Drive published.' },
        close: { title: 'Close applications', kind: 'wait', message: 'Students can no longer apply. Existing applications keep moving through the rounds.', confirmLabel: 'Close applications', path: '/close', method: 'POST', done: 'Applications closed.' },
        cancel: { title: 'Cancel drive', kind: 'no', message: 'Students who are still in the process are told the drive is cancelled. This cannot be undone.', confirmLabel: 'Cancel drive', danger: true, reason: { label: 'Reason shown to students', required: true, min: 10 }, path: '/cancel', method: 'POST', done: 'Drive cancelled.' },
        delete: { title: 'Delete draft', kind: 'no', message: 'This draft was never published and will be removed.', confirmLabel: 'Delete draft', danger: true, path: '', method: 'DELETE', done: 'Draft deleted.' },
      }[act];
      const res = await confirmAction(cfg);
      if (!res) return;
      try {
        await api('/tpo/drives/' + id + cfg.path, { method: cfg.method, body: cfg.reason ? { reason: res.reason } : undefined });
        if (act === 'delete') { flash(cfg.done); location.href = listUrl; return; }
        toast(cfg.done);
        loadDrive();
      } catch (e) { fail(e); }
    });

    /* ---------------- eligibility ---------------- */
    async function loadSummary() {
      ['e-candidates', 'e-eligible', 'e-pending', 'e-not'].forEach((x) => { $('#' + x).innerHTML = '&ndash;'; });
      try {
        const s = (await api('/tpo/drives/' + id + '/eligibility-summary')).data;
        $('#e-candidates').innerHTML = n(s.candidates); $('#e-eligible').innerHTML = n(s.eligible);
        $('#e-pending').innerHTML = n(s.verification_pending); $('#e-not').innerHTML = n(s.not_eligible);
        const failed = Object.entries(s.failed_criteria || {});
        $('#e-failed').innerHTML = failed.length
          ? notice('neutral', 'info', 'Criteria that exclude the most students: ' + failed.slice(0, 4).map(([k, c]) => `${esc(FAILED_LABEL[k] || k)} (${c})`).join(', ') + '.')
          : '';
      } catch (e) { fail(e); }
    }
    $('#refresh-elig').addEventListener('click', () => { loadSummary(); eligPage = 1; loadEligible(); });

    let eligPage = 1;
    async function loadEligible() {
      try {
        const res = await api('/tpo/drives/' + id + '/eligible-students', { query: {
          status: $('#elig-status').value, branch_id: $('#elig-branch').value, search: $('#elig-search').value.trim(), page: eligPage } });
        fillRows($('#elig-rows'), res.data, (s) => [
          `<span class="name">${esc(s.full_name)}</span><span class="sub">${esc(s.university_id)}</span>`, esc(s.branch || ''), n(s.current_semester),
          `<div class="stk">${badge('elig', s.eligibility.status)}${s.eligibility.reasons.length ? `<ul class="reasons">${s.eligibility.reasons.map((r) => `<li>${esc(r)}</li>`).join('')}</ul>` : ''}</div>`,
          s.application_stage ? badge('stage', s.application_stage) : dash,
        ], 'No students match these filters.');
        pager($('#elig-pager'), res, (p) => { eligPage = p; loadEligible(); });
      } catch (e) { fail(e); }
    }
    const reEligible = () => { eligPage = 1; loadEligible(); };
    $('#elig-search').addEventListener('input', debounce(reEligible, 300));
    $('#elig-status').addEventListener('change', reEligible);
    $('#elig-branch').addEventListener('change', reEligible);

    /* ---------------- applications + bulk stage change ---------------- */
    let appPage = 1;
    const selected = new Set();

    async function loadApplications() {
      try {
        const res = await api('/tpo/applications', { query: { drive_id: id, stage: $('#app-stage').value, search: $('#app-search').value.trim(), page: appPage } });
        $('#app-count').textContent = res.total != null ? res.total + ' applications' : '';
        const rows = res.data;
        fillRows($('#app-rows'), rows, (a) => [
          { html: `<input type="checkbox" data-sel="${a.id}" aria-label="Select ${esc(a.student.full_name)}"${selected.has(a.id) ? ' checked' : ''}>`, cls: 'sel' },
          `<span class="name">${esc(a.student.full_name)}</span><span class="sub">${esc(a.student.university_id)}</span>`, esc(a.student.branch || ''),
          badge('stage', a.stage), fmtDate(a.applied_at), fmtDate(a.stage_updated_at),
          { html: `<a class="btn btn-secondary" href="${appUrl}/${a.id}">Open</a>`, cls: 'act' },
        ], 'No applications yet.');
        $('#app-rows-all').checked = rows.length > 0 && rows.every((a) => selected.has(a.id));
        pager($('#app-pager'), res, (p) => { appPage = p; loadApplications(); });
        stageChips();
      } catch (e) { fail(e); }
    }

    function stageChips() {
      const by = drive.applications_by_stage || {};
      $('#stage-chips').innerHTML = Object.keys(T.NEXT).filter((s) => by[s]).map((s) => `${badge('stage', s)}<span class="meta num" style="margin-right:12px">${by[s]}</span>`).join('') || '<span class="meta">No applications yet.</span>';
    }

    function syncBulk() {
      $('#bulk').hidden = selected.size === 0;
      $('#bulk-n').textContent = selected.size;
    }
    $('#app-rows').addEventListener('change', (e) => {
      const c = e.target.closest('[data-sel]');
      if (!c) return;
      const k = Number(c.dataset.sel);
      if (c.checked) selected.add(k); else selected.delete(k);
      syncBulk();
    });
    $('#app-rows-all').addEventListener('change', (e) => {
      $$('[data-sel]', $('#app-rows')).forEach((c) => { c.checked = e.target.checked; const k = Number(c.dataset.sel); if (e.target.checked) selected.add(k); else selected.delete(k); });
      syncBulk();
    });
    $('#bulk-go').addEventListener('click', async (ev) => {
      const btn = ev.currentTarget;
      $('#bulk-errors').innerHTML = '';
      const stage = $('#bulk-stage').value;
      const res = await confirmAction({
        title: 'Move ' + selected.size + ' application' + (selected.size === 1 ? '' : 's'),
        message: `Move to <b>${esc(label('stage', stage))}</b>? Students are notified. If one application cannot make this move, none are changed.`, kind: 'wait', confirmLabel: 'Move',
      });
      if (!res) return;
      btn.disabled = true;
      try {
        const out = await api('/tpo/drives/' + id + '/applications/bulk-stage', { method: 'POST', body: {
          application_ids: Array.from(selected), stage, remarks: $('#bulk-remarks').value.trim() || null, force: $('#bulk-force').checked } });
        toast(out.updated + ' application' + (out.updated === 1 ? '' : 's') + ' updated.');
        selected.clear(); syncBulk(); $('#bulk-remarks').value = ''; $('#bulk-force').checked = false;
        loadDrive();
      } catch (e) {
        const msgs = e.errors ? Object.values(e.errors).flat() : [e.message];
        $('#bulk-errors').innerHTML = '<div style="margin-bottom:16px">' + notice('no', 'info', '<b>Nothing was changed.</b><br>' + msgs.map(esc).join('<br>')) + '</div>';
      } finally { btn.disabled = false; }
    });
    const reApps = () => { appPage = 1; loadApplications(); };
    $('#app-search').addEventListener('input', debounce(reApps, 300));
    $('#app-stage').addEventListener('change', reApps);

    loadDrive();
  })();
</script>
@endpush
