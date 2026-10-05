@extends('layouts.coordinator')

@section('title', 'Application')
@section('nav', 'applications')

{{-- Controller passes: $applicationId. Data: GET /api/coordinator/applications/{id}. Read only. --}}
@section('content')
  <x-ui.page-header title="Application" meta="&nbsp;">
    <a class="btn btn-secondary" href="{{ route('coordinator.applications') }}">All applications</a>
  </x-ui.page-header>

  <div class="stack">
    <x-ui.card id="progress" title="Recruitment progress">
      <div id="progress-body"><div class="skel"></div><div class="skel" style="width:70%"></div></div>
    </x-ui.card>

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
    const { $, api, esc, fillRows, badge, label, fmtDT, fmtDate, dash, num2, fail, PIPELINE } = TPMS;
    const id = @json($applicationId);
    const drivesBase = @json(route('coordinator.drives'));
    const studentsBase = @json(route('coordinator.students'));

    const val = (v) => (v === null || v === undefined ? dash : typeof v === 'boolean' ? (v ? 'Yes' : 'No') : esc(v));

    async function load() {
      let a;
      try { a = (await api('/coordinator/applications/' + id)).data; } catch (e) { fail(e); return; }
      document.title = a.student.full_name + ' · TPMS';
      $('.ph h1').textContent = a.student.full_name;
      $('.ph .meta').innerHTML = `<a class="linkbtn" href="${studentsBase}/${a.student.id}">${esc(a.student.university_id)}</a> &middot; ${esc(a.student.branch || '')} &middot; <a class="linkbtn" href="${drivesBase}/${a.drive.id}">${esc(a.company || '')} &ndash; ${esc(a.drive.title)}</a> &middot; ${badge('stage', a.stage)}`;

      const idx = PIPELINE.indexOf(a.stage);
      const terminal = a.stage === 'REJECTED' || a.stage === 'WITHDRAWN';
      $('#progress-body').innerHTML = `<div class="seg" role="img" aria-label="Stage ${esc(label('stage', a.stage))}">${PIPELINE.map((s, i) => `<i class="${idx >= 0 && i <= idx ? 'on' : ''}"></i>`).join('')}</div>
        <p class="meta" style="margin-top:8px">${terminal ? esc(label('stage', a.stage)) + '. This application is closed.' : 'Now at ' + esc(label('stage', a.stage)) + '.'} Applied ${fmtDate(a.applied_at)}${a.stage_updated_at ? ' &middot; last change ' + fmtDate(a.stage_updated_at) : ''}.</p>`;

      $('#history-list').innerHTML = (a.history || []).length
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

    load();
  })();
</script>
@endpush
