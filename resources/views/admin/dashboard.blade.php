@extends('layouts.admin')

@section('title', 'System Admin Dashboard')
@section('nav', 'dashboard')

@push('styles')
<style>
  .admin-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:24px}
  .admin-main-grid{display:grid;grid-template-columns:minmax(0,7fr) minmax(300px,5fr);gap:24px;align-items:start}
  .admin-filters{display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin-bottom:16px}
  .admin-filters .field{min-width:170px;flex:1}
  .admin-filters .field.search{min-width:220px;flex:2}
  .admin-inline-form{display:flex;align-items:end;gap:12px;flex-wrap:wrap}
  .admin-inline-form .field{flex:1;min-width:180px}
  .admin-note{padding:12px 14px;border:1px solid var(--border);border-radius:4px;background:var(--bg-canvas);color:var(--text-muted);font-size:13px}
  .admin-status{display:inline-flex;align-items:center;gap:7px;font-weight:600;font-size:12px}
  .admin-status:before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
  .admin-status.active{color:var(--ok-fg)}
  .admin-status.inactive{color:var(--text-muted)}
  .admin-coverage-name{font-weight:600}
  .admin-dialog{width:min(720px,calc(100vw - 24px));max-width:none;max-height:calc(100dvh - 32px);padding:0;border:1px solid var(--border);border-radius:8px;background:var(--bg-surface);color:var(--text)}
  .admin-dialog::backdrop{background:rgba(30,36,45,.48)}
  .admin-dialog[open]{display:block}
  .admin-dialog-head,.admin-dialog-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 22px}
  .admin-dialog-head{border-bottom:1px solid var(--border)}
  .admin-dialog-head h2{font:700 18px/1.3 var(--font)}
  .admin-dialog-body{padding:22px;overflow-y:auto;max-height:calc(100dvh - 180px)}
  .admin-dialog-foot{border-top:1px solid var(--border);justify-content:flex-end}
  .admin-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
  .admin-form-grid .full{grid-column:1/-1}
  .admin-dialog .field{display:flex;flex-direction:column;gap:6px;min-width:0}
  .admin-dialog .field label,.admin-form-grid>div>label{font:600 13px var(--font)}
  .admin-dialog input:not([type=checkbox]),.admin-dialog select,.admin-import-form input[type=file],.admin-import-form input[type=text]{width:100%;min-height:38px;padding:8px 10px;border:1px solid var(--border);border-radius:4px;background:var(--bg-surface)}
  .admin-dialog .hint,.admin-import-form .hint{font-size:12px;color:var(--text-muted)}
  .admin-dialog .err{display:block;color:var(--no-fg);font-size:12px}
  .admin-dialog .field.bad input,.admin-dialog .field.bad select{border-color:var(--no-fg)}
  .admin-import-form{display:grid;gap:14px}
  .admin-import-result{margin-top:12px}
  .admin-section-intro{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px}
  .admin-section-intro h3{font:700 15px/1.3 var(--font)}
  .admin-section-intro p{margin-top:5px;color:var(--text-muted);font-size:13px}
  .admin-account-cell b{display:block;font-weight:600}
  .admin-account-cell .sub{display:block;color:var(--text-muted);font-size:12px}
  .admin-actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
  .admin-structure-layout{display:grid;grid-template-columns:minmax(0,1fr);gap:24px;align-items:start}
  .admin-structure-layout>section{min-width:0}
  .admin-structure-layout .tbl-wrap{max-width:100%}
  .admin-structure-layout .tbl{min-width:560px}
  .admin-structure-layout .admin-actions .btn{padding:6px 8px;font-size:12px}
  .admin-structure-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
  .admin-structure-head h3{font:700 15px/1.3 var(--font)}
  .admin-structure-head p{margin-top:5px;color:var(--text-muted);font-size:13px}
  @@container ct (max-width:900px){.admin-main-grid,.admin-structure-layout{grid-template-columns:minmax(0,1fr)}.admin-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @@container ct (max-width:560px){.admin-stat-grid{grid-template-columns:minmax(0,1fr)}.admin-form-grid{grid-template-columns:minmax(0,1fr)}.admin-form-grid .full{grid-column:auto}.admin-dialog-head,.admin-dialog-body{padding:16px}.admin-dialog-foot{padding:14px 16px}}
</style>
@endpush

@section('content')
  <x-ui.page-header title="System administration" meta="Manage programmes, departments, divisions, coordinator accounts and the official student master-list roster.">
    <button type="button" class="btn btn-primary" data-add-coordinator>
      <svg class="icon sm" aria-hidden="true"><use href="#i-plus"/></svg>
      Add coordinator
    </button>
    <a class="btn btn-secondary" href="#master-list">Import master list</a>
  </x-ui.page-header>

  <div id="admin-page-error" aria-live="polite"></div>

  <div class="admin-stat-grid" aria-label="System administration metrics">
    <div class="stat"><span class="cap">Active departments</span><div class="v" id="s-active-departments">&ndash;</div><span class="meta" id="s-total-departments"></span></div>
    <div class="stat"><span class="cap">Active coordinators</span><div class="v" id="s-active-coordinators">&ndash;</div><span class="meta">Department assignments in force</span></div>
    <div class="stat"><span class="cap">Inactive coordinators</span><div class="v" id="s-inactive-coordinators">&ndash;</div><span class="meta">Historical accounts retained</span></div>
    <div class="stat"><span class="cap">Active divisions</span><div class="v" id="s-active-divisions">&ndash;</div><span class="meta">Across all departments</span></div>
    <div class="stat"><span class="cap">Master-list entries</span><div class="v" id="s-roster-total">&ndash;</div><span class="meta">Official roster records</span></div>
    <div class="stat"><span class="cap">Awaiting registration</span><div class="v" id="s-roster-unclaimed">&ndash;</div><span class="meta">Roster records not claimed yet</span></div>
    <div class="stat"><span class="cap">Claimed roster entries</span><div class="v" id="s-roster-claimed">&ndash;</div><span class="meta">Claim timestamp is recorded</span></div>
  </div>

  <x-ui.card id="structure" title="Academic structure">
    <p class="meta" style="margin-bottom:18px">Manage programmes, departments, and divisions. One T&P coordinator is assigned per department, and that coordinator covers every division under it. Records are deactivated rather than deleted.</p>
    <div class="admin-structure-layout">
      <section aria-labelledby="admin-programs-title">
        <div class="admin-structure-head admin-section-intro">
          <div><h3 id="admin-programs-title">Programmes</h3><p>BBA, MBA, B.Tech, M.Tech, and integrated programmes.</p></div>
          <button type="button" class="btn btn-primary" id="add-program">Add programme</button>
        </div>
        <div class="tbl-wrap"><table class="tbl" data-cols='["Programme","Departments","Status","Actions"]'>
          <thead><tr><th>Programme</th><th>Active departments</th><th>Status</th><th><span class="sr">Actions</span></th></tr></thead>
          <tbody id="program-admin-rows"><tr><td colspan="4"><div class="skel" style="width:60%"></div></td></tr></tbody>
        </table></div>
      </section>
      <section aria-labelledby="admin-departments-title">
        <div class="admin-structure-head admin-section-intro">
          <div><h3 id="admin-departments-title">Departments</h3><p>Coordinator assignments are made at this level.</p></div>
          <button type="button" class="btn btn-primary" id="add-department">Add department</button>
        </div>
        <div class="tbl-wrap"><table class="tbl" data-cols='["Department","Programme","Divisions","Status","Actions"]'>
          <thead><tr><th>Department</th><th>Programme</th><th>Active divisions</th><th>Status</th><th><span class="sr">Actions</span></th></tr></thead>
          <tbody id="department-admin-rows"><tr><td colspan="5"><div class="skel" style="width:60%"></div></td></tr></tbody>
        </table></div>
      </section>
      <section aria-labelledby="admin-divisions-title">
        <div class="admin-structure-head admin-section-intro">
          <div><h3 id="admin-divisions-title">Divisions / sections</h3><p>Examples: SY CSE-A, SY CSE-B, DSY CSE-B.</p></div>
          <button type="button" class="btn btn-primary" id="add-division">Add division</button>
        </div>
        <div class="admin-filters">
          <div class="field search"><label for="division-search">Search divisions</label><input id="division-search" type="search" placeholder="Division name or code"></div>
          <div class="field"><label for="division-filter-department">Department</label><select id="division-filter-department"><option value="">All departments</option></select></div>
          <div class="field"><label for="division-filter-status">Status</label><select id="division-filter-status"><option value="all">All divisions</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        </div>
        <div class="tbl-wrap"><table class="tbl" data-cols='["Division","Department / programme","Status","Actions"]'>
          <thead><tr><th>Division</th><th>Department / programme</th><th>Status</th><th><span class="sr">Actions</span></th></tr></thead>
          <tbody id="division-admin-rows"><tr><td colspan="4"><div class="skel" style="width:60%"></div></td></tr></tbody>
        </table></div>
      </section>
    </div>
  </x-ui.card>

  <div class="stack">
    <x-ui.card id="coordinators" title="Coordinator accounts">
      <x-slot:actions>
        <button type="button" class="btn btn-primary" data-add-coordinator>
          <svg class="icon sm" aria-hidden="true"><use href="#i-plus"/></svg> Add account
        </button>
      </x-slot:actions>
      <p class="meta" style="margin-bottom:16px">One active coordinator per department. Replacing a coordinator requires deactivating the existing account first.</p>
      <div class="admin-filters">
        <div class="field search">
          <label for="coord-search">Search accounts</label>
          <input id="coord-search" type="search" placeholder="Name, email or employee ID">
        </div>
        <div class="field">
          <label for="coord-filter-department">Department</label>
          <select id="coord-filter-department">
            <option value="">All departments</option>
            @foreach ($departments as $department)
              <option value="{{ $department->id }}">{{ $department->program?->name }} &middot; {{ $department->code }} — {{ $department->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="coord-filter-status">Account status</label>
          <select id="coord-filter-status">
            <option value="">All accounts</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="tbl-wrap">
        <table class="tbl" data-cols='["Coordinator","Department","Employee / title","Status","Actions"]'>
          <thead><tr><th>Coordinator</th><th>Department</th><th>Employee / title</th><th>Status</th><th><span class="sr">Actions</span></th></tr></thead>
          <tbody id="coordinator-rows"><tr><td colspan="5"><div class="skel" style="width:60%"></div></td></tr></tbody>
        </table>
      </div>
    </x-ui.card>

    <div class="admin-main-grid">
      <x-ui.card id="department-coverage" title="Department coverage">
        <p class="meta" style="margin-bottom:14px">Shows whether each department has an active coordinator. No student marks, experience documents or placement details are exposed here.</p>
        <div class="tbl-wrap">
          <table class="tbl" data-cols='["Department","Programme","Active divisions","Coordinator","Coverage"]'>
            <thead><tr><th>Department</th><th>Programme</th><th>Active divisions</th><th>Coordinator</th><th>Coverage</th></tr></thead>
            <tbody id="coverage-rows"><tr><td colspan="5"><div class="skel" style="width:60%"></div></td></tr></tbody>
          </table>
        </div>
      </x-ui.card>

      <div class="stack">
        <x-ui.card id="master-list" title="Student master-list import">
          <div class="admin-section-intro">
            <div>
              <h3>Import the official roster</h3>
              <p>CSV import is all-or-nothing: any invalid row rejects the entire file.</p>
            </div>
            <button type="button" class="btn btn-secondary" id="download-csv-template">Download CSV template</button>
          </div>
          <form id="master-list-form" class="admin-import-form" enctype="multipart/form-data" novalidate>
            <div class="field" data-field="file">
              <label for="master-list-file">CSV file <span aria-hidden="true">*</span></label>
              <input id="master-list-file" name="file" data-f="file" type="file" accept=".csv,.txt,text/csv,text/plain" required>
              <span class="hint">CSV or TXT, maximum 5 MB. Use the provided header row exactly.</span>
              <span class="err" role="alert" hidden></span>
            </div>
            <div class="field" data-field="batch">
              <label for="master-list-batch">Import batch label (optional)</label>
              <input id="master-list-batch" name="batch" data-f="batch" type="text" maxlength="60" placeholder="e.g. 2026-semester-rollover">
              <span class="err" role="alert" hidden></span>
            </div>
            <button type="submit" class="btn btn-primary" id="master-list-submit">Validate and import CSV</button>
          </form>
          <div id="master-list-result" class="admin-import-result" aria-live="polite"></div>
        </x-ui.card>

        <x-ui.card id="recent-batches" title="Recent import batches">
          <div class="tbl-wrap">
            <table class="tbl" data-cols='["Batch","Roster rows","Last updated"]'>
              <thead><tr><th>Batch</th><th>Roster rows</th><th>Last updated</th></tr></thead>
              <tbody id="batch-rows"><tr><td colspan="3"><div class="skel" style="width:60%"></div></td></tr></tbody>
            </table>
          </div>
          <p class="meta" style="margin-top:12px">Rows are grouped by the batch label recorded on the roster. Unlabelled imports are not listed here.</p>
        </x-ui.card>
      </div>
    </div>

    <x-ui.card title="System admin access boundary">
      <div class="admin-note">
        This dashboard manages the academic hierarchy, coordinator-account lifecycle and student master-list administration. Student academic records, experience certificates and placement records remain under their dedicated T&amp;P and TPO workflows.
      </div>
    </x-ui.card>
  </div>

<dialog class="admin-dialog" id="coordinator-dialog" aria-labelledby="coord-dialog-title">
  <form id="coordinator-form" novalidate>
    <div class="admin-dialog-head">
      <div>
        <h2 id="coord-dialog-title">Add coordinator</h2>
        <p class="meta" id="coord-dialog-subtitle">Create an account for one department.</p>
      </div>
      <button type="button" class="icon-btn" data-close-coordinator aria-label="Close dialog">
        <svg class="icon" aria-hidden="true"><use href="#i-x"/></svg>
      </button>
    </div>
    <div class="admin-dialog-body">
      <div class="admin-form-grid">
        <div class="field full" id="coord-create-department" data-field="department_id">
          <label for="coord-department">Department <span aria-hidden="true">*</span></label>
          <select id="coord-department" name="department_id" data-f="department_id" required>
            <option value="">Choose an available department</option>
            @foreach ($departmentOptions as $department)
              <option value="{{ $department['id'] }}" data-base-label="{{ $department['program_code'] }} &middot; {{ $department['code'] }} — {{ $department['name'] }}">{{ $department['program_code'] }} &middot; {{ $department['code'] }} — {{ $department['name'] }}</option>
            @endforeach
          </select>
          <span class="hint">Departments with an active coordinator are unavailable until that account is deactivated.</span>
          <span class="err" role="alert" hidden></span>
        </div>
        <div class="field full" id="coord-edit-department" hidden>
          <label>Assigned department</label>
          <p class="admin-note" id="coord-current-department"></p>
          <span class="hint">Department reassignment is intentionally done by deactivating and creating an account for the new department.</span>
        </div>
        <div class="field" data-field="name">
          <label for="coord-name">Full name <span aria-hidden="true">*</span></label>
          <input id="coord-name" name="name" data-f="name" type="text" maxlength="150" autocomplete="name" required>
          <span class="err" role="alert" hidden></span>
        </div>
        <div class="field" data-field="email">
          <label for="coord-email">Email <span aria-hidden="true">*</span></label>
          <input id="coord-email" name="email" data-f="email" type="email" maxlength="190" autocomplete="email" required>
          <span class="err" role="alert" hidden></span>
        </div>
        <div class="field full" data-field="password">
          <label for="coord-password">Temporary password <span id="coord-password-required" aria-hidden="true">*</span></label>
          <input id="coord-password" name="password" data-f="password" type="password" autocomplete="new-password" minlength="12">
          <span class="err" role="alert" hidden></span>
          <span class="hint" id="coord-password-hint">At least 12 characters, including uppercase, lowercase and a number. Provide the credentials securely to the coordinator.</span>
        </div>
        <div class="field" data-field="employee_id">
          <label for="coord-employee-id">Employee ID</label>
          <input id="coord-employee-id" name="employee_id" data-f="employee_id" type="text" maxlength="30">
          <span class="err" role="alert" hidden></span>
        </div>
        <div class="field" data-field="phone">
          <label for="coord-phone">Phone</label>
          <input id="coord-phone" name="phone" data-f="phone" type="tel" placeholder="10-digit number or +91 number">
          <span class="err" role="alert" hidden></span>
        </div>
        <div class="field full" data-field="designation">
          <label for="coord-designation">Designation</label>
          <input id="coord-designation" name="designation" data-f="designation" type="text" maxlength="80" placeholder="e.g. T&P Coordinator">
          <span class="err" role="alert" hidden></span>
        </div>
      </div>
    </div>
    <div class="admin-dialog-foot">
      <button type="button" class="btn btn-secondary" data-close-coordinator>Cancel</button>
      <button type="submit" class="btn btn-primary" id="coord-save">Create coordinator</button>
    </div>
  </form>
</dialog>

<dialog id="program-dialog" class="admin-dialog" aria-labelledby="program-dialog-title">
  <form id="program-form" novalidate>
    <div class="admin-dialog-head"><div><h2 id="program-dialog-title">Add programme</h2><p class="meta" id="program-dialog-subtitle">Create a university programme.</p></div><button type="button" class="icon-btn" data-close-program aria-label="Close programme form">&times;</button></div>
    <div class="admin-dialog-body"><div class="admin-form-grid">
      <div class="field" data-field="name"><label for="program-name">Programme name <span aria-hidden="true">*</span></label><input id="program-name" name="name" data-f="name" type="text" maxlength="120" required><span class="err" role="alert" hidden></span></div>
      <div class="field" data-field="code"><label for="program-code">Programme code <span aria-hidden="true">*</span></label><input id="program-code" name="code" data-f="code" type="text" maxlength="20" pattern="[A-Za-z0-9_-]+" placeholder="e.g. BTECH" required><span class="hint">Saved in uppercase.</span><span class="err" role="alert" hidden></span></div>
    </div></div>
    <div class="admin-dialog-foot"><button type="button" class="btn btn-secondary" data-close-program>Cancel</button><button type="submit" class="btn btn-primary" id="program-save">Create programme</button></div>
  </form>
</dialog>

<dialog id="department-dialog" class="admin-dialog" aria-labelledby="department-dialog-title">
  <form id="department-form" novalidate>
    <div class="admin-dialog-head"><div><h2 id="department-dialog-title">Add department</h2><p class="meta" id="department-dialog-subtitle">Create a department within a programme.</p></div><button type="button" class="icon-btn" data-close-department aria-label="Close department form">&times;</button></div>
    <div class="admin-dialog-body"><div class="admin-form-grid">
      <div class="field full" data-field="program_id"><label for="department-program">Programme <span aria-hidden="true">*</span></label><select id="department-program" name="program_id" data-f="program_id" required><option value="">Choose an active programme</option></select><span class="err" role="alert" hidden></span></div>
      <div class="field" data-field="name"><label for="department-name">Department name <span aria-hidden="true">*</span></label><input id="department-name" name="name" data-f="name" type="text" maxlength="120" required><span class="err" role="alert" hidden></span></div>
      <div class="field" data-field="code"><label for="department-code">Department code <span aria-hidden="true">*</span></label><input id="department-code" name="code" data-f="code" type="text" maxlength="20" pattern="[A-Za-z0-9_-]+" placeholder="e.g. CSE" required><span class="hint">Letters, numbers, hyphens and underscores; saved in uppercase.</span><span class="err" role="alert" hidden></span></div>
    </div></div>
    <div class="admin-dialog-foot"><button type="button" class="btn btn-secondary" data-close-department>Cancel</button><button type="submit" class="btn btn-primary" id="department-save">Create department</button></div>
  </form>
</dialog>

<dialog id="division-dialog" class="admin-dialog" aria-labelledby="division-dialog-title">
  <form id="division-form" novalidate>
    <div class="admin-dialog-head"><div><h2 id="division-dialog-title">Add division</h2><p class="meta" id="division-dialog-subtitle">Add a division beneath a department.</p></div><button type="button" class="icon-btn" data-close-division aria-label="Close division form">&times;</button></div>
    <div class="admin-dialog-body"><div class="admin-form-grid">
      <div class="field full" data-field="department_id" id="division-department-field"><label for="division-department">Parent department <span aria-hidden="true">*</span></label><select id="division-department" name="department_id" data-f="department_id" required><option value="">Choose an active department</option></select><span class="err" role="alert" hidden></span></div>
      <div class="field full" id="division-current-department-field" hidden><label>Parent department</label><p class="admin-note" id="division-current-department"></p><span class="hint">The parent department cannot be changed after creation.</span></div>
      <div class="field" data-field="name"><label for="division-name">Division name <span aria-hidden="true">*</span></label><input id="division-name" name="name" data-f="name" type="text" maxlength="120" placeholder="e.g. SY CSE-A" required><span class="err" role="alert" hidden></span></div>
      <div class="field" data-field="code"><label for="division-code">Division code <span aria-hidden="true">*</span></label><input id="division-code" name="code" data-f="code" type="text" maxlength="30" pattern="[A-Za-z0-9_-]+" placeholder="e.g. SYCSE-A" required><span class="hint">Unique across the university; saved in uppercase.</span><span class="err" role="alert" hidden></span></div>
    </div></div>
    <div class="admin-dialog-foot"><button type="button" class="btn btn-secondary" data-close-division>Cancel</button><button type="submit" class="btn btn-primary" id="division-save">Create division</button></div>
  </form>
</dialog>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  const { $, api, esc, fillRows, toast, flash, fail, fmtDT, notice, clearErrors } = TPMS;
  const metrics = @json($metrics);
  const departmentRows = @json($departmentRows);
  const departmentOptions = @json($departmentOptions);
  const recentBatches = @json($recentBatches);
  const coordinatorDialog = $('#coordinator-dialog');
  const coordinatorForm = $('#coordinator-form');
  const masterListForm = $('#master-list-form');
  let coordinators = [];
  let editingId = null;
  const programDialog = $('#program-dialog');
  const programForm = $('#program-form');
  const departmentDialog = $('#department-dialog');
  const departmentForm = $('#department-form');
  const divisionDialog = $('#division-dialog');
  const divisionForm = $('#division-form');
  let structure = { programs: [], departments: [], divisions: [] };
  let editingProgramId = null;
  let editingDepartmentId = null;
  let editingDivisionId = null;

  function statusBadge(active) {
    return active
      ? '<span class="admin-status active">Active</span>'
      : '<span class="admin-status inactive">Inactive</span>';
  }

  function syncStructureSelects() {
    const selectedProgram = $('#department-program').value;
    const selectedDepartment = $('#division-department').value;
    const selectedFilterDepartment = $('#division-filter-department').value;
    $('#department-program').innerHTML = '<option value="">Choose an active programme</option>' + structure.programs.map((program) =>
      '<option value="' + esc(program.id) + '"' + (program.is_active ? '' : ' disabled') + '>' +
      esc(program.code + ' — ' + program.name + (program.is_active ? '' : ' (inactive)')) + '</option>'
    ).join('');
    if (structure.programs.some((program) => String(program.id) === selectedProgram)) {
      $('#department-program').value = selectedProgram;
    }

    $('#division-department').innerHTML = '<option value="">Choose an active department</option>' + structure.departments.map((department) => {
      const active = department.is_active && department.program && department.program.is_active;
      return '<option value="' + esc(department.id) + '"' + (active ? '' : ' disabled') + '>' +
        esc(department.code + ' — ' + department.name + ' (' + (department.program ? department.program.name : 'Programme') + ')' + (active ? '' : ' (inactive)')) + '</option>';
    }).join('');
    if (structure.departments.some((department) => String(department.id) === selectedDepartment)) {
      $('#division-department').value = selectedDepartment;
    }

    $('#division-filter-department').innerHTML = '<option value="">All departments</option>' + structure.departments.map((department) =>
      '<option value="' + esc(department.id) + '">' + esc(department.code + ' — ' + department.name) + '</option>'
    ).join('');
    if (structure.departments.some((department) => String(department.id) === selectedFilterDepartment)) {
      $('#division-filter-department').value = selectedFilterDepartment;
    }
    $('#add-department').disabled = !structure.programs.some((program) => program.is_active);
    $('#add-division').disabled = !structure.departments.some((department) => department.is_active && department.program && department.program.is_active);
  }

  function renderStructure() {
    fillRows($('#program-admin-rows'), structure.programs, (program) => {
      const edit = '<button type="button" class="btn btn-secondary" data-program-action="edit" data-id="' + esc(program.id) + '">Edit</button>';
      const toggle = program.is_active
        ? '<button type="button" class="btn btn-danger" data-program-action="deactivate" data-id="' + esc(program.id) + '">Deactivate</button>'
        : '<button type="button" class="btn btn-secondary" data-program-action="reactivate" data-id="' + esc(program.id) + '">Reactivate</button>';
      return [
        '<span class="admin-account-cell"><b>' + esc(program.name) + '</b><span class="sub">' + esc(program.code) + '</span></span>',
        '<span class="num">' + esc(program.active_departments_count) + '</span>',
        statusBadge(program.is_active),
        { html: '<div class="admin-actions">' + edit + toggle + '</div>', cls: 'act' }
      ];
    }, 'No programmes found.');

    fillRows($('#department-admin-rows'), structure.departments, (department) => {
      const edit = '<button type="button" class="btn btn-secondary" data-department-action="edit" data-id="' + esc(department.id) + '">Edit</button>';
      const toggle = department.is_active
        ? '<button type="button" class="btn btn-danger" data-department-action="deactivate" data-id="' + esc(department.id) + '">Deactivate</button>'
        : '<button type="button" class="btn btn-secondary" data-department-action="reactivate" data-id="' + esc(department.id) + '">Reactivate</button>';
      return [
        '<span class="admin-account-cell"><b>' + esc(department.name) + '</b><span class="sub">' + esc(department.code) + '</span></span>',
        '<span class="admin-account-cell"><b>' + esc(department.program ? department.program.name : '—') + '</b><span class="sub">' + esc(department.program ? department.program.code : '') + '</span></span>',
        '<span class="num">' + esc(department.active_divisions_count) + '</span>',
        statusBadge(department.is_active),
        { html: '<div class="admin-actions">' + edit + toggle + '</div>', cls: 'act' }
      ];
    }, 'No departments found.');

    const query = $('#division-search').value.trim().toLowerCase();
    const departmentFilter = $('#division-filter-department').value;
    const statusFilter = $('#division-filter-status').value;
    const divisions = structure.divisions.filter((division) => {
      if (departmentFilter && String(division.department_id) !== departmentFilter) return false;
      if (statusFilter === 'active' && !division.is_active) return false;
      if (statusFilter === 'inactive' && division.is_active) return false;
      if (query && !(division.name + ' ' + division.code).toLowerCase().includes(query)) return false;
      return true;
    });

    fillRows($('#division-admin-rows'), divisions, (division) => {
      const edit = '<button type="button" class="btn btn-secondary" data-division-action="edit" data-id="' + esc(division.id) + '">Edit</button>';
      const toggle = division.is_active
        ? '<button type="button" class="btn btn-danger" data-division-action="deactivate" data-id="' + esc(division.id) + '">Deactivate</button>'
        : '<button type="button" class="btn btn-secondary" data-division-action="reactivate" data-id="' + esc(division.id) + '">Reactivate</button>';
      const department = division.department || {};
      const program = department.program || {};
      return [
        '<span class="admin-account-cell"><b>' + esc(division.name) + '</b><span class="sub">' + esc(division.code) + '</span></span>',
        '<span class="admin-account-cell"><b>' + esc(department.code || '—') + ' · ' + esc(department.name || 'Unknown department') + '</b><span class="sub">' + esc(program.name || 'Unknown programme') + '</span></span>',
        statusBadge(division.is_active),
        { html: '<div class="admin-actions">' + edit + toggle + '</div>', cls: 'act' }
      ];
    }, 'No divisions match these filters.');
  }

  async function loadStructure() {
    try {
      const response = await api('/admin/structure');
      structure = response.data || { programs: [], departments: [], divisions: [] };
      syncStructureSelects();
      renderStructure();
    } catch (error) {
      fail(error);
      fillRows($('#program-admin-rows'), [], () => [], 'Could not load programmes.');
      fillRows($('#department-admin-rows'), [], () => [], 'Could not load departments.');
      fillRows($('#division-admin-rows'), [], () => [], 'Could not load divisions.');
    }
  }

  function openCreateProgram() {
    editingProgramId = null;
    programForm.reset();
    clearErrors(programForm);
    $('#program-dialog-title').textContent = 'Add programme';
    $('#program-dialog-subtitle').textContent = 'Create a university programme with a unique name and code.';
    $('#program-save').textContent = 'Create programme';
    programDialog.showModal();
    $('#program-name').focus();
  }

  function openEditProgram(program) {
    editingProgramId = program.id;
    programForm.reset();
    clearErrors(programForm);
    $('#program-dialog-title').textContent = 'Edit programme';
    $('#program-dialog-subtitle').textContent = 'Existing departments remain associated with this programme.';
    $('#program-name').value = program.name || '';
    $('#program-code').value = program.code || '';
    $('#program-save').textContent = 'Save changes';
    programDialog.showModal();
    $('#program-name').focus();
  }

  function openCreateDepartment() {
    if (!structure.programs.some((program) => program.is_active)) {
      toast('Create or reactivate a programme before adding a department.', 'info');
      return;
    }
    editingDepartmentId = null;
    departmentForm.reset();
    clearErrors(departmentForm);
    syncStructureSelects();
    $('#department-dialog-title').textContent = 'Add department';
    $('#department-dialog-subtitle').textContent = 'Create a department within an active programme.';
    $('#department-program').disabled = false;
    $('#department-program').required = true;
    $('#department-save').textContent = 'Create department';
    departmentDialog.showModal();
    $('#department-program').focus();
  }

  function openEditDepartment(department) {
    editingDepartmentId = department.id;
    departmentForm.reset();
    clearErrors(departmentForm);
    syncStructureSelects();
    $('#department-dialog-title').textContent = 'Edit department';
    $('#department-dialog-subtitle').textContent = 'Change the department name, code or parent programme.';
    $('#department-name').value = department.name || '';
    $('#department-code').value = department.code || '';
    $('#department-program').value = String(department.program_id);
    $('#department-program').disabled = false;
    $('#department-program').required = true;
    $('#department-save').textContent = 'Save changes';
    departmentDialog.showModal();
    $('#department-name').focus();
  }

  function openCreateDivision() {
    if (!structure.departments.some((department) => department.is_active && department.program && department.program.is_active)) {
      toast('Create or reactivate a department under an active programme first.', 'info');
      return;
    }
    editingDivisionId = null;
    divisionForm.reset();
    clearErrors(divisionForm);
    syncStructureSelects();
    $('#division-dialog-title').textContent = 'Add division';
    $('#division-dialog-subtitle').textContent = 'Create a class/section within an active department.';
    $('#division-department-field').hidden = false;
    $('#division-current-department-field').hidden = true;
    $('#division-department').disabled = false;
    $('#division-department').required = true;
    $('#division-save').textContent = 'Create division';
    divisionDialog.showModal();
    $('#division-department').focus();
  }

  function openEditDivision(division) {
    editingDivisionId = division.id;
    divisionForm.reset();
    clearErrors(divisionForm);
    $('#division-dialog-title').textContent = 'Edit division';
    $('#division-dialog-subtitle').textContent = 'Update the division/section name or code.';
    $('#division-department-field').hidden = true;
    $('#division-current-department-field').hidden = false;
    const department = division.department || {};
    const program = department.program || {};
    $('#division-current-department').textContent = (program.name || 'Programme') + ' · ' + (department.code || '') + ' — ' + (department.name || '');
    $('#division-department').disabled = true;
    $('#division-department').required = false;
    $('#division-name').value = division.name || '';
    $('#division-code').value = division.code || '';
    $('#division-save').textContent = 'Save changes';
    divisionDialog.showModal();
    $('#division-name').focus();
  }

  $('#add-program').addEventListener('click', openCreateProgram);
  $('#add-department').addEventListener('click', openCreateDepartment);
  $('#add-division').addEventListener('click', openCreateDivision);
  document.querySelectorAll('[data-close-program]').forEach((button) => button.addEventListener('click', () => programDialog.close()));
  document.querySelectorAll('[data-close-department]').forEach((button) => button.addEventListener('click', () => departmentDialog.close()));
  document.querySelectorAll('[data-close-division]').forEach((button) => button.addEventListener('click', () => divisionDialog.close()));

  programForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors(programForm);
    const body = { name: $('#program-name').value.trim(), code: $('#program-code').value.trim().toUpperCase() };
    const button = $('#program-save');
    button.disabled = true;
    try {
      await api(editingProgramId === null ? '/admin/programs' : '/admin/programs/' + editingProgramId, {
        method: editingProgramId === null ? 'POST' : 'PATCH', body
      });
      flash(editingProgramId === null ? 'Programme created.' : 'Programme updated.');
      window.location.reload();
    } catch (error) {
      fail(error, programForm);
    } finally {
      button.disabled = false;
    }
  });

  departmentForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors(departmentForm);
    const programId = $('#department-program').value;
    if (!programId) {
      toast('Choose a programme.', 'info');
      return;
    }
    const body = {
      name: $('#department-name').value.trim(),
      code: $('#department-code').value.trim().toUpperCase(),
      program_id: Number(programId)
    };
    const button = $('#department-save');
    button.disabled = true;
    try {
      const url = editingDepartmentId === null ? '/admin/programs/' + programId + '/departments' : '/admin/departments/' + editingDepartmentId;
      await api(url, { method: editingDepartmentId === null ? 'POST' : 'PATCH', body });
      flash(editingDepartmentId === null ? 'Department created.' : 'Department updated.');
      window.location.reload();
    } catch (error) {
      fail(error, departmentForm);
    } finally {
      button.disabled = false;
    }
  });

  divisionForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors(divisionForm);
    const departmentId = $('#division-department').value;
    if (editingDivisionId === null && !departmentId) {
      toast('Choose a parent department.', 'info');
      return;
    }
    const body = {
      name: $('#division-name').value.trim(),
      code: $('#division-code').value.trim().toUpperCase()
    };
    const url = editingDivisionId === null ? '/admin/departments/' + departmentId + '/divisions' : '/admin/divisions/' + editingDivisionId;
    const button = $('#division-save');
    button.disabled = true;
    try {
      await api(url, { method: editingDivisionId === null ? 'POST' : 'PATCH', body });
      flash(editingDivisionId === null ? 'Division created.' : 'Division updated.');
      window.location.reload();
    } catch (error) {
      fail(error, divisionForm);
    } finally {
      button.disabled = false;
    }
  });

  $('#program-admin-rows').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-program-action]');
    if (!button) return;
    const program = structure.programs.find((item) => String(item.id) === button.dataset.id);
    if (!program) return;
    const action = button.dataset.programAction;
    if (action === 'edit') { openEditProgram(program); return; }
    if (action === 'deactivate' && !window.confirm('Deactivate ' + program.name + '? Active departments must be deactivated first. Existing data is retained.')) return;
    if (action === 'reactivate' && !window.confirm('Reactivate ' + program.name + '? Departments remain in their current states.')) return;
    button.disabled = true;
    try {
      await api('/admin/programs/' + program.id + '/' + action, { method: 'POST', body: {} });
      flash(action === 'deactivate' ? 'Programme deactivated.' : 'Programme reactivated.');
      window.location.reload();
    } catch (error) {
      fail(error);
      button.disabled = false;
    }
  });

  $('#department-admin-rows').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-department-action]');
    if (!button) return;
    const department = structure.departments.find((item) => String(item.id) === button.dataset.id);
    if (!department) return;
    const action = button.dataset.departmentAction;
    if (action === 'edit') { openEditDepartment(department); return; }
    if (action === 'deactivate' && !window.confirm('Deactivate ' + department.name + '? Active divisions and its active coordinator must be deactivated first. Existing students remain assigned.')) return;
    if (action === 'reactivate' && !window.confirm('Reactivate ' + department.name + '? Its divisions and coordinator keep their current status.')) return;
    button.disabled = true;
    try {
      await api('/admin/departments/' + department.id + '/' + action, { method: 'POST', body: {} });
      flash(action === 'deactivate' ? 'Department deactivated.' : 'Department reactivated.');
      window.location.reload();
    } catch (error) {
      fail(error);
      button.disabled = false;
    }
  });

  $('#division-admin-rows').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-division-action]');
    if (!button) return;
    const division = structure.divisions.find((item) => String(item.id) === button.dataset.id);
    if (!division) return;
    const action = button.dataset.divisionAction;
    if (action === 'edit') { openEditDivision(division); return; }
    if (action === 'deactivate' && !window.confirm('Deactivate division ' + division.name + '? Existing students and historical references are retained.')) return;
    if (action === 'reactivate' && !window.confirm('Reactivate division ' + division.name + '? Its parent department and programme must be active.')) return;
    button.disabled = true;
    try {
      await api('/admin/divisions/' + division.id + '/' + action, { method: 'POST', body: {} });
      flash(action === 'deactivate' ? 'Division deactivated.' : 'Division reactivated.');
      window.location.reload();
    } catch (error) {
      fail(error);
      button.disabled = false;
    }
  });

  $('#division-search').addEventListener('input', renderStructure);
  $('#division-filter-department').addEventListener('change', renderStructure);
  $('#division-filter-status').addEventListener('change', renderStructure);

  function setText(id, value) {
    const element = $('#' + id);
    if (element) element.textContent = String(value == null ? 0 : value);
  }

  function renderMetrics() {
    setText('s-active-departments', metrics.departments_active);
    setText('s-total-departments', metrics.departments_total + ' total departments');
    setText('s-active-coordinators', metrics.coordinators_active);
    setText('s-inactive-coordinators', metrics.coordinators_inactive);
    setText('s-active-divisions', metrics.divisions_active);
    setText('s-roster-total', metrics.roster_total);
    setText('s-roster-unclaimed', metrics.roster_unclaimed);
    setText('s-roster-claimed', metrics.roster_claimed);
  }

  function updateDepartmentOptions() {
    const select = $('#coord-department');
    if (!select) return;
    const activeIds = new Set(coordinators.filter((row) => row.is_active).map((row) => String(row.department.id)));
    Array.from(select.options).forEach((option) => {
      if (!option.value) return;
      const base = option.dataset.baseLabel || option.textContent;
      option.disabled = activeIds.has(option.value);
      option.textContent = base + (option.disabled ? ' — active coordinator assigned' : '');
    });
  }

  function filteredCoordinators() {
    const query = $('#coord-search').value.trim().toLowerCase();
    const department = $('#coord-filter-department').value;
    const status = $('#coord-filter-status').value;
    return coordinators.filter((row) => {
      if (department && String(row.department.id) !== department) return false;
      if (status === 'active' && !row.is_active) return false;
      if (status === 'inactive' && row.is_active) return false;
      if (query) {
        const text = [row.name, row.email, row.employee_id, row.designation, row.department.name, row.department.code, row.department.program && row.department.program.name].join(' ').toLowerCase();
        if (!text.includes(query)) return false;
      }
      return true;
    });
  }

  function renderCoordinatorTable() {
    const rows = filteredCoordinators();
    fillRows($('#coordinator-rows'), rows, (row) => {
      const state = row.is_active
        ? '<span class="admin-status active">Active</span>'
        : '<span class="admin-status inactive">Inactive</span>';
      const edit = '<button type="button" class="btn btn-secondary" data-coordinator-action="edit" data-id="' + esc(row.id) + '">Edit</button>';
      const stateButton = row.is_active
        ? '<button type="button" class="btn btn-danger" data-coordinator-action="deactivate" data-id="' + esc(row.id) + '">Deactivate</button>'
        : '<button type="button" class="btn btn-secondary" data-coordinator-action="reactivate" data-id="' + esc(row.id) + '">Reactivate</button>';
      return [
        '<div class="admin-account-cell"><b>' + esc(row.name) + '</b><span class="sub">' + esc(row.email) + '</span></div>',
        '<span class="admin-account-cell"><b>' + esc(row.department.code) + '</b><span class="sub">' + esc(row.department.name) + '</span><span class="sub">' + esc(row.department.program ? row.department.program.name : '') + '</span></span>',
        '<span class="admin-account-cell">' + esc(row.employee_id || '—') + '<span class="sub">' + esc(row.designation || '—') + '</span></span>',
        state,
        { html: '<div class="admin-actions">' + edit + stateButton + '</div>', cls: 'act' }
      ];
    }, 'No coordinator accounts match these filters.');
  }

  function renderCoverage() {
    fillRows($('#coverage-rows'), departmentRows, (department) => {
      const coordinator = coordinators.find((row) => row.is_active && Number(row.department.id) === Number(department.id));
      const isCovered = Boolean(coordinator);
      const departmentState = department.is_active
        ? '<span class="tag">Active</span>'
        : '<span class="tag">Inactive department</span>';
      const coordinatorCell = coordinator
        ? '<span class="admin-coverage-name">' + esc(coordinator.name) + '</span><span class="sub">' + esc(coordinator.email) + '</span>'
        : '<span class="meta">No active coordinator</span>';
      const coverage = isCovered
        ? '<span class="badge b-ok">Covered</span>'
        : '<span class="badge b-wait">Unassigned</span>';
      return [
        '<span class="admin-coverage-name">' + esc(department.code) + '</span><span class="sub">' + esc(department.name) + '</span><span class="sub">' + departmentState + '</span>',
        '<span class="admin-account-cell"><b>' + esc(department.program_name || '—') + '</b><span class="sub">' + esc(department.program_code || '') + '</span></span>',
        '<span class="num">' + esc(department.active_divisions_count) + '</span>',
        coordinatorCell,
        coverage
      ];
    }, 'No departments found.');
  }

  async function loadCoordinators() {
    try {
      const response = await api('/admin/coordinators');
      coordinators = response.data || [];
      updateDepartmentOptions();
      renderCoordinatorTable();
      renderCoverage();
    } catch (error) {
      $('#admin-page-error').innerHTML = '<div style="margin-bottom:16px">' + notice('no', 'info', esc(error.message)) + '</div>';
      fail(error);
      fillRows($('#coordinator-rows'), [], () => [], 'Could not load coordinator accounts.');
    }
  }

  function renderRecentBatches() {
    fillRows($('#batch-rows'), recentBatches, (batch) => [
      esc(batch.name),
      '<span class="num">' + esc(batch.entries) + '</span>',
      esc(batch.last_updated ? fmtDT(batch.last_updated) : '—')
    ], 'No labelled import batches found yet.');
  }

  function clearCoordinatorErrors() {
    clearErrors(coordinatorForm);
  }

  function openCreateDialog() {
    const departmentSelect = $('#coord-department');
    const hasVacancy = departmentSelect && Array.from(departmentSelect.options).some((option) => option.value && !option.disabled);
    if (!hasVacancy) {
      toast('Every active department already has a coordinator, or there are no active departments. Deactivate an account or activate a department before adding another.', 'info');
      return;
    }
    editingId = null;
    coordinatorForm.reset();
    clearCoordinatorErrors();
    $('#coord-dialog-title').textContent = 'Add coordinator';
    $('#coord-dialog-subtitle').textContent = 'Create an account for one department.';
    $('#coord-create-department').hidden = false;
    $('#coord-edit-department').hidden = true;
    $('#coord-department').required = true;
    $('#coord-password').required = true;
    $('#coord-password').placeholder = '';
    $('#coord-password-hint').textContent = 'At least 12 characters, including uppercase, lowercase and a number. Provide the credentials securely to the coordinator.';
    $('#coord-password-required').hidden = false;
    $('#coord-save').textContent = 'Create coordinator';
    coordinatorDialog.showModal();
    $('#coord-name').focus();
  }

  function openEditDialog(row) {
    editingId = row.id;
    coordinatorForm.reset();
    clearCoordinatorErrors();
    $('#coord-dialog-title').textContent = 'Edit coordinator';
    $('#coord-dialog-subtitle').textContent = 'Update account details. Department reassignment is not supported in edit mode.';
    $('#coord-create-department').hidden = true;
    $('#coord-edit-department').hidden = false;
    $('#coord-current-department').textContent = row.department.code + ' — ' + row.department.name;
    $('#coord-department').required = false;
    $('#coord-name').value = row.name || '';
    $('#coord-email').value = row.email || '';
    $('#coord-employee-id').value = row.employee_id || '';
    $('#coord-phone').value = row.phone || '';
    $('#coord-designation').value = row.designation || '';
    $('#coord-password').required = false;
    $('#coord-password').placeholder = 'Leave blank to keep current password';
    $('#coord-password-hint').textContent = 'Leave blank to keep the current password. A new password must be at least 12 characters with uppercase, lowercase and a number. Resetting it revokes existing API tokens.';
    $('#coord-password-required').hidden = true;
    $('#coord-save').textContent = 'Save changes';
    coordinatorDialog.showModal();
    $('#coord-name').focus();
  }

  document.querySelectorAll('[data-add-coordinator]').forEach((button) => button.addEventListener('click', openCreateDialog));
  document.querySelectorAll('[data-close-coordinator]').forEach((button) => button.addEventListener('click', () => coordinatorDialog.close()));

  coordinatorForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearCoordinatorErrors();

    const body = {
      name: $('#coord-name').value.trim(),
      email: $('#coord-email').value.trim(),
      employee_id: $('#coord-employee-id').value.trim() || null,
      phone: $('#coord-phone').value.trim() || null,
      designation: $('#coord-designation').value.trim() || null
    };
    const password = $('#coord-password').value;
    if (editingId === null) {
      body.department_id = Number($('#coord-department').value);
      body.password = password;
    } else if (password !== '') {
      body.password = password;
    }

    const button = $('#coord-save');
    button.disabled = true;
    try {
      await api(editingId === null ? '/admin/coordinators' : '/admin/coordinators/' + editingId, {
        method: editingId === null ? 'POST' : 'PATCH',
        body
      });
      flash(editingId === null ? 'Coordinator account created.' : 'Coordinator account updated.');
      window.location.reload();
    } catch (error) {
      fail(error, coordinatorForm);
    } finally {
      button.disabled = false;
    }
  });

  $('#coordinator-rows').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-coordinator-action]');
    if (!button) return;
    const row = coordinators.find((item) => String(item.id) === button.dataset.id);
    if (!row) return;

    if (button.dataset.coordinatorAction === 'edit') {
      openEditDialog(row);
      return;
    }

    if (button.dataset.coordinatorAction === 'deactivate') {
      const reason = window.prompt('Optional reason for deactivating ' + row.name + '. Cancel to leave the account unchanged.', '');
      if (reason === null) return;
      if (!window.confirm('Deactivate ' + row.name + '? Their active sessions and API tokens will be revoked.')) return;
      button.disabled = true;
      try {
        await api('/admin/coordinators/' + row.id + '/deactivate', { method: 'POST', body: { reason: reason.trim() || null } });
        flash('Coordinator account deactivated and tokens revoked.');
        window.location.reload();
      } catch (error) {
        fail(error);
        button.disabled = false;
      }
      return;
    }

    if (button.dataset.coordinatorAction === 'reactivate') {
      if (!window.confirm('Reactivate ' + row.name + '? The department must not already have another active coordinator.')) return;
      button.disabled = true;
      try {
        await api('/admin/coordinators/' + row.id + '/reactivate', { method: 'POST', body: {} });
        flash('Coordinator account reactivated.');
        window.location.reload();
      } catch (error) {
        fail(error);
        button.disabled = false;
      }
    }
  });

  ['coord-search', 'coord-filter-department', 'coord-filter-status'].forEach((id) => {
    const element = $('#' + id);
    element.addEventListener(element.tagName === 'INPUT' ? 'input' : 'change', renderCoordinatorTable);
  });

  $('#download-csv-template').addEventListener('click', () => {
    const header = 'university_id,full_name,institutional_email,program_code,department_code,division_code,admission_type,admission_year,graduation_year,current_semester\r\n';
    const blob = new Blob([header], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'tpms-master-list-template.csv';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
  });

  masterListForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors(masterListForm);
    $('#master-list-result').innerHTML = '';

    const file = $('#master-list-file').files[0];
    if (!file) {
      toast('Choose a CSV file first.', 'info');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      toast('The file must be 5 MB or smaller.', 'info');
      return;
    }
    if (!window.confirm('Import this roster? Existing university IDs are updated, and any invalid row rejects the entire file.')) return;

    const submit = $('#master-list-submit');
    submit.disabled = true;
    submit.textContent = 'Validating and importing…';
    try {
      const response = await fetch('/api/master-list/import', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: new FormData(masterListForm)
      });
      let result = null;
      try { result = await response.json(); } catch (_) { result = null; }
      if (!response.ok) {
        const error = new Error((result && result.message) || 'Roster import failed.');
        error.status = response.status;
        error.errors = (result && result.errors) || {};
        throw error;
      }

      flash('Roster import complete: ' + (result.created || 0) + ' created, ' + (result.updated || 0) + ' updated.');
      window.location.reload();
    } catch (error) {
      fail(error, masterListForm);
    } finally {
      submit.disabled = false;
      submit.textContent = 'Validate and import CSV';
    }
  });

  renderMetrics();
  renderRecentBatches();
  updateDepartmentOptions();
  loadCoordinators();
  loadStructure();
})();
</script>
@endpush
