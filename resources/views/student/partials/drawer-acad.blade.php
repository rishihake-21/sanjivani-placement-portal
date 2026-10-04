{{--
  Academic record drawer. $rec (array|null for a new record), $mode: add | edit | revise | view, $avail (keys, add only).
  edit   = draft or rejected: saved in place.   revise = verified: saving starts a new draft revision.
--}}
@use('App\Support\StudentUi', 'U')
@php
  $isNew = $rec === null;
  $lv = $rec['level'] ?? null;
  $isSem = $lv === 'DEGREE_SEM';
  $label = $isNew ? '' : U::levelLabel($lv, $rec['semester']);
  $doc = $rec['document'] ?? null;
  $rej = $rec['rejection'] ?? null;
  $title = $isNew ? 'Add academic record' : ($mode === 'view' ? $label : ($mode === 'revise' ? 'Revise ' . $label : 'Edit ' . $label));
  $haveDoc = ! $isNew && $doc && $doc['status'] === 'PENDING' && $mode !== 'revise';
  $firstIsSem = $isNew && ($avail[0][0] ?? null) === 'DEGREE_SEM';
  $maxYear = (int) date('Y') + 1;
@endphp
<template id="tpl-acad-{{ $isNew ? 'new' : $rec['id'] }}">
  <div class="scrim" data-drawer-close></div>
  <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="dr-title">
    <form class="dr-form" data-ajax="acad" data-mode="{{ $mode }}" novalidate
      @if ($isNew)
        data-store="{{ route('student.x.academic.store') }}"
      @else
        data-level="{{ $lv }}" data-semester="{{ $rec['semester'] }}" data-update="{{ route('student.x.academic.update', $rec['id']) }}"
        data-have-doc="{{ $haveDoc ? 1 : 0 }}"
      @endif>
      <div class="dr-h">
        <div>
          <h2 id="dr-title">{{ $title }}</h2>
          <p class="meta">@if ($isNew)Saved as a draft until you submit it.@else{{ $label }} &middot; version {{ $rec['version'] }}@endif</p>
        </div>
        <button type="button" class="icon-btn" data-drawer-close aria-label="Close">{!! U::icon('x') !!}</button>
      </div>

      <div class="dr-b">
        @if ($rej && $rec['status'] === 'REJECTED')
          <div class="notice no">{!! U::icon('info', 'sm') !!}<div>{{ $rej['label'] }}@if (! empty($rej['text'])): {{ $rej['text'] }}@endif @if ($rej['code'] === 'DATA_MISMATCH' && $doc && $doc['status'] === 'PENDING')<br>The same marksheet can be reused.@endif</div></div>
        @endif
        @if ($mode === 'revise')
          @include('student.partials.notice', ['kind' => 'wait', 'ic' => 'info', 'text' => 'Saving creates a revision. The verified values stay in use until the coordinator approves the new ones. The new values need a fresh marksheet.'])
        @endif
        @if ($mode === 'view' && $rec['status'] === 'PENDING')
          @include('student.partials.notice', ['kind' => 'neutral', 'ic' => 'info', 'text' => 'This record is under review and cannot be edited right now.'])
        @endif
        @if ($mode === 'view' && $rec['status'] === 'VERIFIED' && ! empty($rec['locked']))
          @include('student.partials.notice', ['kind' => 'neutral', 'ic' => 'lock', 'text' => 'This record is locked. Ask your T&P Coordinator to unlock it before editing.'])
        @endif

        @if ($mode === 'view')
          <dl class="kv">
            <dt>Status</dt><dd>{!! U::badge('rec', $rec['status']) !!}</dd>
            @if (! $isSem)
              <dt>Institution</dt><dd>{{ U::orDash($rec['institution_name']) }}</dd>
              <dt>Board or university</dt><dd>{{ U::orDash($rec['board_or_university']) }}</dd>
              <dt>Passing year</dt><dd>{{ U::orDash($rec['passing_year']) }}</dd>
              <dt>Percentage</dt><dd>{{ $rec['percentage'] !== null ? U::num2($rec['percentage']) . '%' : '–' }}</dd>
              <dt>Marks</dt><dd>{{ $rec['obtained_marks'] !== null ? U::trimNum($rec['obtained_marks']) . ' / ' . U::trimNum($rec['total_marks']) : '–' }}</dd>
            @else
              <dt>SGPA</dt><dd>{{ $rec['sgpa'] !== null ? U::num2($rec['sgpa']) : '–' }}</dd>
              <dt>CGPA</dt><dd>{{ $rec['cgpa'] !== null ? U::num2($rec['cgpa']) : '–' }}</dd>
              <dt>Backlogs in term</dt><dd>{{ U::orDash($rec['backlogs_in_term']) }}</dd>
              <dt>Active backlogs after term</dt><dd>{{ U::orDash($rec['active_backlogs_after_term']) }}</dd>
            @endif
            <dt>Submitted</dt><dd>{{ U::orDash(U::dateTime($rec['submitted_at'])) }}</dd>
            <dt>Reviewed</dt><dd>{{ U::orDash(U::dateTime($rec['reviewed_at'])) }}</dd>
            <dt>Marksheet</dt><dd>@if ($doc)@include('student.partials.file-link', ['doc' => $doc]) {!! U::badge('doc', $doc['status']) !!}@else&ndash;@endif</dd>
          </dl>
        @else
          @if ($isNew)
            <div class="field" data-field="key">
              <label for="f-key">Record</label>
              <select id="f-key" data-key-select>
                @foreach ($avail as [$klv, $ksem])
                  <option value="{{ $klv }}|{{ $ksem }}">{{ U::levelLabel($klv, $ksem) }}</option>
                @endforeach
              </select>
            </div>
          @endif

          @if ($isNew || ! $isSem)
            <div class="dfg" data-group="pct"@if ($firstIsSem) hidden @endif>
              @include('student.partials.input', ['name' => 'institution_name', 'label' => 'Institution', 'value' => $rec['institution_name'] ?? '', 'max' => 150, 'cls' => 'full', 'nullable' => true])
              @include('student.partials.input', ['name' => 'board_or_university', 'label' => 'Board or university', 'value' => $rec['board_or_university'] ?? '', 'max' => 150, 'cls' => 'full', 'nullable' => true])
              @include('student.partials.input', ['name' => 'passing_year', 'label' => 'Passing year', 'type' => 'number', 'value' => $rec['passing_year'] ?? '', 'step' => '1', 'min' => 1990, 'maxv' => $maxYear, 'inputmode' => 'decimal', 'nullable' => true])
              @include('student.partials.input', ['name' => 'percentage', 'label' => 'Percentage', 'type' => 'number', 'value' => $rec['percentage'] ?? '', 'step' => '0.01', 'min' => 0, 'maxv' => 100, 'inputmode' => 'decimal', 'req' => true])
              @include('student.partials.input', ['name' => 'obtained_marks', 'label' => 'Obtained marks', 'type' => 'number', 'value' => $rec['obtained_marks'] ?? '', 'step' => '0.01', 'min' => 0, 'inputmode' => 'decimal', 'nullable' => true])
              @include('student.partials.input', ['name' => 'total_marks', 'label' => 'Total marks', 'type' => 'number', 'value' => $rec['total_marks'] ?? '', 'step' => '0.01', 'min' => 0, 'inputmode' => 'decimal', 'nullable' => true])
            </div>
          @endif
          @if ($isNew || $isSem)
            <div class="dfg" data-group="sem"@if ($isNew && ! $firstIsSem) hidden @endif>
              @include('student.partials.input', ['name' => 'sgpa', 'label' => 'SGPA', 'type' => 'number', 'value' => $rec['sgpa'] ?? '', 'step' => '0.01', 'min' => 0, 'maxv' => 10, 'inputmode' => 'decimal', 'req' => true, 'hint' => '0 to 10'])
              @include('student.partials.input', ['name' => 'cgpa', 'label' => 'CGPA', 'type' => 'number', 'value' => $rec['cgpa'] ?? '', 'step' => '0.01', 'min' => 0, 'maxv' => 10, 'inputmode' => 'decimal', 'hint' => 'As printed, if shown', 'nullable' => true])
              @include('student.partials.input', ['name' => 'backlogs_in_term', 'label' => 'Backlogs in term', 'type' => 'number', 'value' => $rec['backlogs_in_term'] ?? 0, 'step' => '1', 'min' => 0, 'maxv' => 20, 'inputmode' => 'decimal'])
              @include('student.partials.input', ['name' => 'active_backlogs_after_term', 'label' => 'Active backlogs after term', 'type' => 'number', 'value' => $rec['active_backlogs_after_term'] ?? 0, 'step' => '1', 'min' => 0, 'maxv' => 40, 'inputmode' => 'decimal'])
            </div>
          @endif

          <div class="field" data-field="document">
            <span class="lbl">Marksheet @if (! $doc) <span class="req" aria-hidden="true">*</span>@endif</span>
            @if ($doc && $mode !== 'revise')
              <div class="row wrap">@include('student.partials.file-link', ['doc' => $doc]) {!! U::badge('doc', $doc['status']) !!}<span class="meta num">Version {{ $doc['version'] }}</span></div>
            @endif
            @include('student.partials.file-box', ['id' => 'dr-file', 'name' => 'document', 'disabled' => false])
            <span class="hint">PDF, JPG or PNG, up to 5 MB.</span>
          </div>
        @endif
      </div>

      <div class="dr-f">
        @if ($mode === 'view')
          <button type="button" class="btn btn-secondary" data-drawer-close>Close</button>
        @else
          @if ($rec && $rec['status'] === 'DRAFT' && $mode === 'edit')
            <button type="button" class="btn btn-danger" data-delete="{{ route('student.x.academic.destroy', $rec['id']) }}" data-done="Draft deleted.">Delete draft</button>
          @endif
          <span class="sp"></span>
          <button type="button" class="btn btn-secondary" data-drawer-close>Cancel</button>
          <button type="submit" class="btn btn-secondary" data-intent="0">{{ $rec && $rec['status'] === 'REJECTED' ? 'Save changes' : 'Save draft' }}</button>
          <button type="submit" class="btn btn-primary" data-intent="1">Submit for review</button>
        @endif
      </div>
    </form>
  </aside>
</template>
