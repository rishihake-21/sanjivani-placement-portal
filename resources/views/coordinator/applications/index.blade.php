@extends('layouts.coordinator')

@section('title', 'Applications')
@section('nav', 'applications')

{{-- Controller passes: $branches (the department's branches: id, name). Read only; stages are moved by the TPO. --}}
@section('content')
  <x-ui.page-header title="Applications" meta="Where your students are in each drive. Stages are updated by the T&amp;P officer." />

  <x-ui.card id="list" title="All applications">
    <x-ui.filter-bar>
      <div class="field grow">
        <label for="flt-search">Search</label>
        <input id="flt-search" type="search" data-filter="search" placeholder="Name or university ID">
      </div>
      <x-ui.select filter name="stage" label="Stage" placeholder="All stages"
        :options="['APPLIED' => 'Applied', 'SHORTLISTED' => 'Shortlisted', 'APTITUDE' => 'Aptitude', 'TECHNICAL' => 'Technical', 'HR' => 'HR', 'SELECTED' => 'Selected', 'OFFER_RECEIVED' => 'Offer received', 'PLACED' => 'Placed', 'REJECTED' => 'Rejected', 'WITHDRAWN' => 'Withdrawn']" />
      <x-ui.select filter name="branch_id" label="Branch" placeholder="All" :options="$branches->pluck('name', 'id')->all()" />
      <input type="hidden" data-filter="drive_id" id="flt-drive_id">
    </x-ui.filter-bar>
    <div id="drive-chip" style="margin-bottom:16px"></div>

    <x-ui.table id="app-rows" :cols="['Student', 'Drive', 'Branch', 'Stage', 'Applied', 'Updated', '']" />
    <div class="pager" id="pager"></div>
  </x-ui.card>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fillRows, pager, badge, fmtDate, dash, fail, syncQuery, readFilters, filterValues, debounce } = TPMS;
    const base = @json(route('coordinator.applications'));
    const drivesBase = @json(route('coordinator.drives'));
    const studentsBase = @json(route('coordinator.students'));
    let page = 1;

    async function load() {
      const f = filterValues(document);
      syncQuery(Object.assign({}, f, { page: page > 1 ? page : '' }));
      $('#drive-chip').innerHTML = f.drive_id
        ? `<span class="tag">Drive #${esc(f.drive_id)}</span> <button type="button" class="linkbtn" id="clear-drive">Show all drives</button>` : '';
      try {
        const res = await api('/coordinator/applications', { query: Object.assign({}, f, { page }) });
        fillRows($('#app-rows'), res.data, (a) => [
          `<a class="linkbtn" href="${studentsBase}/${a.student.id}"><span class="name">${esc(a.student.full_name)}</span></a><span class="sub">${esc(a.student.university_id)}</span>`,
          `<a class="linkbtn" href="${drivesBase}/${a.drive.id}">${esc(a.drive.title)}</a><span class="sub">${esc(a.company || '')}</span>`,
          esc(a.student.branch || ''), badge('stage', a.stage),
          { html: fmtDate(a.applied_at), cls: 'nw' }, { html: a.stage_updated_at ? fmtDate(a.stage_updated_at) : dash, cls: 'nw' },
          { html: `<a class="btn btn-secondary" href="${base}/${a.id}">Open</a>`, cls: 'act' },
        ], 'No applications match these filters.');
        pager($('#pager'), res, (p) => { page = p; load(); });
      } catch (e) { fail(e); }
    }

    const reload = () => { page = 1; load(); };
    $('#flt-search').addEventListener('input', debounce(reload, 300));
    ['stage', 'branch_id'].forEach((k) => $('#flt-' + k).addEventListener('change', reload));
    document.addEventListener('click', (e) => { if (e.target.closest('#clear-drive')) { $('#flt-drive_id').value = ''; reload(); } });

    const p = readFilters(document);
    page = Number(p.page) || 1;
    load();
  })();
</script>
@endpush
