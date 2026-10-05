@extends('layouts.coordinator')

@section('title', 'Profile')
@section('nav', 'dashboard')

@section('content')
  <x-ui.page-header title="Profile" meta="Your details are maintained by the T&amp;P office." />

  <x-ui.card id="me" title="Your details">
    <div id="me-body"><div class="skel"></div><div class="skel" style="width:70%"></div></div>
  </x-ui.card>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, api, esc, fmtDate, dash, fail } = TPMS;
    const val = (v) => (v == null || v === '' ? dash : esc(v));

    async function load() {
      let p;
      try { p = (await api('/coordinator/profile')).data; } catch (e) { fail(e); return; }
      $('.ph h1').textContent = p.name;
      $('.ph .meta').innerHTML = `T&amp;P Coordinator &middot; ${esc(p.department.name)}`;
      const rows = [['Name', val(p.name)], ['Email', val(p.email)], ['Employee ID', val(p.employee_id)], ['Phone', val(p.phone)],
        ['Designation', val(p.designation)], ['Department', `${esc(p.department.name)} (${esc(p.department.code)})`], ['Active from', p.active_from ? fmtDate(p.active_from) : dash]];
      $('#me-body').innerHTML = '<dl class="dl">' + rows.map(([l, v]) => `<div><dt>${l}</dt><dd>${v}</dd></div>`).join('') + '</dl>';
    }

    load();
  })();
</script>
@endpush
