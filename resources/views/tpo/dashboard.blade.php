@extends('layouts.tpo')

@section('title', 'Dashboard')
@section('nav', 'dashboard')

@section('content')
  <x-ui.page-header title="Placement dashboard" meta="University-wide numbers. Only verified placements count as placed.">
    <div class="field" style="min-width:180px">
      <label for="flt-graduation_year">Batch (graduation year)</label>
      <select id="flt-graduation_year" data-filter="graduation_year">
        <option value="">All batches</option>
        @foreach ($years as $year)
          <option value="{{ $year }}">{{ $year }}</option>
        @endforeach
      </select>
    </div>
  </x-ui.page-header>

  <div id="dash-error"></div>

  <div class="stack">
    <x-ui.card id="outcome" title="Placement outcome">
      <div class="stat-grid">
        <x-ui.stat id="s-students" label="Students" />
        <x-ui.stat id="s-placed" label="Placed" />
        <x-ui.stat id="s-pct" label="Placement %" />
        <x-ui.stat id="s-high" label="Highest CTC" />
        <x-ui.stat id="s-avg" label="Average CTC" />
      </div>
      <p class="meta" style="margin-top:16px" id="s-note"></p>
    </x-ui.card>

    <x-ui.card id="activity" title="Recruitment activity">
      <div class="stat-grid">
        <x-ui.stat id="s-live" label="Drives live" />
        <x-ui.stat id="s-apps" label="Applications" />
        <x-ui.stat id="s-recruiting" label="In recruitment" />
        <x-ui.stat id="s-selected" label="Selected" />
        <x-ui.stat id="s-offers" label="Offers to verify" />
      </div>
    </x-ui.card>

    <div class="grid">
      <x-ui.card id="stages" title="Applications by stage" class="c6">
        <div class="tbl-wrap">
          <table class="tbl" data-cols='["Stage","Applications","Share"]'>
            <thead><tr><th>Stage</th><th>Applications</th><th>Share</th></tr></thead>
            <tbody id="stage-rows"><tr><td colspan="3"><div class="skel" style="width:60%"></div></td></tr></tbody>
          </table>
        </div>
      </x-ui.card>

      <x-ui.card id="attention" title="Needs attention" class="c6">
        <div class="list" id="attention-list"><div class="skel"></div><div class="skel" style="width:70%"></div></div>
      </x-ui.card>
    </div>

    <x-ui.card id="closing" title="Drives closing in the next 7 days">
      <x-slot:actions><a class="linkbtn" href="{{ route('tpo.drives') }}?status=PUBLISHED">All live drives</a></x-slot:actions>
      <x-ui.table id="closing-rows" :cols="['Company', 'Role', 'Deadline', '']" />
    </x-ui.card>

    <x-ui.card id="departments" title="Departments">
      <x-slot:actions><a class="linkbtn" href="{{ route('tpo.reports') }}">Full reports</a></x-slot:actions>
      <x-ui.table id="dept-rows" :cols="['Department', 'Students', 'Applied', 'Placed', 'Unplaced', 'Placement %', 'Highest CTC', 'Average CTC']" />
    </x-ui.card>

    <x-ui.card id="companies" title="Companies">
      <x-slot:actions><a class="linkbtn" href="{{ route('tpo.reports') }}#sec-companies">All companies</a></x-slot:actions>
      <x-ui.table id="company-rows" :cols="['Company', 'Drives', 'Applications', 'Selected', 'Placed', 'Highest CTC']" />
    </x-ui.card>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fillRows, badge, label, num2, lpa, pct, fmtDT, dash, notice, fail, syncQuery, readFilters, filterValues, orDash } = TPMS;
    const drivesUrl = @json(route('tpo.drives'));
    const setStat = (id, html) => { $('#' + id).innerHTML = html; };
    const n = (v) => `<span class="num">${esc(v == null ? 0 : v)}</span>`;

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

    function render(d, depts, comps) {
      const s = d.students, p = d.placements, a = d.applications;
      setStat('s-students', n(s.total));
      setStat('s-placed', n(p.placed_students));
      setStat('s-pct', p.placement_percentage == null ? '&ndash;' : n(num2(p.placement_percentage) + '%'));
      setStat('s-high', p.highest_ctc_lpa == null ? '&ndash;' : n(num2(p.highest_ctc_lpa) + ' LPA'));
      setStat('s-avg', p.average_ctc_lpa == null ? '&ndash;' : n(num2(p.average_ctc_lpa) + ' LPA'));
      $('#s-note').textContent = `${s.opted_out_of_placement} opted out of placements and are left out of the percentage. ${p.unplaced_students} students are still unplaced.`;

      setStat('s-live', n((d.drives.by_status || {}).PUBLISHED || 0));
      setStat('s-apps', n(a.total));
      setStat('s-recruiting', n(a.in_recruitment));
      setStat('s-selected', n(a.selected));
      setStat('s-offers', n(p.open_offers));

      // applications by stage, with a share bar
      const order = ['APPLIED', 'SHORTLISTED', 'APTITUDE', 'TECHNICAL', 'HR', 'SELECTED', 'OFFER_RECEIVED', 'PLACED', 'REJECTED', 'WITHDRAWN'];
      const total = a.total || 0;
      fillRows($('#stage-rows'), order.filter((k) => (a.by_stage || {})[k]), (k) => {
        const c = a.by_stage[k];
        const share = total ? Math.round((c / total) * 100) : 0;
        return [`<a class="linkbtn" href="${@json(route('tpo.applications'))}?stage=${k}">${esc(label('stage', k))}</a>`, n(c),
          `<span class="bar-track" role="img" aria-label="${share}%"><i style="width:${share}%"></i></span><span class="num meta">${share}%</span>`];
      }, 'No applications yet.');

      // needs attention
      const drafts = (d.drives.by_status || {}).DRAFT || 0;
      const rows = [];
      if (p.open_offers) rows.push(['Offers waiting for your verification', `${p.open_offers} offer${p.open_offers === 1 ? '' : 's'} are recorded but not official yet.`, @json(route('tpo.placements')) + '?status=OFFERED', 'Review']);
      if (drafts) rows.push(['Draft drives', `${drafts} draft${drafts === 1 ? '' : 's'} not published yet.`, drivesUrl + '?status=DRAFT', 'Open']);
      const v = d.verification_backlog;
      if (v.academic_records || v.experiences) rows.push(['Verification backlog', `${v.academic_records} academic record${v.academic_records === 1 ? '' : 's'} and ${v.experiences} experience entr${v.experiences === 1 ? 'y' : 'ies'} wait for coordinators. Students with unverified marks cannot apply.`, null, null]);
      $('#attention-list').innerHTML = rows.length
        ? rows.map(([t, m, href, cta]) => `<div class="li"><div class="l"><div class="t">${esc(t)}</div><div class="meta">${esc(m)}</div></div>${href ? `<div class="r"><a class="btn btn-secondary" href="${href}">${cta}</a></div>` : ''}</div>`).join('')
        : '<p class="empty">Nothing needs attention.</p>';

      fillRows($('#closing-rows'), d.drives.closing_in_7_days, (x) => [
        `<span class="name">${esc(x.company)}</span>`, esc(x.title), fmtDT(x.application_deadline),
        { html: `<a class="btn btn-secondary" href="${drivesUrl}/${x.id}">View</a>`, cls: 'act' },
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
