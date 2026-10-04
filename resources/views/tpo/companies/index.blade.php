@extends('layouts.tpo')

@section('title', 'Companies')
@section('nav', 'companies')

@section('content')
  <x-ui.page-header title="Companies" meta="Recruiters that run placement drives. Names are unique; deactivate a company instead of deleting it.">
    <button type="button" class="btn btn-primary" id="add-company">
      <svg class="icon sm" aria-hidden="true"><use href="#i-plus"/></svg> Add company
    </button>
  </x-ui.page-header>

  <x-ui.card id="list" title="All companies">
    <x-ui.filter-bar>
      <div class="field grow">
        <label for="flt-search">Search</label>
        <input id="flt-search" type="search" data-filter="search" placeholder="Company name">
      </div>
      <x-ui.select filter name="active" label="Status" placeholder="All" :options="['1' => 'Active', '0' => 'Inactive']" />
    </x-ui.filter-bar>

    <x-ui.table id="company-rows" :cols="['Company', 'Contact', 'Drives', 'Status', '']" />
    <div class="pager" id="pager"></div>
  </x-ui.card>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fld, input, textarea, fillRows, pager, badge, openDrawer, closeDrawer, collect, showErrors, clearErrors,
            fail, toast, notice, params, syncQuery, readFilters, filterValues, debounce, orDash } = TPMS;
    const drivesUrl = @json(route('tpo.drives'));
    let page = 1;
    let items = [];

    async function load() {
      const f = filterValues(document);
      syncQuery(Object.assign({}, f, { page: page > 1 ? page : '' }));
      try {
        const res = await api('/tpo/companies', { query: Object.assign({}, f, { page }) });
        items = res.data;
        fillRows($('#company-rows'), items, (c) => [
          `<span class="name">${esc(c.name)}</span>${c.industry ? `<span class="sub">${esc(c.industry)}</span>` : ''}${c.website ? `<span class="sub">${esc(c.website)}</span>` : ''}`,
          c.contact_name || c.contact_email || c.contact_phone
            ? `${esc(c.contact_name || '')}${c.contact_email ? `<span class="sub">${esc(c.contact_email)}</span>` : ''}${c.contact_phone ? `<span class="sub">${esc(c.contact_phone)}</span>` : ''}`
            : TPMS.dash,
          `<span class="num">${esc(c.drives_count)}</span>`,
          badge('company', String(!!c.is_active)),
          { html: `<a class="btn btn-secondary" href="${drivesUrl}?company_id=${c.id}">Drives</a> <button type="button" class="btn btn-secondary" data-edit="${c.id}">Edit</button>`, cls: 'act' },
        ], 'No companies match these filters.');
        pager($('#pager'), res, (p) => { page = p; load(); });
      } catch (e) { fail(e); }
    }

    /* create / edit drawer */
    function form(c) {
      const v = (k) => (c ? c[k] : '');
      return `<div data-summary></div>
        <div class="dfg">
          ${fld('f-name', 'Company name', input('name', 'text', v('name'), 'maxlength="150" required'), { req: true, cls: 'full' })}
          ${fld('f-industry', 'Industry', input('industry', 'text', v('industry'), 'maxlength="100"'))}
          ${fld('f-website', 'Website', input('website', 'url', v('website'), 'maxlength="255" placeholder="https://"'))}
          ${fld('f-contact_name', 'Contact person', input('contact_name', 'text', v('contact_name'), 'maxlength="120"'))}
          ${fld('f-contact_phone', 'Contact phone', input('contact_phone', 'tel', v('contact_phone'), 'maxlength="20"'))}
          ${fld('f-contact_email', 'Contact email', input('contact_email', 'email', v('contact_email'), 'maxlength="190"'), { cls: 'full' })}
          ${fld('f-notes', 'Notes', textarea('notes', v('notes'), 'maxlength="2000"'), { cls: 'full', hint: 'Internal only. Students do not see this.' })}
          ${c ? `<div class="field full"><label class="chk" for="f-is_active"><input type="checkbox" id="f-is_active" data-f="is_active"${c.is_active ? ' checked' : ''}><span>Company is active</span></label>
                 <span class="hint">Inactive companies cannot be used for new drives.</span><span class="err" role="alert" hidden></span></div>` : ''}
        </div>`;
    }

    function openForm(c) {
      openDrawer({
        title: c ? 'Edit company' : 'Add company',
        meta: c ? esc(c.name) : null,
        body: form(c),
        footer: `<span class="sp"></span><button type="button" class="btn btn-secondary" data-close>Cancel</button><button type="button" class="btn btn-primary" data-save>${c ? 'Save changes' : 'Add company'}</button>`,
        onMount: (el) => el.addEventListener('click', async (e) => {
          const b = e.target.closest('[data-save]');
          if (!b) return;
          clearErrors(el);
          b.disabled = true;
          try {
            const payload = collect(el);
            if (c) await api('/tpo/companies/' + c.id, { method: 'PATCH', body: payload });
            else await api('/tpo/companies', { method: 'POST', body: payload });
            closeDrawer();
            toast(c ? 'Company updated.' : 'Company added.');
            load();
          } catch (err) { fail(err, el); } finally { b.disabled = false; }
        }),
      });
    }

    $('#add-company').addEventListener('click', () => openForm(null));
    $('#company-rows').addEventListener('click', (e) => {
      const b = e.target.closest('[data-edit]');
      if (b) openForm(items.find((c) => String(c.id) === b.dataset.edit));
    });
    const reload = () => { page = 1; load(); };
    $('#flt-search').addEventListener('input', debounce(reload, 300));
    $('#flt-active').addEventListener('change', reload);

    const p = readFilters(document);
    page = Number(p.page) || 1;
    load();
  })();
</script>
@endpush
