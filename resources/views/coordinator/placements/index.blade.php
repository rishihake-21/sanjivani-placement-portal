@extends('layouts.coordinator')

@section('title', 'Placements')
@section('nav', 'placements')

{{-- Controller passes: $branches (the department's branches), $years [int, ...], optional $companies (id, name). Read only; the TPO maintains placements. --}}
@section('content')
  <x-ui.page-header title="Placements" meta="Offers and placements of your students, newest offer first. Only the T&amp;P officer can verify or change them." />

  <x-ui.card id="list" title="All placements">
    <x-ui.filter-bar>
      <x-ui.select filter name="status" label="Status" placeholder="All" :options="['OFFERED' => 'Offered, not verified', 'PLACED' => 'Placed', 'DECLINED' => 'Declined']" />
      <x-ui.select filter name="branch_id" label="Branch" placeholder="All" :options="$branches->pluck('name', 'id')->all()" />
      @if (isset($companies))
        <x-ui.select filter name="company_id" label="Company" placeholder="All" :options="$companies->pluck('name', 'id')->all()" />
      @else
        <input type="hidden" data-filter="company_id" id="flt-company_id">
      @endif
      <x-ui.select filter name="graduation_year" label="Batch" placeholder="All" :options="array_combine($years, $years)" />
    </x-ui.filter-bar>

    <x-ui.table id="plc-rows" :cols="['Student', 'Company', 'Role', 'CTC', 'Location', 'Offer date', 'Joining date', 'Status']" />
    <div class="pager" id="pager"></div>
  </x-ui.card>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fillRows, pager, badge, lpa, fmtDate, dash, fail, syncQuery, readFilters, filterValues } = TPMS;
    const studentsBase = @json(route('coordinator.students'));
    let page = 1;

    async function load() {
      const f = filterValues(document);
      syncQuery(Object.assign({}, f, { page: page > 1 ? page : '' }));
      try {
        const res = await api('/coordinator/placements', { query: Object.assign({}, f, { page }) });
        fillRows($('#plc-rows'), res.data, (p) => [
          `<a class="linkbtn" href="${studentsBase}/${p.student.id}"><span class="name">${esc(p.student.full_name)}</span></a><span class="sub">${esc(p.student.university_id)}${p.student.branch ? ' &middot; ' + esc(p.student.branch) : ''}</span>`,
          `<span class="name">${esc(p.company)}</span>`, p.role_title ? esc(p.role_title) : dash, lpa(p.ctc_lpa), p.location ? esc(p.location) : dash,
          { html: p.offer_date ? fmtDate(p.offer_date) : dash, cls: 'nw' }, { html: p.joining_date ? fmtDate(p.joining_date) : dash, cls: 'nw' },
          badge('placement', p.status),
        ], 'No placements match these filters.');
        pager($('#pager'), res, (n) => { page = n; load(); });
      } catch (e) { fail(e); }
    }

    const reload = () => { page = 1; load(); };
    ['status', 'branch_id', 'graduation_year'].forEach((k) => $('#flt-' + k).addEventListener('change', reload));
    if ($('#flt-company_id').tagName === 'SELECT') $('#flt-company_id').addEventListener('change', reload);

    const p = readFilters(document);
    page = Number(p.page) || 1;
    load();
  })();
</script>
@endpush
