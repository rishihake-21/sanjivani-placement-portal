@extends('layouts.coordinator')

@section('title', 'Students')
@section('nav', 'students')

{{-- Controller passes: $department ['id','name','code'], $branches (the department's branches: id, name). --}}
@section('content')
  <x-ui.page-header title="Students" :meta="e($department['name']) . '. Open a student to see the full profile and every record.'" />

  <x-ui.card id="list" title="Directory">
    <x-ui.filter-bar>
      <div class="field grow">
        <label for="flt-search">Search</label>
        <input id="flt-search" type="search" data-filter="search" placeholder="Name or university ID">
      </div>
      <x-ui.select filter name="branch_id" label="Branch" placeholder="All" :options="$branches->pluck('name', 'id')->all()" />
      <x-ui.select filter name="semester" label="Semester" placeholder="All" :options="array_combine(range(1, 8), range(1, 8))" />
      <x-ui.select filter name="admission_type" label="Admission" placeholder="All" :options="['REGULAR' => 'Regular', 'LATERAL' => 'Lateral']" />
      <div class="field">
        <label class="chk" for="flt-only_pending" style="height:40px">
          <input type="checkbox" id="flt-only_pending">
          <span>Only with pending reviews</span>
        </label>
      </div>
    </x-ui.filter-bar>

    <x-ui.table id="student-rows" :cols="['University ID', 'Name', 'Branch', 'Admission', 'Semester', 'Batch', 'Pending reviews', '']" />
    <div class="pager" id="pager"></div>
  </x-ui.card>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fillRows, pager, fail, syncQuery, readFilters, filterValues, debounce, dash } = TPMS;
    const C = TPMS.C;
    const base = @json(route('coordinator.students'));
    let page = 1;

    const query = () => Object.assign({}, filterValues(document), { only_pending: $('#flt-only_pending').checked ? '1' : '' });

    async function load() {
      const f = query();
      syncQuery(Object.assign({}, f, { page: page > 1 ? page : '' }));
      try {
        const res = await api('/coordinator/students', { query: Object.assign({}, f, { page }) });
        fillRows($('#student-rows'), res.data, (s) => [
          { html: esc(s.university_id), cls: 'nw' },
          `<a class="linkbtn" href="${base}/${s.id}"><span class="name">${esc(s.full_name)}</span></a>`,
          esc(s.branch || ''), esc(C.ADMISSION[s.admission_type] || s.admission_type),
          `<span class="num">${esc(s.current_semester)}</span>`, `<span class="num">${esc(s.graduation_year)}</span>`,
          s.pending_reviews ? `<span class="badge b-wait">${esc(s.pending_reviews)}</span>` : dash,
          { html: `<a class="btn btn-secondary" href="${base}/${s.id}">Open</a>`, cls: 'act' },
        ], 'No students match these filters.');
        pager($('#pager'), res, (p) => { page = p; load(); });
      } catch (e) { fail(e); }
    }

    const reload = () => { page = 1; load(); };
    $('#flt-search').addEventListener('input', debounce(reload, 300));
    ['branch_id', 'semester', 'admission_type'].forEach((k) => $('#flt-' + k).addEventListener('change', reload));
    $('#flt-only_pending').addEventListener('change', reload);

    const p = readFilters(document);
    $('#flt-only_pending').checked = p.only_pending === '1';
    page = Number(p.page) || 1;
    load();
  })();
</script>
@endpush
