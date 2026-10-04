@extends('layouts.tpo')

@section('title', 'Placements')
@section('nav', 'placements')

@section('content')
  <x-ui.page-header title="Placements" meta="Official placement records. An offer becomes official only when you verify it. Students and coordinators can only read these.">
    <button type="button" class="btn btn-primary" id="add-placement">
      <svg class="icon sm" aria-hidden="true"><use href="#i-plus"/></svg> Add off-campus placement
    </button>
  </x-ui.page-header>

  <x-ui.card id="list" title="All placements">
    <x-ui.filter-bar>
      <x-ui.select filter name="status" label="Status" placeholder="All"
        :options="['OFFERED' => 'Offered, not verified', 'PLACED' => 'Placed', 'DECLINED' => 'Declined']" />
      <x-ui.select filter name="company_id" label="Company" placeholder="All" :options="$companies->pluck('name', 'id')->all()" />
      <x-ui.select filter name="department_id" label="Department" placeholder="All" :options="$departments->pluck('name', 'id')->all()" />
      <x-ui.select filter name="graduation_year" label="Batch" placeholder="All" :options="array_combine($years, $years)" />
    </x-ui.filter-bar>

    <x-ui.table id="pl-rows" :cols="['Student', 'Company', 'Role', 'CTC', 'Offer date', 'Status', '']" />
    <div class="pager" id="pager"></div>
  </x-ui.card>
@endsection

@push('scripts')
<script>
  (function () {
    const T = TPMS;
    const { $, api, esc, fld, input, options, fillRows, pager, badge, lpa, fmtDate, dash, notice, openDrawer, closeDrawer, collect, clearErrors,
            fail, toast, confirmAction, syncQuery, readFilters, filterValues, debounce, num2 } = T;
    const companies = @json($companies->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values());
    const appBase = @json(url('/tpo/applications'));
    let page = 1;
    let items = [];

    async function load() {
      const f = filterValues(document);
      syncQuery(Object.assign({}, f, { page: page > 1 ? page : '' }));
      try {
        const res = await api('/tpo/placements', { query: Object.assign({}, f, { page }) });
        items = res.data;
        fillRows($('#pl-rows'), items, (p) => [
          `<span class="name">${esc(p.student.full_name)}</span><span class="sub">${esc(p.student.university_id)} &middot; ${esc(p.student.branch || '')}</span>`,
          esc(p.company || ''), esc(p.role_title), lpa(p.ctc_lpa), p.offer_date ? fmtDate(p.offer_date) : dash,
          `<div class="stk">${badge('placement', p.status)}${p.application_id ? '' : '<span class="tag">Off-campus</span>'}</div>`,
          { html: actions(p), cls: 'act' },
        ], 'No placements match these filters.');
        pager($('#pager'), res, (n) => { page = n; load(); });
      } catch (e) { fail(e); }
    }

    function actions(p) {
      const out = [];
      if (p.status === 'OFFERED') out.push(`<button type="button" class="btn btn-primary" data-verify="${p.id}">Verify</button>`, `<button type="button" class="btn btn-danger" data-decline="${p.id}">Declined</button>`);
      if (p.status !== 'DECLINED') out.push(`<button type="button" class="btn btn-secondary" data-edit="${p.id}">Edit</button>`);
      if (p.application_id) out.push(`<a class="btn btn-secondary" href="${appBase}/${p.application_id}">Application</a>`);
      return out.join(' ');
    }

    $('#pl-rows').addEventListener('click', async (e) => {
      const verify = e.target.closest('[data-verify]');
      const decline = e.target.closest('[data-decline]');
      const edit = e.target.closest('[data-edit]');
      if (verify) {
        const p = items.find((x) => String(x.id) === verify.dataset.verify);
        const ok = await confirmAction({ title: 'Verify placement', kind: 'wait', confirmLabel: 'Verify',
          message: `Make ${esc(p.student.full_name)}'s placement at <b>${esc(p.company)}</b> official. It then counts in every placement statistic and the student cannot apply to further drives unless a drive allows placed students.` });
        if (!ok) return;
        try { await api('/tpo/placements/' + p.id + '/verify', { method: 'POST' }); toast('Placement verified.'); load(); } catch (err) { fail(err); }
      } else if (decline) {
        const p = items.find((x) => String(x.id) === decline.dataset.decline);
        const res = await confirmAction({ title: 'Offer declined', kind: 'no', danger: true, confirmLabel: 'Mark as declined',
          message: `${esc(p.student.full_name)} did not accept the offer from <b>${esc(p.company)}</b>. The application is marked rejected.`,
          reason: { label: 'Remarks', required: false, hint: 'Optional.' } });
        if (!res) return;
        try { await api('/tpo/placements/' + p.id + '/decline', { method: 'POST', body: { remarks: res.reason } }); toast('Offer marked as declined.'); load(); } catch (err) { fail(err); }
      } else if (edit) {
        openEdit(items.find((x) => String(x.id) === edit.dataset.edit));
      }
    });

    /* ---------------- edit ---------------- */
    function openEdit(p) {
      const official = p.status === 'PLACED';
      const dis = official ? ' disabled' : '';
      openDrawer({
        title: 'Edit placement', meta: esc(p.student.full_name) + ' &middot; ' + esc(p.company),
        body: `<div data-summary></div>
          ${official ? notice('wait', 'lock', 'This placement is verified. Only the location and joining date can change.') : ''}
          <div class="dfg">
            ${fld('f-role_title', 'Role', input('role_title', 'text', p.role_title, 'maxlength="150"' + dis), { cls: 'full' })}
            ${fld('f-ctc_lpa', 'CTC (LPA)', input('ctc_lpa', 'number', p.ctc_lpa, 'step="0.01" min="0"' + dis))}
            ${fld('f-location', 'Location', input('location', 'text', p.location, 'maxlength="150"'))}
            ${fld('f-offer_date', 'Offer date', input('offer_date', 'date', p.offer_date, dis))}
            ${fld('f-joining_date', 'Joining date', input('joining_date', 'date', p.joining_date, ''))}
          </div>`,
        footer: '<span class="sp"></span><button type="button" class="btn btn-secondary" data-close>Cancel</button><button type="button" class="btn btn-primary" data-save>Save changes</button>',
        onMount: (el) => el.addEventListener('click', async (ev) => {
          const b = ev.target.closest('[data-save]');
          if (!b) return;
          clearErrors(el);
          b.disabled = true;
          try { await api('/tpo/placements/' + p.id, { method: 'PATCH', body: collect(el) }); closeDrawer(); toast('Placement updated.'); load(); } catch (err) { fail(err, el); } finally { b.disabled = false; }
        }),
      });
    }

    /* ---------------- off-campus placement ---------------- */
    let picked = null;
    $('#add-placement').addEventListener('click', () => {
      picked = null;
      openDrawer({
        title: 'Add off-campus placement', meta: 'For offers that did not come through a campus drive.',
        body: `<div data-summary></div>
          <div class="field" data-err="student_id"><label for="stu-search">Student <span class="req" aria-hidden="true">*</span></label>
            <input id="stu-search" type="search" placeholder="Search by name or university ID" autocomplete="off">
            <div class="pick" id="stu-list" hidden></div><span class="hint" id="stu-picked">No student chosen.</span><span class="err" role="alert" hidden></span></div>
          <div class="dfg">
            ${fld('f-company_id', 'Company', '<select id="f-company_id" data-f="company_id">' + options(companies.map((c) => [c.id, c.name]), '', 'Choose a company') + '</select>', { req: true, cls: 'full' })}
            ${fld('f-role_title', 'Role', input('role_title', 'text', '', 'maxlength="150"'), { req: true, cls: 'full' })}
            ${fld('f-ctc_lpa', 'CTC (LPA)', input('ctc_lpa', 'number', '', 'step="0.01" min="0"'))}
            ${fld('f-location', 'Location', input('location', 'text', '', 'maxlength="150"'))}
            ${fld('f-offer_date', 'Offer date', input('offer_date', 'date', '', ''), { req: true })}
            ${fld('f-joining_date', 'Joining date', input('joining_date', 'date', '', ''))}
            ${fld('f-status', 'Status', '<select id="f-status" data-f="status"><option value="PLACED">Placed (verified now)</option><option value="OFFERED">Offered, verify later</option></select>', { cls: 'full', hint: 'You are the verifier. Placed records count in statistics immediately.' })}
          </div>`,
        footer: '<span class="sp"></span><button type="button" class="btn btn-secondary" data-close>Cancel</button><button type="button" class="btn btn-primary" data-save>Add placement</button>',
        onMount: (el) => {
          const list = $('#stu-list', el);
          $('#stu-search', el).addEventListener('input', debounce(async (ev) => {
            const q = ev.target.value.trim();
            if (q.length < 2) { list.hidden = true; return; }
            try {
              const r = await api('/tpo/students', { query: { search: q } });
              list.hidden = false;
              list.innerHTML = r.data.length ? r.data.slice(0, 8).map((s) => `<div class="li" role="option" tabindex="0" data-pick="${s.id}" data-name="${esc(s.full_name)}" data-uid="${esc(s.university_id)}"><div class="l"><div class="t">${esc(s.full_name)}</div><div class="meta">${esc(s.university_id)} &middot; ${esc(s.branch || '')}</div></div></div>`).join('') : '<p class="empty" style="padding:8px 12px">No student found.</p>';
            } catch (err) { fail(err); }
          }, 300));
          el.addEventListener('click', async (ev) => {
            const pick = ev.target.closest('[data-pick]');
            if (pick) {
              picked = Number(pick.dataset.pick);
              $('#stu-picked', el).textContent = pick.dataset.name + ' (' + pick.dataset.uid + ')';
              list.hidden = true;
              return;
            }
            const b = ev.target.closest('[data-save]');
            if (!b) return;
            clearErrors(el);
            const body = collect(el);
            body.student_id = picked;
            b.disabled = true;
            try { await api('/tpo/placements', { method: 'POST', body }); closeDrawer(); toast('Placement added.'); load(); } catch (err) { fail(err, el); } finally { b.disabled = false; }
          });
        },
      });
    });

    const reload = () => { page = 1; load(); };
    ['status', 'company_id', 'department_id', 'graduation_year'].forEach((k) => $('#flt-' + k).addEventListener('change', reload));
    const q = readFilters(document);
    page = Number(q.page) || 1;
    load();
  })();
</script>
@endpush
