@extends('layouts.tpo')

@php($editing = ! is_null($driveId))
@section('title', $editing ? 'Edit drive' : 'Create drive')
@section('nav', 'drives')

@section('content')
  <x-ui.page-header :title="$editing ? 'Edit drive' : 'Create drive'"
    meta="Saved as a draft. Nothing is visible to students until you publish.">
    <a class="btn btn-secondary" href="{{ $editing ? route('tpo.drives.show', $driveId) : route('tpo.drives') }}">Back</a>
  </x-ui.page-header>

  <div id="drive-form" class="stack">
    <div data-summary></div>
    <div id="lock-note"></div>

    <x-ui.card id="job" title="Job details">
      <div class="fgrid">
        <x-ui.select name="company_id" label="Company" required placeholder="Choose a company"
          :options="$companies->pluck('name', 'id')->all()" hint="Only active companies are listed." />
        <x-ui.field name="title" label="Job title" required maxlength="150" placeholder="e.g. Software Engineer" />
        <x-ui.select name="employment_type" label="Employment type" selected="FULL_TIME"
          :options="['FULL_TIME' => 'Full time', 'INTERNSHIP' => 'Internship', 'INTERNSHIP_PPO' => 'Internship with PPO']" />
        <x-ui.select name="graduation_year" label="Batch (graduation year)" required placeholder="Choose a batch"
          :options="array_combine($years, $years)" hint="Only students graduating in this year can apply." />
        <x-ui.field name="location" label="Location" maxlength="150" />
        <div class="fgrid" style="grid-column:1/-1;gap:16px 24px">
          <x-ui.field name="ctc_lpa" label="CTC from (LPA)" type="number" step="0.01" min="0" hint="Enter both CTC values or neither." />
          <x-ui.field name="ctc_max_lpa" label="CTC up to (LPA)" type="number" step="0.01" min="0" />
        </div>
        <x-ui.textarea name="description" label="Job description" cls="full" maxlength="5000" rows="5" />
      </div>
    </x-ui.card>

    <x-ui.card id="schedule" title="Schedule">
      <div class="fgrid">
        <x-ui.field name="drive_date" label="Drive date" type="date" />
        <x-ui.field name="application_deadline" label="Application deadline" type="datetime-local"
          hint="India time. Applications close automatically after this." />
        <x-ui.checkbox name="allow_placed_students" label="Allow students who are already placed to apply"
          hint="Off by default: a placed student cannot apply to further drives." />
      </div>
    </x-ui.card>

    <x-ui.card id="eligibility" title="Eligibility criteria">
      <p class="meta" style="margin-bottom:16px">Leave a field empty to skip that criterion. Checks use verified marks only. These criteria are locked once the drive is published.</p>
      <div class="fgrid">
        <x-ui.field name="eligibility.min_cgpa" label="Minimum CGPA" type="number" step="0.01" min="0" max="10" />
        <x-ui.field name="eligibility.min_tenth_percentage" label="Minimum 10th %" type="number" step="0.01" min="0" max="100" />
        <x-ui.field name="eligibility.min_twelfth_percentage" label="Minimum 12th %" type="number" step="0.01" min="0" max="100" />
        <x-ui.field name="eligibility.min_diploma_percentage" label="Minimum diploma %" type="number" step="0.01" min="0" max="100"
          hint="Applies to lateral-entry students only." />
        <x-ui.field name="eligibility.max_active_backlogs" label="Maximum active backlogs" type="number" step="1" min="0" max="40" />
        <x-ui.field name="eligibility.max_total_backlogs" label="Maximum total backlogs" type="number" step="1" min="0" max="40" />
        <x-ui.checkbox name="eligibility.diploma_counts_as_twelfth" label="Count the diploma percentage as 12th for lateral-entry students" :checked="true"
          hint="Lateral-entry students have no 12th marks. Turn this off to require real 12th marks." />
      </div>
    </x-ui.card>

    <x-ui.card id="requirements" title="Additional requirements">
      <x-ui.textarea name="additional_requirements" label="Other requirements" maxlength="3000" rows="4"
        hint="Free text shown to students, for example: must be willing to relocate. It is not checked automatically and can be edited after publishing." />
    </x-ui.card>

    <x-ui.card id="branches" title="Target branches">
      <div class="field">
        <span class="lbl">Branches that may apply <span class="req" aria-hidden="true">*</span></span>
        <div data-err="branch_ids" id="branch-picker">
          @foreach ($departments as $department)
            <div class="dept-grp">
              <div class="dept-h">
                <b>{{ $department->name }}</b>
                <button type="button" class="linkbtn" data-toggle-dept="{{ $department->id }}">Select all</button>
              </div>
              <div class="checks">
                @foreach ($department->branches as $branch)
                  <label class="chk" for="b-{{ $branch->id }}">
                    <input type="checkbox" id="b-{{ $branch->id }}" data-branch="{{ $branch->id }}" data-dept="{{ $department->id }}">
                    <span>{{ $branch->name }}</span>
                  </label>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
        <span class="err" role="alert" hidden></span>
      </div>
    </x-ui.card>

    <div class="form-bar">
      <a class="btn btn-secondary" href="{{ $editing ? route('tpo.drives.show', $driveId) : route('tpo.drives') }}">Cancel</a>
      <button type="button" class="btn btn-primary" id="save">{{ $editing ? 'Save changes' : 'Save draft' }}</button>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    const { $, $$, api, notice, collect, showErrors, clearErrors, fail, flash, toLocalInput, fromLocalInput } = TPMS;
    const driveId = @json($driveId);
    const showUrl = @json($editing ? route('tpo.drives.show', $driveId) : null);
    const showBase = @json(url('/tpo/drives'));
    const root = $('#drive-form');
    const EDITABLE_AFTER_PUBLISH = ['description', 'additional_requirements', 'location', 'drive_date', 'application_deadline'];
    let status = 'DRAFT';

    const field = (name) => root.querySelector('[data-f="' + name + '"]');
    const branchBoxes = () => $$('[data-branch]', root);

    function fill(d) {
      status = d.status;
      const flat = {
        company_id: d.company ? d.company.id : '', title: d.title, employment_type: d.employment_type, graduation_year: d.graduation_year,
        location: d.location, ctc_lpa: d.ctc_lpa, ctc_max_lpa: d.ctc_max_lpa, description: d.description,
        drive_date: d.drive_date, application_deadline: toLocalInput(d.application_deadline), additional_requirements: d.additional_requirements,
      };
      Object.entries(flat).forEach(([k, v]) => { const el = field(k); if (el) el.value = v == null ? '' : v; });
      field('allow_placed_students').checked = !!d.allow_placed_students;
      const e = d.eligibility || {};
      ['min_cgpa', 'min_tenth_percentage', 'min_twelfth_percentage', 'min_diploma_percentage', 'max_active_backlogs', 'max_total_backlogs'].forEach((k) => {
        field('eligibility.' + k).value = e[k] == null ? '' : e[k];
      });
      field('eligibility.diploma_counts_as_twelfth').checked = e.diploma_counts_as_twelfth == null ? true : !!e.diploma_counts_as_twelfth;
      const picked = new Set((d.branches || []).map((b) => String(b.id)));
      branchBoxes().forEach((b) => { b.checked = picked.has(b.dataset.branch); });
      applyLocks();
    }

    /** Mirror of the server rule: after publishing only schedule and text fields may change. */
    function applyLocks() {
      const note = $('#lock-note');
      if (status === 'DRAFT') { note.innerHTML = ''; return; }
      const open = status === 'PUBLISHED';
      $$('[data-f]', root).forEach((el) => { el.disabled = !(open && EDITABLE_AFTER_PUBLISH.includes(el.dataset.f)); });
      branchBoxes().forEach((b) => { b.disabled = true; });
      $$('[data-toggle-dept]', root).forEach((b) => { b.disabled = true; });
      note.innerHTML = open
        ? notice('wait', 'lock', 'This drive is published. Criteria, company, batch and target branches are locked because students have already seen them. You can still change the description, requirements, location, drive date and deadline. To change anything else, cancel the drive and create a new one.')
        : notice('no', 'lock', 'This drive is ' + status.toLowerCase() + ' and can no longer be edited.');
      if (!open) $('#save').hidden = true;
    }

    $('#branch-picker').addEventListener('click', (e) => {
      const t = e.target.closest('[data-toggle-dept]');
      if (!t) return;
      const boxes = branchBoxes().filter((b) => b.dataset.dept === t.dataset.toggleDept);
      const all = boxes.every((b) => b.checked);
      boxes.forEach((b) => { b.checked = !all; });
      t.textContent = all ? 'Select all' : 'Clear';
    });

    $('#save').addEventListener('click', async (ev) => {
      const btn = ev.currentTarget;
      clearErrors(root);
      const body = collect(root);
      if (body.application_deadline !== undefined) body.application_deadline = fromLocalInput(body.application_deadline);
      if (status === 'DRAFT') body.branch_ids = branchBoxes().filter((b) => b.checked).map((b) => Number(b.dataset.branch));

      btn.disabled = true;
      try {
        const res = driveId
          ? await api('/tpo/drives/' + driveId, { method: 'PATCH', body })
          : await api('/tpo/drives', { method: 'POST', body });
        flash(driveId ? 'Drive saved.' : 'Draft created. Review it, then publish.');
        location.href = (showUrl || showBase + '/' + res.data.id);
      } catch (e) {
        fail(e, root);
      } finally { btn.disabled = false; }
    });

    if (driveId) {
      api('/tpo/drives/' + driveId).then((r) => fill(r.data)).catch((e) => fail(e));
    }
  })();
</script>
@endpush
