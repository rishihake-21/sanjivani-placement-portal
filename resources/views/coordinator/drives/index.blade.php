@extends('layouts.coordinator')

@section('title', 'Drives')
@section('nav', 'drives')

{{-- Controller passes: $years [int, ...]. Drives are managed by the TPO; this list is read only. --}}
@section('content')
  <x-ui.page-header title="Placement drives" meta="Drives your students can apply to. The T&amp;P officer manages them; this list is read only." />

  <x-ui.card id="list" title="All drives">
    <x-ui.filter-bar>
      <x-ui.select filter name="status" label="Status" placeholder="All" :options="['PUBLISHED' => 'Published', 'CLOSED' => 'Closed']" />
      <x-ui.select filter name="graduation_year" label="Batch" placeholder="All" :options="array_combine($years, $years)" />
    </x-ui.filter-bar>

    <x-ui.table id="drive-rows" :cols="['Drive', 'CTC', 'Deadline', 'Batch', 'Your applications', 'Status', '']" />
    <div class="pager" id="pager"></div>
  </x-ui.card>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fillRows, pager, badge, lpa, fmtDT, dash, fail, syncQuery, readFilters, filterValues } = TPMS;
    const base = @json(route('coordinator.drives'));
    let page = 1;

    async function load() {
      const f = filterValues(document);
      syncQuery(Object.assign({}, f, { page: page > 1 ? page : '' }));
      try {
        const res = await api('/coordinator/drives', { query: Object.assign({}, f, { page }) });
        fillRows($('#drive-rows'), res.data, (d) => [
          `<a class="linkbtn" href="${base}/${d.id}">${esc(d.title)}</a><span class="sub">${esc(d.company || '')}${d.location ? ' &middot; ' + esc(d.location) : ''}</span>`,
          d.ctc_lpa == null ? dash : lpa(d.ctc_lpa) + (d.ctc_max_lpa ? `<span class="sub">up to ${Number(d.ctc_max_lpa).toFixed(2)} LPA</span>` : ''),
          d.application_deadline ? fmtDT(d.application_deadline) : dash,
          `<span class="num">${esc(d.graduation_year)}</span>`,
          { html: `<span class="num">${esc(d.department_applications_count == null ? 0 : d.department_applications_count)}</span>`, cls: 'nw' },
          badge('drive', d.status),
          { html: `<a class="btn btn-secondary" href="${base}/${d.id}">Open</a>`, cls: 'act' },
        ], 'No drives match these filters.');
        pager($('#pager'), res, (p) => { page = p; load(); });
      } catch (e) { fail(e); }
    }

    const reload = () => { page = 1; load(); };
    ['status', 'graduation_year'].forEach((k) => $('#flt-' + k).addEventListener('change', reload));

    const p = readFilters(document);
    page = Number(p.page) || 1;
    load();
  })();
</script>
@endpush
