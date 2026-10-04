@extends('layouts.tpo')

@section('title', 'Applications')
@section('nav', 'applications')

@section('content')
  <x-ui.page-header title="Applications" meta="Every student application across all drives. Open one to move it through the rounds." />

  <x-ui.card id="list" title="All applications">
    <x-ui.filter-bar>
      <div class="field grow">
        <label for="flt-search">Search</label>
        <input id="flt-search" type="search" data-filter="search" placeholder="Name or university ID">
      </div>
      <x-ui.select filter name="stage" label="Stage" placeholder="All stages" :options="$stages" />
      <x-ui.select filter name="company_id" label="Company" placeholder="All" :options="$companies->pluck('name', 'id')->all()" />
      <x-ui.select filter name="department_id" label="Department" placeholder="All" :options="$departments->pluck('name', 'id')->all()" />
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
    const { $, api, esc, fillRows, pager, badge, fmtDate, fail, syncQuery, readFilters, filterValues, debounce } = TPMS;
    const base = @json(url('/tpo/applications'));
    const drivesBase = @json(route('tpo.drives'));
    let page = 1;

    async function load() {
      const f = filterValues(document);
      syncQuery(Object.assign({}, f, { page: page > 1 ? page : '' }));
      $('#drive-chip').innerHTML = f.drive_id
        ? `<span class="tag">Drive #${esc(f.drive_id)}</span> <button type="button" class="linkbtn" id="clear-drive">Show all drives</button>` : '';
      try {
        const res = await api('/tpo/applications', { query: Object.assign({}, f, { page }) });
        fillRows($('#app-rows'), res.data, (a) => [
          `<span class="name">${esc(a.student.full_name)}</span><span class="sub">${esc(a.student.university_id)}</span>`,
          `<a class="linkbtn" href="${drivesBase}/${a.drive.id}">${esc(a.drive.title)}</a><span class="sub">${esc(a.company || '')}</span>`,
          esc(a.student.branch || ''), badge('stage', a.stage), fmtDate(a.applied_at), fmtDate(a.stage_updated_at),
          { html: `<a class="btn btn-secondary" href="${base}/${a.id}">Open</a>`, cls: 'act' },
        ], 'No applications match these filters.');
        pager($('#pager'), res, (p) => { page = p; load(); });
      } catch (e) { fail(e); }
    }

    const reload = () => { page = 1; load(); };
    $('#flt-search').addEventListener('input', debounce(reload, 300));
    ['stage', 'company_id', 'department_id'].forEach((k) => $('#flt-' + k).addEventListener('change', reload));
    document.addEventListener('click', (e) => { if (e.target.closest('#clear-drive')) { $('#flt-drive_id').value = ''; reload(); } });

    const p = readFilters(document);
    page = Number(p.page) || 1;
    load();
  })();
</script>
@endpush
