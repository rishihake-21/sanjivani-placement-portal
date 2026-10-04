@extends('layouts.tpo')

@section('title', 'Application')
@section('nav', 'applications')

@section('content')
  <x-ui.page-header title="Application" meta="&nbsp;">
    <a class="btn btn-secondary" href="{{ route('tpo.applications') }}">All applications</a>
  </x-ui.page-header>

  <div class="stack">
    <x-ui.card id="progress" title="Recruitment progress">
      <div id="progress-body"><div class="skel"></div><div class="skel" style="width:70%"></div></div>
    </x-ui.card>

    <div id="offer-card"></div>

    <div class="grid">
      <x-ui.card id="history" title="History" class="c6">
        <div class="list tl" id="history-list"><div class="skel"></div></div>
      </x-ui.card>
      <x-ui.card id="academics" title="Verified marks when the student applied" class="c6">
        <div id="academics-body"><div class="skel"></div></div>
      </x-ui.card>
    </div>

    <x-ui.card id="checks" title="Eligibility check at application time">
      <x-ui.table id="check-rows" :cols="['Check', 'Required', 'Student', 'Result']" />
    </x-ui.card>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    const T = TPMS;
    const { $, api, esc, fillRows, badge, label, fld, input, textarea, options, openDrawer, closeDrawer, collect, clearErrors, fail, toast,
            notice, fmtDT, fmtDate, dash, num2, PIPELINE, NEXT, SETTABLE, correctable } = T;
    const id = @json($applicationId);
    const drivesBase = @json(route('tpo.drives'));
    const placementsUrl = @json(route('tpo.placements'));
    let a = null;

    async function load() {
      try { a = (await api('/tpo/applications/' + id)).data; } catch (e) { fail(e); return; }
      render();
    }

    const val = (v) => (v === null || v === undefined ? dash : typeof v === 'boolean' ? (v ? 'Yes' : 'No') : esc(v));

    function render() {
      document.title = a.student.full_name + ' · TPMS';
      $('.ph h1').textContent = a.student.full_name;
      $('.ph .meta').innerHTML = `${esc(a.student.university_id)} &middot; ${esc(a.student.branch || '')} &middot; <a class="linkbtn" href="${drivesBase}/${a.drive.id}">${esc(a.company || '')} &ndash; ${esc(a.drive.title)}</a> &middot; ${badge('stage', a.stage)}`;
      renderProgress();
      renderOffer();

      $('#history-list').innerHTML = a.history.length
        ? a.history.map((h) => `<div class="li"><div class="l">
            <div class="t">${h.from ? esc(label('stage', h.from)) + ' &rarr; ' : ''}${esc(label('stage', h.to))}</div>
            <div class="meta">${esc(h.by || 'System')} &middot; ${fmtDT(h.at)}</div>
            ${h.remarks ? `<div class="meta">${esc(h.remarks)}</div>` : ''}</div></div>`).join('')
        : '<p class="empty">No history.</p>';

      const snap = a.data_snapshot || {};
      const v = snap.verified_academics || {};
      const stat = (l, x) => `<div class="stat"><span class="cap">${l}</span><div class="v">${x == null ? '&ndash;' : '<span class="num">' + esc(x) + '</span>'}</div></div>`;
      const p = (x) => (x == null ? null : num2(x) + '%');
      $('#academics-body').innerHTML = snap.verified_academics
        ? `<div class="stat-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">${stat('10th', p(v.tenth_percentage))}${stat(v.diploma_percentage != null ? 'Diploma' : '12th', p(v.diploma_percentage != null ? v.diploma_percentage : v.twelfth_percentage))}${stat('CGPA', v.cgpa == null ? null : num2(v.cgpa))}${stat('Active backlogs', v.active_backlogs)}${stat('Total backlogs', v.total_backlogs)}</div>
           <p class="meta" style="margin-top:16px">Frozen when the student applied. Later changes to the student's profile do not alter it.</p>`
        : '<p class="empty">No snapshot stored.</p>';

      const checks = ((snap.eligibility || {}).checks || []).filter((c) => c.result !== 'NA');
      fillRows($('#check-rows'), checks, (c) => [`<span class="name">${esc(c.label)}</span>`, val(c.required), val(c.actual), badge('check', c.result)], 'No checks stored.');
    }

    /* ---------------- stage controls ---------------- */
    function renderProgress() {
      const idx = PIPELINE.indexOf(a.stage);
      const segs = PIPELINE.map((s, i) => `<i class="${idx >= 0 && i <= idx ? 'on' : ''}"></i>`).join('');
      const terminal = ['REJECTED', 'WITHDRAWN'].includes(a.stage);
      const next = NEXT[a.stage] || [];

      let controls = '';
      if (a.stage === 'OFFER_RECEIVED' || a.stage === 'PLACED') {
        controls = notice('neutral', 'info', 'Offers and placements are managed on the <a class="linkbtn" href="' + placementsUrl + '">Placements</a> page, not by changing the stage.');
      } else if (a.stage === 'WITHDRAWN') {
        controls = notice('neutral', 'info', 'The student withdrew this application. Only the student can withdraw.');
      } else {
        controls = `<div class="bulk" style="border-left-color:var(--border);margin-bottom:0">
          <div class="field"><label for="stage-to">Move to</label><select id="stage-to" data-skip></select></div>
          <div class="field grow"><label for="stage-remarks">Remarks</label><input id="stage-remarks" data-skip type="text" maxlength="500" placeholder="Optional. The student sees this."></div>
          ${correctable(a.stage) ? '<label class="chk" for="stage-force"><input type="checkbox" id="stage-force" data-skip><span>This corrects a mistake</span></label>' : ''}
          <button type="button" class="btn btn-primary" id="stage-go">Update stage</button></div>
          <div id="stage-error" style="margin-top:12px"></div>`;
      }

      $('#progress-body').innerHTML = `<div class="seg" role="img" aria-label="Stage ${esc(label('stage', a.stage))}">${segs}</div>
        <p class="meta" style="margin:8px 0 16px">Applied ${fmtDate(a.applied_at)} &middot; last change ${fmtDT(a.stage_updated_at)}${terminal ? ' &middot; ' + esc(label('stage', a.stage)) : ''}</p>${controls}`;

      const sel = $('#stage-to');
      if (!sel) return;
      const fill = (force) => {
        const list = force ? SETTABLE.filter((s) => s !== a.stage) : next.filter((s) => SETTABLE.includes(s));
        sel.innerHTML = list.length ? list.map((s) => `<option value="${s}">${esc(label('stage', s))}</option>`).join('') : '<option value="">No further moves</option>';
        $('#stage-go').disabled = !list.length;
      };
      fill(false);
      const f = $('#stage-force');
      if (f) f.addEventListener('change', () => fill(f.checked));
      $('#stage-go').addEventListener('click', async (ev) => {
        const force = f ? f.checked : false;
        const remarks = $('#stage-remarks').value.trim();
        const stage = sel.value;
        $('#stage-error').innerHTML = '';
        if (force && remarks.length < 10) { $('#stage-error').innerHTML = notice('no', 'info', 'A correction needs remarks of at least 10 characters.'); return; }
        const btn = ev.currentTarget;
        btn.disabled = true;
        try {
          await api('/tpo/applications/' + id + '/stage', { method: 'POST', body: { stage, remarks: remarks || null, force } });
          toast('Stage updated.');
          load();
        } catch (e) { $('#stage-error').innerHTML = notice('no', 'info', esc(Object.values(e.errors || {}).flat()[0] || e.message)); btn.disabled = false; }
      });
    }

    /* ---------------- offer ---------------- */
    function renderOffer() {
      const box = $('#offer-card');
      if (a.placement) {
        box.innerHTML = `<section class="card"><div class="card-h"><h2>Placement</h2><div class="acts"><a class="linkbtn" href="${placementsUrl}?status=${esc(a.placement.status)}">Open placements</a></div></div>
          <dl class="kv"><dt>Status</dt><dd>${badge('placement', a.placement.status)}</dd><dt>Role</dt><dd>${esc(a.placement.role_title)}</dd>
          <dt>CTC</dt><dd>${a.placement.ctc_lpa == null ? '&ndash;' : num2(a.placement.ctc_lpa) + ' LPA'}</dd></dl></section>`;
        return;
      }
      if (a.stage !== 'SELECTED') { box.innerHTML = ''; return; }
      box.innerHTML = `<section class="card"><div class="card-h"><h2>Offer</h2></div>
        <p class="meta" style="margin-bottom:16px">The student is selected. Record the offer once the company confirms it. It becomes an official placement only after you verify it.</p>
        <button type="button" class="btn btn-primary" id="offer-btn">Record offer</button></section>`;
      $('#offer-btn').addEventListener('click', openOffer);
    }

    function openOffer() {
      openDrawer({
        title: 'Record offer', meta: esc(a.student.full_name) + ' &middot; ' + esc(a.company || ''),
        body: `<div data-summary></div>${notice('neutral', 'info', 'Leave a field empty to use the value from the drive.')}
          <div class="dfg">
            ${fld('f-role_title', 'Role', input('role_title', 'text', '', 'maxlength="150" placeholder="' + esc(a.drive.title) + '"'), { cls: 'full' })}
            ${fld('f-ctc_lpa', 'CTC (LPA)', input('ctc_lpa', 'number', '', 'step="0.01" min="0"'))}
            ${fld('f-location', 'Location', input('location', 'text', '', 'maxlength="150"'))}
            ${fld('f-offer_date', 'Offer date', input('offer_date', 'date', '', ''))}
            ${fld('f-joining_date', 'Joining date', input('joining_date', 'date', '', ''))}
          </div>`,
        footer: '<span class="sp"></span><button type="button" class="btn btn-secondary" data-close>Cancel</button><button type="button" class="btn btn-primary" data-save>Record offer</button>',
        onMount: (el) => el.addEventListener('click', async (e) => {
          const b = e.target.closest('[data-save]');
          if (!b) return;
          clearErrors(el);
          const body = collect(el);
          Object.keys(body).forEach((k) => { if (body[k] === null) delete body[k]; });
          b.disabled = true;
          try {
            await api('/tpo/applications/' + id + '/offer', { method: 'POST', body });
            closeDrawer();
            toast('Offer recorded. Verify it on the Placements page to make it official.');
            load();
          } catch (err) { fail(err, el); } finally { b.disabled = false; }
        }),
      });
    }

    load();
  })();
</script>
@endpush
