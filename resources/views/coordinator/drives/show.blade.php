@extends('layouts.coordinator')

@section('title', 'Drive')
@section('nav', 'drives')

{{-- Controller passes: $driveId. Data: GET /api/coordinator/drives/{id}. --}}
@section('content')
  <x-ui.page-header title="Drive" meta="&nbsp;">
    <a class="btn btn-secondary" href="{{ route('coordinator.drives') }}">All drives</a>
    <a class="btn btn-primary" id="btn-apps" href="{{ route('coordinator.applications') }}">View applications</a>
  </x-ui.page-header>

  <div class="stack">
    <x-ui.card id="overview" title="Overview">
      <div id="overview-body"><div class="skel"></div><div class="skel" style="width:70%"></div></div>
    </x-ui.card>

    <div class="grid">
      <x-ui.card id="criteria" title="Eligibility" class="c6">
        <div id="criteria-body"><div class="skel"></div></div>
      </x-ui.card>
      <x-ui.card id="stages" title="Applications from your department" class="c6">
        <div class="tbl-wrap">
          <table class="tbl" data-cols='["Stage","Applications"]'>
            <thead><tr><th>Stage</th><th>Applications</th></tr></thead>
            <tbody id="stage-rows"><tr><td colspan="2"><div class="skel" style="width:60%"></div></td></tr></tbody>
          </table>
        </div>
      </x-ui.card>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fillRows, badge, label, lpa, num2, fmtDate, fmtDT, dash, fail, EMPLOYMENT } = TPMS;
    const id = @json($driveId);
    const appsUrl = @json(route('coordinator.applications'));
    const val = (v) => (v == null || v === '' ? dash : esc(v));
    const dl = (pairs) => '<dl class="dl">' + pairs.map(([l, v]) => `<div><dt>${esc(l)}</dt><dd>${v}</dd></div>`).join('') + '</dl>';

    async function load() {
      let d;
      try { d = (await api('/coordinator/drives/' + id)).data; } catch (e) { fail(e); return; }
      document.title = d.title + ' · TPMS';
      $('.ph h1').textContent = d.title;
      $('.ph .meta').innerHTML = `${esc(d.company || '')} &middot; Batch ${esc(d.graduation_year)} &middot; ${badge('drive', d.status)}`;
      $('#btn-apps').href = appsUrl + '?drive_id=' + d.id;

      const ctc = d.ctc_lpa == null ? dash : lpa(d.ctc_lpa) + (d.ctc_max_lpa && Number(d.ctc_max_lpa) !== Number(d.ctc_lpa) ? ` <span class="meta">up to ${num2(d.ctc_max_lpa)} LPA</span>` : '');
      $('#overview-body').innerHTML = dl([
        ['Employment type', val(EMPLOYMENT[d.employment_type] || d.employment_type)], ['CTC', ctc], ['Location', val(d.location)],
        ['Drive date', d.drive_date ? fmtDate(d.drive_date) : dash], ['Application deadline', d.application_deadline ? fmtDT(d.application_deadline) : dash],
        ['Applications from your department', `<span class="num">${esc(d.department_applications_count == null ? 0 : d.department_applications_count)}</span>`],
      ])
        + (d.description ? '<p class="cap" style="margin:24px 0 8px">Description</p><p style="white-space:pre-wrap;overflow-wrap:anywhere">' + esc(d.description) + '</p>' : '')
        + (d.additional_requirements ? '<p class="cap" style="margin:24px 0 8px">Additional requirements</p><p style="white-space:pre-wrap;overflow-wrap:anywhere">' + esc(d.additional_requirements) + '</p>' : '');

      const el = d.eligibility || {};
      const limit = (v, pct) => (v == null ? '<span class="meta">Not set</span>' : `<span class="num">${num2(v)}${pct ? '%' : ''}</span>`);
      const cnt = (v) => (v == null ? '<span class="meta">Not set</span>' : `<span class="num">${esc(v)}</span>`);
      const rows = [['Minimum CGPA', limit(el.min_cgpa)], ['Minimum 10th percentage', limit(el.min_tenth_percentage, true)], ['Minimum 12th percentage', limit(el.min_twelfth_percentage, true)],
        ['Minimum diploma percentage', limit(el.min_diploma_percentage, true)], ['Maximum active backlogs', cnt(el.max_active_backlogs)], ['Maximum total backlogs', cnt(el.max_total_backlogs)]];
      $('#criteria-body').innerHTML = '<dl class="kv" style="grid-template-columns:220px minmax(0,1fr)">' + rows.map(([l, v]) => `<dt>${l}</dt><dd>${v}</dd>`).join('') + '</dl>'
        + '<p class="cap" style="margin:24px 0 8px">Branches from your department</p>'
        + ((d.department_branches || []).length ? d.department_branches.map((b) => `<span class="tag">${esc(b.code)} &middot; ${esc(b.name)}</span>`).join(' ') : '<p class="empty">None of your branches is eligible.</p>');

      const order = ['APPLIED', 'SHORTLISTED', 'APTITUDE', 'TECHNICAL', 'HR', 'SELECTED', 'OFFER_RECEIVED', 'PLACED', 'REJECTED', 'WITHDRAWN'];
      const by = d.department_applications_by_stage || {};
      fillRows($('#stage-rows'), order.filter((k) => by[k]), (k) => [badge('stage', k), `<span class="num">${esc(by[k])}</span>`], 'No applications from your department yet.');
    }

    load();
  })();
</script>
@endpush
