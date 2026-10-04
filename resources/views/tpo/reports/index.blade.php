@extends('layouts.tpo')

@section('title', 'Reports')
@section('nav', 'reports')

@section('content')
  <x-ui.page-header title="Reports" meta="Department, branch and company results, and CSV exports. Only verified placements count as placed.">
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

  <div class="stack">
    <x-ui.card id="sec-export" title="Export">
      <p class="meta" style="margin-bottom:16px">CSV files open in Excel or Google Sheets. They use the batch chosen above.</p>
      <div class="row wrap" style="align-items:flex-end">
        <div class="field" style="min-width:220px">
          <label for="flt-department_id">Department</label>
          <select id="flt-department_id" data-filter="department_id">
            <option value="">All departments</option>
            @foreach ($departments as $department)
              <option value="{{ $department->id }}">{{ $department->name }}</option>
            @endforeach
          </select>
        </div>
        <button type="button" class="btn btn-secondary" data-export="students">
          <svg class="icon sm" aria-hidden="true"><use href="#i-down"/></svg> Students and placement status
        </button>
        <button type="button" class="btn btn-secondary" data-export="placements">
          <svg class="icon sm" aria-hidden="true"><use href="#i-down"/></svg> Placement records
        </button>
      </div>
    </x-ui.card>

    <x-ui.card id="sec-departments" title="By department">
      <x-ui.table id="dept-rows" :cols="['Department', 'Students', 'Applied', 'Placed', 'Unplaced', 'Placement %', 'Highest CTC', 'Average CTC']" />
    </x-ui.card>

    <x-ui.card id="sec-branches" title="By branch">
      <x-ui.table id="branch-rows" :cols="['Branch', 'Department', 'Students', 'Applied', 'Placed', 'Unplaced', 'Placement %', 'Highest CTC', 'Average CTC']" />
    </x-ui.card>

    <x-ui.card id="sec-companies" title="By company">
      <x-ui.table id="company-rows" :cols="['Company', 'Drives', 'Applications', 'Selected', 'Placed', 'Highest CTC', 'Average CTC']" />
    </x-ui.card>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, $$, api, esc, fillRows, lpa, num2, dash, fail, download, syncQuery, readFilters, filterValues } = TPMS;
    const n = (v) => `<span class="num">${esc(v == null ? 0 : v)}</span>`;
    const pctCell = (v) => (v == null ? dash : n(num2(v) + '%'));

    async function load() {
      const f = filterValues(document);
      syncQuery(f);
      const q = { graduation_year: f.graduation_year };
      try {
        const [d, b, c] = await Promise.all([api('/tpo/reports/departments', { query: q }), api('/tpo/reports/branches', { query: q }), api('/tpo/reports/companies', { query: q })]);
        fillRows($('#dept-rows'), d.data, (r) => [`<span class="name">${esc(r.code)}</span><span class="sub">${esc(r.name)}</span>`, n(r.students), n(r.applied), n(r.placed), n(r.unplaced), pctCell(r.placement_percentage), lpa(r.highest_ctc_lpa), lpa(r.average_ctc_lpa)], 'No students for this batch.');
        fillRows($('#branch-rows'), b.data, (r) => [`<span class="name">${esc(r.code)}</span><span class="sub">${esc(r.name)}</span>`, esc(r.department || ''), n(r.students), n(r.applied), n(r.placed), n(r.unplaced), pctCell(r.placement_percentage), lpa(r.highest_ctc_lpa), lpa(r.average_ctc_lpa)], 'No students for this batch.');
        fillRows($('#company-rows'), c.data, (r) => [`<span class="name">${esc(r.company)}</span>`, n(r.drives), n(r.applications), n(r.students_selected), n(r.students_placed), lpa(r.highest_ctc_lpa), lpa(r.average_ctc_lpa)], 'No company has run a drive for this batch yet.');
      } catch (e) { fail(e); }
    }

    $$('[data-export]').forEach((b) => b.addEventListener('click', () => {
      const f = filterValues(document);
      const year = f.graduation_year ? '-' + f.graduation_year : '';
      download('/tpo/reports/' + b.dataset.export + '.csv', { graduation_year: f.graduation_year, department_id: f.department_id }, 'tpms-' + b.dataset.export + year + '.csv');
    }));
    $('#flt-graduation_year').addEventListener('change', load);
    $('#flt-department_id').addEventListener('change', () => syncQuery(filterValues(document)));

    readFilters(document);
    load();
  })();
</script>
@endpush
