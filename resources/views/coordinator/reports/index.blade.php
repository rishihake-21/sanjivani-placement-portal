@extends('layouts.coordinator')

@section('title', 'Reports')
@section('nav', 'reports')

{{-- Controller passes: $department, $years [int, ...]. Data: GET /api/coordinator/reports/branch-summary and the two CSV exports. --}}
@section('content')
  <x-ui.page-header title="Reports" :meta="e($department['name']) . ' results by branch, and CSV exports.'">
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
      <div class="row wrap">
        <button type="button" class="btn btn-secondary" data-export="students">
          <svg class="icon sm" aria-hidden="true"><use href="#i-down"/></svg> Students and placement status
        </button>
        <button type="button" class="btn btn-secondary" data-export="placements">
          <svg class="icon sm" aria-hidden="true"><use href="#i-down"/></svg> Placement records
        </button>
      </div>
    </x-ui.card>

    <x-ui.card id="sec-branches" title="By branch">
      <x-ui.table id="branch-rows" :cols="['Branch', 'Students', 'Opted out', 'Applied', 'Placed', 'Unplaced', 'Placement %', 'Highest CTC', 'Average CTC']" />
    </x-ui.card>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, $$, api, esc, fillRows, lpa, num2, dash, fail, download, syncQuery, readFilters, filterValues } = TPMS;
    const n = (v) => `<span class="num">${esc(v == null ? 0 : v)}</span>`;

    async function load() {
      const f = filterValues(document);
      syncQuery(f);
      try {
        const res = await api('/coordinator/reports/branch-summary', { query: { graduation_year: f.graduation_year } });
        fillRows($('#branch-rows'), res.data, (r) => [
          `<span class="name">${esc(r.code)}</span><span class="sub">${esc(r.name)}</span>`, n(r.students), n(r.opted_out), n(r.applied), n(r.placed), n(r.unplaced),
          r.placement_percentage == null ? dash : n(num2(r.placement_percentage) + '%'), lpa(r.highest_ctc_lpa), lpa(r.average_ctc_lpa),
        ], 'No students for this batch.');
      } catch (e) { fail(e); }
    }

    $$('[data-export]').forEach((b) => b.addEventListener('click', () => {
      const year = filterValues(document).graduation_year;
      download('/coordinator/reports/' + b.dataset.export + '.csv', { graduation_year: year }, 'department-' + b.dataset.export + (year ? '-' + year : '') + '.csv');
    }));
    $('#flt-graduation_year').addEventListener('change', load);

    readFilters(document);
    load();
  })();
</script>
@endpush
