@extends('layouts.tpo')

@section('title', 'Dashboard')
@section('nav', 'dashboard')

@section('content')
  <x-ui.page-header title="Placement dashboard" meta="University-wide numbers. Only verified placements count as placed.">
    <x-ui.segmented name="graduation_year" label="Batch" all-label="All batches" :options="array_combine($years, $years)" />
  </x-ui.page-header>

  <div id="dash-error"></div>

  <div class="stack">
    <div class="grid">
      <x-ui.card id="outcome" title="Placement outcome" class="c7">
        <div class="hero">
          <div>
            <span class="cap">Placement rate</span>
            <div class="hero-v" id="h-pct">&ndash;</div>
            <div class="meter" role="progressbar" aria-labelledby="h-pct" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i id="h-meter"></i></div>
            <p class="meta" id="h-note">&nbsp;</p>
          </div>
          <dl class="hero-side">
            <div><dt>Placed</dt><dd id="s-placed">&ndash;</dd></div>
            <div><dt>Unplaced</dt><dd id="s-unplaced">&ndash;</dd></div>
            <div><dt>Highest CTC</dt><dd id="s-high">&ndash;</dd></div>
            <div><dt>Average CTC</dt><dd id="s-avg">&ndash;</dd></div>
          </dl>
        </div>
      </x-ui.card>

      <x-ui.card id="attention" title="Needs attention" class="c5">
        <x-slot:actions><span class="badge b-wait" id="attn-count" hidden></span></x-slot:actions>
        <ul class="queue" id="attention-list"><li><div class="skel" style="width:100%"></div></li></ul>
      </x-ui.card>
    </div>

    <x-ui.card id="pipeline" title="Applications by current stage">
      <x-slot:actions><a class="btn btn-quiet btn-sm" href="{{ route('tpo.applications') }}">All applications</a></x-slot:actions>
      <p class="meta" id="pipe-summary" style="margin:-8px 0 16px">&nbsp;</p>
      <div class="pipe" id="pipe"></div>
      <div class="pipe-foot" id="pipe-foot"></div>
    </x-ui.card>

    <x-ui.card id="closing" title="Drives closing in the next 7 days">
      <x-slot:actions>
        <span class="meta num" id="live-count"></span>
        <a class="btn btn-quiet btn-sm" href="{{ route('tpo.drives') }}?status=PUBLISHED">All live drives</a>
      </x-slot:actions>
      <x-ui.table id="closing-rows" :cols="['Company', 'Role', 'Deadline', '']" />
    </x-ui.card>

    <x-ui.card id="departments" title="Departments">
      <x-slot:actions><a class="btn btn-quiet btn-sm" href="{{ route('tpo.reports') }}">Full reports</a></x-slot:actions>
      <x-ui.table class="nums" id="dept-rows" :cols="['Department', 'Students', 'Applied', 'Placed', 'Unplaced', 'Placement %', 'Highest CTC', 'Average CTC']" />
    </x-ui.card>

    <x-ui.card id="companies" title="Companies">
      <x-slot:actions><a class="btn btn-quiet btn-sm" href="{{ route('tpo.reports') }}#sec-companies">All companies</a></x-slot:actions>
      <x-ui.table class="nums" id="company-rows" :cols="['Company', 'Drives', 'Applications', 'Selected', 'Placed', 'Highest CTC']" />
    </x-ui.card>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, icon, fillRows, label, num2, lpa, fmtDT, dash, notice, fail, syncQuery, readFilters, filterValues } = TPMS;
    const drivesUrl = @json(route('tpo.drives'));
    const appsUrl = @json(route('tpo.applications'));
    const placementsUrl = @json(route('tpo.placements'));
    const n = (v) => `<span class="num">${esc(v == null ? 0 : v)}</span>`;
    const plural = (c, one, many) => `${c} ${c === 1 ? one : many}`;

    // The eight stages a student moves through, in order. The last three mean "selected or better".
    const PIPELINE = ['APPLIED', 'SHORTLISTED', 'APTITUDE', 'TECHNICAL', 'HR', 'SELECTED', 'OFFER_RECEIVED', 'PLACED'];
    const END = ['SELECTED', 'OFFER_RECEIVED', 'PLACED'];
    const SHORT = { OFFER_RECEIVED: 'Offer' };

    async function load() {
      const year = filterValues(document).graduation_year;
      syncQuery({ graduation_year: year });
      $('#dash-error').innerHTML = '';
      try {
        const q = { graduation_year: year };
        const [dash_, depts, comps] = await Promise.all([
          api('/tpo/dashboard', { query: q }),
          api('/tpo/reports/departments', { query: q }),
          api('/tpo/reports/companies', { query: q }),
        ]);
        render(dash_.data, depts.data, comps.data);
      } catch (e) {
        $('#dash-error').innerHTML = '<div style="margin-bottom:24px">' + notice('no', 'info', esc(e.message)) + '</div>';
        fail(e);
      }
    }

    function renderOutcome(d) {
      const s = d.students, p = d.placements;
      const rate = p.placement_percentage;
      const counted = s.total - s.opted_out_of_placement;
      $('#h-pct').textContent = rate == null ? '–' : num2(rate) + '%';
      const meter = $('#h-meter');
      meter.style.width = Math.max(0, Math.min(100, rate || 0)) + '%';
      meter.parentNode.setAttribute('aria-valuenow', String(Math.round(rate || 0)));
      $('#h-note').textContent = counted > 0
        ? `${p.placed_students} of ${plural(counted, 'student', 'students')} placed` + (s.opted_out_of_placement ? ` · ${s.opted_out_of_placement} opted out, not counted` : '')
        : 'No students in this batch yet.';
      $('#s-placed').innerHTML = n(p.placed_students);
      $('#s-unplaced').innerHTML = n(p.unplaced_students);
      const ctc = (v) => (v == null ? dash : `<span class="num">${num2(v)}</span> <small>LPA</small>`);
      $('#s-high').innerHTML = ctc(p.highest_ctc_lpa);
      $('#s-avg').innerHTML = ctc(p.average_ctc_lpa);
    }

    function renderAttention(d) {
      const p = d.placements, v = d.verification_backlog;
      const drafts = (d.drives.by_status || {}).DRAFT || 0;
      const items = []; // [mine?, title, text, href, cta]
      if (p.open_offers) items.push([true, `${plural(p.open_offers, 'offer', 'offers')} to verify`, 'Recorded, but not official until you verify them.', placementsUrl + '?status=OFFERED', 'Review']);
      if (drafts) items.push([true, `${plural(drafts, 'draft drive', 'draft drives')} not published`, 'Students cannot see a draft yet.', drivesUrl + '?status=DRAFT', 'Open']);
      if (v.academic_records || v.experiences) {
        const what = [];
        if (v.academic_records) what.push(plural(v.academic_records, 'academic record', 'academic records'));
        if (v.experiences) what.push(v.experiences === 1 ? '1 experience entry' : v.experiences + ' experience entries');
        items.push([false, 'Verification backlog',
          `${what.join(' and ')} ${v.academic_records + v.experiences === 1 ? 'waits' : 'wait'} for coordinators. Students with unverified marks cannot apply.`, null, null]);
      }
      const mine = items.filter((i) => i[0]).length;
      const badge = $('#attn-count');
      badge.hidden = !mine;
      badge.textContent = mine;
      $('#attention-list').innerHTML = items.length
        ? items.map(([own, t, m, href, cta]) => `<li><span class="dot${own ? '' : ' info'}" aria-hidden="true"></span><div class="q-b"><div class="q-t">${esc(t)}</div><div class="q-m">${esc(m)}</div></div>${href ? `<a class="btn btn-quiet btn-sm" href="${href}">${cta}</a>` : ''}</li>`).join('')
        : `<li><div class="all-clear">${icon('check', 'sm')}Nothing needs your attention.</div></li>`;
    }

    function renderPipeline(a) {
      const by = a.by_stage || {};
      const max = Math.max(1, ...PIPELINE.map((k) => by[k] || 0));
      $('#pipe-summary').textContent = a.total
        ? `${plural(a.total, 'application', 'applications')} · ${a.in_recruitment} in interview rounds · ${a.selected} selected or placed`
        : 'No applications yet for this batch.';
      $('#pipe').innerHTML = PIPELINE.map((k) => {
        const c = by[k] || 0;
        const cls = [END.includes(k) ? 'end' : '', c ? '' : 'zero'].filter(Boolean).join(' ');
        return `<a href="${appsUrl}?stage=${k}" class="${cls}" aria-label="${esc(label('stage', k))}: ${c}">
          <span class="p-l">${esc(SHORT[k] || label('stage', k))}</span><span class="p-n">${c}</span>
          <span class="p-bar"><i data-w="${Math.round((c / max) * 100)}"></i></span></a>`;
      }).join('');
      $('#pipe-foot').innerHTML = ['REJECTED', 'WITHDRAWN'].map((k) =>
        `<a href="${appsUrl}?stage=${k}"><span>${esc(label('stage', k))}</span><b>${by[k] || 0}</b></a>`).join('');
      // widths are applied a frame later so the bars grow in instead of appearing
      requestAnimationFrame(() => requestAnimationFrame(() => { document.querySelectorAll('#pipe i[data-w]').forEach((i) => { i.style.width = i.dataset.w + '%'; }); }));
    }

    function due(iso) {
      const h = (new Date(iso) - Date.now()) / 36e5;
      if (!(h > 0)) return '';
      if (h < 24) return '<span class="due soon">Under 24 h</span>';
      const days = Math.floor(h / 24);
      return `<span class="due${days <= 2 ? ' soon' : ''}">${days} days left</span>`;
    }

    function render(d, depts, comps) {
      renderOutcome(d);
      renderAttention(d);
      renderPipeline(d.applications);

      const live = (d.drives.by_status || {}).PUBLISHED || 0;
      $('#live-count').textContent = plural(live, 'drive live', 'drives live');
      fillRows($('#closing-rows'), d.drives.closing_in_7_days, (x) => [
        `<span class="name">${esc(x.company)}</span>`, esc(x.title), fmtDT(x.application_deadline) + due(x.application_deadline),
        { html: `<a class="btn btn-quiet btn-sm" href="${drivesUrl}/${x.id}">View</a>`, cls: 'act' },
      ], 'No drive closes in the next 7 days.');

      fillRows($('#dept-rows'), depts, (r) => [
        `<span class="name">${esc(r.code)}</span><span class="sub">${esc(r.name)}</span>`, n(r.students), n(r.applied), n(r.placed), n(r.unplaced),
        r.placement_percentage == null ? dash : n(num2(r.placement_percentage) + '%'), lpa(r.highest_ctc_lpa), lpa(r.average_ctc_lpa),
      ], 'No students for this batch.');

      fillRows($('#company-rows'), comps.slice(0, 8), (r) => [
        `<span class="name">${esc(r.company)}</span>`, n(r.drives), n(r.applications), n(r.students_selected), n(r.students_placed), lpa(r.highest_ctc_lpa),
      ], 'No company has run a drive for this batch yet.');
    }

    readFilters(document);
    $('#flt-graduation_year').addEventListener('change', load);
    load();
  })();
</script>
@endpush
