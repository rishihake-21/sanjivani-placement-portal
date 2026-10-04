@extends('layouts.tpo')

@section('title', 'Drives')
@section('nav', 'drives')

@section('content')
  <x-ui.page-header title="Placement drives" meta="Create a drive, set who may apply, publish it, then follow the rounds.">
    <a class="btn btn-primary" href="{{ route('tpo.drives.create') }}">
      <svg class="icon sm" aria-hidden="true"><use href="#i-plus"/></svg> Create drive
    </a>
  </x-ui.page-header>

  <x-ui.card id="list" title="All drives">
    <x-ui.filter-bar>
      <div class="field grow">
        <label for="flt-search">Search</label>
        <input id="flt-search" type="search" data-filter="search" placeholder="Job title">
      </div>
      <x-ui.select filter name="status" label="Status" placeholder="All"
        :options="['DRAFT' => 'Draft', 'PUBLISHED' => 'Published', 'CLOSED' => 'Closed', 'CANCELLED' => 'Cancelled']" />
      <x-ui.select filter name="company_id" label="Company" placeholder="All" :options="$companies->pluck('name', 'id')->all()" />
      <x-ui.select filter name="graduation_year" label="Batch" placeholder="All" :options="array_combine($years, $years)" />
    </x-ui.filter-bar>

    <x-ui.table id="drive-rows" :cols="['Drive', 'CTC', 'Deadline', 'Batch', 'Applications', 'Status', '']" />
    <div class="pager" id="pager"></div>
  </x-ui.card>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fillRows, pager, badge, lpa, fmtDT, fmtDate, dash, fail, syncQuery, readFilters, filterValues, debounce } = TPMS;
    const base = @json(route('tpo.drives'));
    let page = 1;

    async function load() {
      const f = filterValues(document);
      syncQuery(Object.assign({}, f, { page: page > 1 ? page : '' }));
      try {
        const res = await api('/tpo/drives', { query: Object.assign({}, f, { page }) });
        fillRows($('#drive-rows'), res.data, (d) => [
          `<a class="linkbtn" href="${base}/${d.id}">${esc(d.title)}</a><span class="sub">${esc(d.company ? d.company.name : '')}${d.location ? ' &middot; ' + esc(d.location) : ''}</span>`,
          d.ctc_lpa == null ? dash : lpa(d.ctc_lpa) + (d.ctc_max_lpa ? `<span class="sub">up to ${Number(d.ctc_max_lpa).toFixed(2)} LPA</span>` : ''),
          d.application_deadline ? fmtDT(d.application_deadline) : dash,
          `<span class="num">${esc(d.graduation_year)}</span>`,
          { html: `<span class="num">${esc(d.applications_count)}</span>`, cls: 'nw' },
          badge('drive', d.status),
          { html: `<a class="btn btn-secondary" href="${base}/${d.id}">Open</a>`, cls: 'act' },
        ], 'No drives match these filters.');
        pager($('#pager'), res, (p) => { page = p; load(); });
      } catch (e) { fail(e); }
    }

    const reload = () => { page = 1; load(); };
    $('#flt-search').addEventListener('input', debounce(reload, 300));
    ['status', 'company_id', 'graduation_year'].forEach((k) => $('#flt-' + k).addEventListener('change', reload));

    const p = readFilters(document);
    page = Number(p.page) || 1;
    load();
  })();
</script>
@endpush
