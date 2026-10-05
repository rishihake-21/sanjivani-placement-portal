@extends('layouts.coordinator')

@section('title', 'Dashboard')
@section('nav', 'dashboard')

{{-- Controller passes: $department ['id','name','code'], $years [int, ...] (graduation years of the department's students). --}}
@section('content')
  <x-ui.page-header :title="$department['name']" :meta="e($department['code']) . ' &middot; Verification, students, applications and placements of your department.'">
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
    <x-ui.card id="review" title="Waiting for your review">
      <x-slot:actions><a class="linkbtn" href="{{ route('coordinator.queue') }}">Open the verification queue</a></x-slot:actions>
      <div class="stat-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
        <x-ui.stat id="s-acad" label="Academic records" />
        <x-ui.stat id="s-exp" label="Experiences" />
        <x-ui.stat id="s-doc" label="Documents" />
        <x-ui.stat id="s-reup" label="Open re-upload requests" />
      </div>
    </x-ui.card>

    <x-ui.card id="outcome" title="Placement outcome">
      <x-slot:actions><a class="linkbtn" href="{{ route('coordinator.placements') }}">All placements</a></x-slot:actions>
      <div class="stat-grid">
        <x-ui.stat id="s-students" label="Students" />
        <x-ui.stat id="s-placed" label="Placed" />
        <x-ui.stat id="s-pct" label="Placement %" />
        <x-ui.stat id="s-offers" label="Open offers" />
        <x-ui.stat id="s-unplaced" label="Unplaced" />
      </div>
      <p class="meta" style="margin-top:16px" id="s-note"></p>
    </x-ui.card>

    <x-ui.card id="activity" title="Recruitment activity">
      <x-slot:actions><a class="linkbtn" href="{{ route('coordinator.applications') }}">All applications</a></x-slot:actions>
      <div class="stat-grid">
        <x-ui.stat id="s-live" label="Active drives" />
        <x-ui.stat id="s-apps" label="Applications" />
        <x-ui.stat id="s-applied" label="Students applied" />
        <x-ui.stat id="s-recruiting" label="In recruitment" />
        <x-ui.stat id="s-selected" label="Selected" />
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

      <x-ui.card id="semesters" title="Students by semester" class="c6">
        <x-slot:actions><a class="linkbtn" href="{{ route('coordinator.students') }}">All students</a></x-slot:actions>
        <x-ui.table id="sem-rows" :cols="['Semester', 'Students']" />
      </x-ui.card>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fillRows, label, num2, notice, fail, syncQuery, readFilters, filterValues } = TPMS;
    const queueUrl = @json(route('coordinator.queue'));
    const reuploadsUrl = @json(route('coordinator.reuploads'));
    const applicationsUrl = @json(route('coordinator.applications'));
    const setStat = (id, html) => { $('#' + id).innerHTML = html; };
    const n = (v) => `<span class="num">${esc(v == null ? 0 : v)}</span>`;

    async function load() {
      const year = filterValues(document).graduation_year;
      syncQuery({ graduation_year: year });
      $('#dash-error').innerHTML = '';
      try {
        const res = await api('/coordinator/dashboard', { query: { graduation_year: year } });
        render(res.data);
      } catch (e) {
        $('#dash-error').innerHTML = '<div style="margin-bottom:24px">' + notice('no', 'info', esc(e.message)) + '</div>';
        fail(e);
      }
    }

    function render(d) {
      const s = d.students, v = d.verification_queue, a = d.applications, p = d.placements;

      setStat('s-acad', `<a class="linkbtn" href="${queueUrl}">${n(v.academic_records)}</a>`);
      setStat('s-exp', `<a class="linkbtn" href="${queueUrl}">${n(v.experiences)}</a>`);
      setStat('s-doc', `<a class="linkbtn" href="${queueUrl}">${n(v.documents)}</a>`);
      setStat('s-reup', `<a class="linkbtn" href="${reuploadsUrl}?status=OPEN">${n(v.open_reupload_requests)}</a>`);

      setStat('s-students', n(s.total));
      setStat('s-placed', n(p.placed_students));
      setStat('s-pct', p.placement_percentage == null ? '&ndash;' : n(num2(p.placement_percentage) + '%'));
      setStat('s-offers', n(p.open_offers));
      setStat('s-unplaced', n(p.unplaced_students));
      $('#s-note').textContent = `${s.lateral_entry} lateral-entry students. ${s.opted_out_of_placement} opted out of placements and are left out of the percentage.`;

      setStat('s-live', n(d.active_drives));
      setStat('s-apps', n(a.total));
      setStat('s-applied', n(a.students_applied));
      setStat('s-recruiting', n(a.in_recruitment));
      setStat('s-selected', n(a.selected));

      const order = ['APPLIED', 'SHORTLISTED', 'APTITUDE', 'TECHNICAL', 'HR', 'SELECTED', 'OFFER_RECEIVED', 'PLACED', 'REJECTED', 'WITHDRAWN'];
      const total = a.total || 0;
      fillRows($('#stage-rows'), order.filter((k) => (a.by_stage || {})[k]), (k) => {
        const c = a.by_stage[k];
        const share = total ? Math.round((c / total) * 100) : 0;
        return [`<a class="linkbtn" href="${applicationsUrl}?stage=${k}">${esc(label('stage', k))}</a>`, n(c),
          `<span class="bar-track" role="img" aria-label="${share}%"><i style="width:${share}%"></i></span><span class="num meta">${share}%</span>`];
      }, 'No applications yet.');

      const sems = Object.entries(s.by_semester || {});
      fillRows($('#sem-rows'), sems, ([sem, c]) => [`Semester ${esc(sem)}`, n(c)], 'No students for this batch.');
    }

    readFilters(document);
    $('#flt-graduation_year').addEventListener('change', load);
    load();
  })();
</script>
@endpush
