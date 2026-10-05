@extends('layouts.coordinator')

@section('title', 'Re-upload requests')
@section('nav', 'reuploads')

@section('content')
  <x-ui.page-header title="Re-upload requests" meta="Items you sent back to a student. A request closes when the student uploads again. Make a new request from the student's page." />

  <x-ui.card id="list" title="All requests">
    <x-ui.filter-bar>
      <x-ui.select filter name="status" label="Status" placeholder="All" :options="['OPEN' => 'Open', 'FULFILLED' => 'Fulfilled', 'CANCELLED' => 'Cancelled']" />
    </x-ui.filter-bar>

    <x-ui.table id="req-rows" :cols="['Student', 'Item', 'Reason', 'Status', 'Requested', 'Closed', '']" />
    <div class="pager" id="pager"></div>
  </x-ui.card>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fillRows, pager, fmtDate, dash, fail, toast, confirmAction, syncQuery, readFilters, filterValues } = TPMS;
    const C = TPMS.C;
    const studentsUrl = @json(route('coordinator.students'));
    const SUBJECT = { ACADEMIC_RECORD: 'Academic record', EXPERIENCE: 'Experience', DOCUMENT: 'Document' };
    let page = 1;
    let items = [];

    async function load() {
      const f = filterValues(document);
      syncQuery(Object.assign({}, f, { page: page > 1 ? page : '' }));
      try {
        const res = await api('/coordinator/reupload-requests', { query: Object.assign({}, f, { page }) });
        items = res.data;
        fillRows($('#req-rows'), items, (r) => [
          `<a class="linkbtn" href="${studentsUrl}/${r.student.id}"><span class="name">${esc(r.student.full_name)}</span></a><span class="sub">${esc(r.student.university_id)}</span>`,
          `<span class="name">${esc(SUBJECT[r.subject_type] || r.subject_type)}</span><span class="sub">#${esc(r.subject_id)}</span>`,
          esc(r.reason), C.badge('req', r.status),
          { html: fmtDate(r.created_at), cls: 'nw' }, { html: r.closed_at ? fmtDate(r.closed_at) : dash, cls: 'nw' },
          { html: r.status === 'OPEN' ? `<button type="button" class="btn btn-secondary" data-cancel="${r.id}">Cancel request</button>` : '', cls: 'act' },
        ], 'No requests match this filter.');
        pager($('#pager'), res, (p) => { page = p; load(); });
      } catch (e) { fail(e); }
    }

    $('#req-rows').addEventListener('click', async (e) => {
      const b = e.target.closest('[data-cancel]');
      if (!b) return;
      const r = items.find((x) => String(x.id) === b.dataset.cancel);
      const ok = await confirmAction({
        title: 'Cancel re-upload request',
        message: `The request to ${esc(r.student.full_name)} will be closed. The student is not asked to upload this item any more.`,
        confirmLabel: 'Cancel request',
        danger: true,
      });
      if (!ok) return;
      try {
        await api('/coordinator/reupload-requests/' + r.id + '/cancel', { method: 'POST' });
        toast('Re-upload request cancelled.');
        load();
      } catch (err) { fail(err); }
    });

    $('#flt-status').addEventListener('change', () => { page = 1; load(); });

    const p = readFilters(document);
    page = Number(p.page) || 1;
    load();
  })();
</script>
@endpush
