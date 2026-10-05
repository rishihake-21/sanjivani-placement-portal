@extends('layouts.coordinator')

@section('title', 'Verification queue')
@section('nav', 'queue')

{{-- Controller passes: $department ['id','name','code']. The queue itself comes from GET /api/coordinator/verification-queue. --}}
@section('content')
  <x-ui.page-header :title="$department['name'] . ' verification'" meta="Oldest submissions first. Open an item to see the file and the submitted details side by side, then approve or reject." />

  <div id="queue-error"></div>

  <div class="stack">
    <x-ui.card id="summary" title="Waiting for review">
      <div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <x-ui.stat id="s-acad" label="Academic records" />
        <x-ui.stat id="s-exp" label="Experiences" />
        <x-ui.stat id="s-doc" label="Documents" />
      </div>
    </x-ui.card>

    <x-ui.card id="queue" title="Queue">
      <x-ui.filter-bar>
        <x-ui.select filter name="type" label="Type" placeholder="All types" :options="['academic' => 'Academic record', 'experience' => 'Experience', 'document' => 'Document']" />
        <div class="field grow">
          <label for="flt-search">Student name or ID</label>
          <input id="flt-search" type="search" data-filter="search" placeholder="Search the queue">
        </div>
      </x-ui.filter-bar>

      <x-ui.table id="queue-rows" :cols="['Type', 'Student', 'Item', 'Submitted', '']" />
      <p class="meta" id="queue-note" style="margin-top:12px" hidden>Each list shows the oldest 100 items. Review some to see newer ones.</p>
    </x-ui.card>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fillRows, fmtDate, notice, fail, readFilters, filterValues, debounce } = TPMS;
    const C = TPMS.C;
    const studentsUrl = @json(route('coordinator.students'));
    let items = [];
    let shown = [];

    async function load() {
      $('#queue-error').innerHTML = '';
      try {
        const q = await api('/coordinator/verification-queue');
        const acad = q.academic_records || [], exps = q.experiences || [], docs = q.documents || [];
        items = [].concat(
          acad.map((i) => ({ kind: 'academic', item: i, type: 'Academic record', title: i.label, sub: '', at: i.submitted_at, rev: !!i.previous_verified })),
          exps.map((i) => ({ kind: 'experience', item: i, type: 'Experience', title: C.expType(i.type) + ' at ' + i.organization, sub: i.role_title || '', at: i.submitted_at, rev: !!i.previous_verified })),
          docs.map((i) => ({ kind: 'document', item: i, type: 'Document', title: C.docType(i.document_type), sub: i.original_name || '', at: i.uploaded_at, rev: false })),
        ).sort((a, b) => (a.at ? new Date(a.at).getTime() : Infinity) - (b.at ? new Date(b.at).getTime() : Infinity));

        const cap = (list) => (list.length >= 100 ? '100+' : list.length);
        $('#s-acad').innerHTML = `<span class="num">${cap(acad)}</span>`;
        $('#s-exp').innerHTML = `<span class="num">${cap(exps)}</span>`;
        $('#s-doc').innerHTML = `<span class="num">${cap(docs)}</span>`;
        $('#queue-note').hidden = !(acad.length >= 100 || exps.length >= 100 || docs.length >= 100);
        draw();
      } catch (e) {
        $('#queue-error').innerHTML = '<div style="margin-bottom:24px">' + notice('no', 'info', esc(e.message)) + '</div>';
        fail(e);
      }
    }

    function draw() {
      const f = filterValues(document);
      const needle = (f.search || '').toLowerCase();
      shown = items.filter((r) => (!f.type || r.kind === f.type)
        && (!needle || (r.item.student.full_name + ' ' + r.item.student.university_id + ' ' + r.title).toLowerCase().includes(needle)));
      fillRows($('#queue-rows'), shown, (r, i) => [
        esc(r.type),
        `<a class="linkbtn" href="${studentsUrl}/${r.item.student.id}"><span class="name">${esc(r.item.student.full_name)}</span></a><span class="sub">${esc(r.item.student.university_id)}</span>`,
        `<span class="name">${esc(r.title)}</span>${r.rev ? ' <span class="tag">Revision</span>' : ''}${r.sub ? `<span class="sub">${esc(r.sub)}</span>` : ''}`,
        { html: r.at ? fmtDate(r.at) : TPMS.dash, cls: 'nw' },
        { html: `<button type="button" class="btn btn-primary" data-review="${shown.indexOf(r)}">Review</button>`, cls: 'act' },
      ], items.length ? 'No items match the filters.' : 'Nothing is waiting for review.');
    }

    $('#queue-rows').addEventListener('click', (e) => {
      const b = e.target.closest('[data-review]');
      if (!b) return;
      const r = shown[Number(b.dataset.review)];
      if (r) C.reviewDrawer({ kind: r.kind, item: r.item, onDone: load });
    });
    $('#flt-search').addEventListener('input', debounce(draw, 150));
    $('#flt-type').addEventListener('change', draw);

    readFilters(document);
    load();
  })();
</script>
@endpush
