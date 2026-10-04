{{--
  Experience drawer. $e (array|null for a new entry), $mode: add | edit | revise | view.
  edit   = self-declared, draft or rejected: saved in place.   revise = verified: saving starts a new draft revision.
--}}
@use('App\Support\StudentUi', 'U')
@php
  $isNew = $e === null;
  $cert = $e['certificate'] ?? null;
  $rej = $e['rejection'] ?? null;
  $ongoing = ! $isNew && ! empty($e['is_ongoing']);
  $today = date('Y-m-d');
  $title = $isNew ? 'Add experience' : ($mode === 'view' ? 'Experience' : ($mode === 'revise' ? 'Revise experience' : 'Edit experience'));
  $pendingCert = ! $isNew && $cert && $cert['status'] === 'PENDING' && in_array($e['status'], ['SELF_DECLARED', 'DRAFT', 'REJECTED'], true);
  $canDelete = ! $isNew && in_array($e['status'], ['SELF_DECLARED', 'DRAFT'], true) && $mode === 'edit';
@endphp
<template id="tpl-exp-{{ $isNew ? 'new' : $e['id'] }}">
  <div class="scrim" data-drawer-close></div>
  <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="dr-title">
    <form class="dr-form" data-ajax="exp" data-mode="{{ $mode }}" novalidate
      @if ($isNew)
        data-store="{{ route('student.x.experience.store') }}"
      @else
        data-update="{{ $apiUrl('student.x.experience.update', 'api/student/experiences/' . $e['id']) }}"
      @endif
      data-pending-cert="{{ $pendingCert ? 1 : 0 }}">
      <div class="dr-h">
        <div>
          <h2 id="dr-title">{{ $title }}</h2>
          <p class="meta">@if ($isNew)New entry @else{{ U::expType($e['type']) }} &middot; version {{ $e['version'] }}@endif</p>
        </div>
        <button type="button" class="icon-btn" data-drawer-close aria-label="Close">{!! U::icon('x') !!}</button>
      </div>

      <div class="dr-b">
        @if ($rej && $e['status'] === 'REJECTED')
          <div class="notice no">{!! U::icon('info', 'sm') !!}<div>{{ $rej['label'] }}@if (! empty($rej['text'])): {{ $rej['text'] }}@endif</div></div>
        @endif
        @if ($mode === 'revise')
          @include('student.partials.notice', ['kind' => 'wait', 'ic' => 'info', 'text' => 'Saving creates a revision. The verified entry stays in use until the coordinator approves the new one. The revision needs a fresh certificate.'])
        @endif
        @if ($mode === 'view')
          @include('student.partials.notice', ['kind' => 'neutral', 'ic' => 'info', 'text' => 'This entry is under review and cannot be edited right now.'])
        @endif
        @if ($isNew || (! $isNew && $e['status'] === 'SELF_DECLARED'))
          @include('student.partials.notice', ['kind' => 'neutral', 'ic' => 'info', 'text' => 'Without a certificate the entry is saved as self-declared and shown as unverified.'])
        @endif

        @if ($mode === 'view')
          <dl class="kv">
            <dt>Status</dt><dd>{!! U::badge('rec', $e['status']) !!}</dd>
            <dt>Type</dt><dd>{{ U::expType($e['type']) }}</dd>
            <dt>Organization</dt><dd>{{ U::orDash($e['organization']) }}</dd>
            <dt>Role</dt><dd>{{ U::orDash($e['role_title']) }}</dd>
            <dt>Dates</dt><dd>{{ U::expDates($e) }}</dd>
            <dt>Description</dt><dd>{{ U::orDash($e['description']) }}</dd>
            <dt>Submitted</dt><dd>{{ U::orDash(U::dateTime($e['submitted_at'])) }}</dd>
            <dt>Certificate</dt><dd>@if ($cert)@include('student.partials.file-link', ['doc' => $cert]) {!! U::badge('doc', $cert['status']) !!}@else&ndash;@endif</dd>
          </dl>
        @else
          <div class="dfg">
            <div class="field full" data-field="type">
              <label for="f-type">Type <span class="req" aria-hidden="true">*</span></label>
              <select id="f-type" name="type">
                @foreach (U::EXP_TYPE as $code => $name)
                  <option value="{{ $code }}"@if (($e['type'] ?? 'INTERNSHIP') === $code) selected @endif>{{ $name }}</option>
                @endforeach
              </select>
            </div>
            @include('student.partials.input', ['name' => 'organization', 'label' => 'Organization', 'value' => $e['organization'] ?? '', 'max' => 150, 'cls' => 'full', 'req' => true])
            @include('student.partials.input', ['name' => 'role_title', 'label' => 'Role', 'value' => $e['role_title'] ?? '', 'max' => 150, 'cls' => 'full', 'req' => true])
            @include('student.partials.input', ['name' => 'start_date', 'label' => 'Start date', 'type' => 'date', 'value' => $e['start_date'] ?? '', 'maxv' => $today, 'req' => true])
            <div class="field" data-field="end_date">
              <label for="f-end_date">End date <span class="req" data-end-req aria-hidden="true"@if ($ongoing) hidden @endif>*</span></label>
              <input id="f-end_date" name="end_date" type="date" value="{{ $ongoing ? '' : ($e['end_date'] ?? '') }}" max="{{ $today }}" @disabled($ongoing)>
            </div>
            <div class="full"><label class="chk"><input type="checkbox" id="f-is_ongoing" name="is_ongoing" data-ongoing @checked($ongoing)> Ongoing</label></div>
            <div class="field full" data-field="description">
              <label for="f-description">Description</label>
              <textarea id="f-description" name="description" maxlength="2000" data-nullable>{{ $e['description'] ?? '' }}</textarea>
              <span class="hint">Up to 2000 characters.</span>
            </div>
          </div>

          <div class="field" data-field="certificate">
            <span class="lbl">Certificate</span>
            @if ($cert)
              <div class="row wrap">@include('student.partials.file-link', ['doc' => $cert]) {!! U::badge('doc', $cert['status']) !!}</div>
            @endif
            @include('student.partials.file-box', ['id' => 'dr-file', 'name' => 'certificate', 'disabled' => false])
            <span class="hint">PDF, JPG or PNG, up to 5 MB. Attaching a certificate also submits the entry for review.</span>
          </div>
        @endif
      </div>

      <div class="dr-f">
        @if ($mode === 'view')
          <button type="button" class="btn btn-secondary" data-drawer-close>Close</button>
        @else
          @if ($canDelete)
            <button type="button" class="btn btn-danger" data-delete="{{ $apiUrl('student.x.experience.destroy', 'api/student/experiences/' . $e['id']) }}" data-done="Entry deleted.">Delete entry</button>
          @endif
          <span class="sp"></span>
          <button type="button" class="btn btn-secondary" data-drawer-close>Cancel</button>
          <button type="submit" class="btn btn-primary" data-intent="{{ $pendingCert ? 1 : 0 }}" data-save-main>{{ $pendingCert ? 'Save and submit' : 'Save' }}</button>
        @endif
      </div>
    </form>
  </aside>
</template>
