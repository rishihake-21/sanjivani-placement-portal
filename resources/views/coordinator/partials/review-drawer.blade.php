{{--
  Review drawer for one queue item. $kind: academic | experience | document, $item = the queue entry exactly as
  GET /api/coordinator/verification-queue returns it (resource fields + student + previous_verified).
--}}
@use('App\Support\StudentUi', 'U')
@use('App\Enums\RejectionReason')
@php
  $isAcad = $kind === 'academic';
  $isExp = $kind === 'experience';
  $isDoc = $kind === 'document';
  $key = $isDoc ? $item['uuid'] : $item['id'];
  $prev = $item['previous_verified'] ?? null;
  $who = $item['student'];
  $title = $isAcad ? $item['label'] : ($isExp ? U::expType($item['type']) . ' at ' . $item['organization'] : U::docType($item['document_type']));
  $file = $isAcad ? ($item['document'] ?? null) : ($isExp ? ($item['certificate'] ?? null) : $item);

  if ($isAcad) {
      $defs = $item['level'] === 'DEGREE_SEM'
          ? [['SGPA', 'sgpa', 'num'], ['CGPA', 'cgpa', 'num'], ['Backlogs in term', 'backlogs_in_term', ''], ['Active backlogs after term', 'active_backlogs_after_term', '']]
          : [['Institution', 'institution_name', ''], ['Board or university', 'board_or_university', ''], ['Passing year', 'passing_year', ''], ['Percentage', 'percentage', 'pct'], ['Obtained marks', 'obtained_marks', 'trim'], ['Total marks', 'total_marks', 'trim']];
      $fields = [];
      foreach ($defs as [$label, $field, $fmt]) {
          $show = function ($rec) use ($field, $fmt) {
              if ($rec === null) { return null; }
              $x = $rec[$field] ?? null;
              if ($x === null || $x === '') { return '–'; }
              return $fmt === 'num' ? U::num2($x) : ($fmt === 'pct' ? U::num2($x) . '%' : ($fmt === 'trim' ? U::trimNum($x) : (string) $x));
          };
          $fields[] = ['label' => $label, 'cur' => $show($item), 'prev' => $show($prev)];
      }
  } elseif ($isExp) {
      $parts = [['Type', fn ($r) => U::expType($r['type'])], ['Organization', fn ($r) => U::orDash($r['organization'])], ['Role', fn ($r) => U::orDash($r['role_title'])], ['Dates', fn ($r) => U::expDates($r)], ['Description', fn ($r) => U::orDash($r['description'] ?? null)]];
      $fields = [];
      foreach ($parts as [$label, $fn]) {
          $fields[] = ['label' => $label, 'cur' => $fn($item), 'prev' => $prev ? $fn($prev) : null];
      }
  } else {
      $fields = [];
  }

  $approve = $isAcad ? route('coordinator.x.academic.approve', $key) : ($isExp ? route('coordinator.x.experience.approve', $key) : route('coordinator.x.document.approve', $key));
  $reject = $isAcad ? route('coordinator.x.academic.reject', $key) : ($isExp ? route('coordinator.x.experience.reject', $key) : route('coordinator.x.document.reject', $key));
  $submitted = $isDoc ? ($item['uploaded_at'] ?? null) : ($item['submitted_at'] ?? null);
@endphp
<template id="tpl-review-{{ $kind }}-{{ $key }}">
  <div class="scrim" data-drawer-close></div>
  <aside class="drawer wide" role="dialog" aria-modal="true" aria-labelledby="dr-title">
    <div class="dr-h">
      <div>
        <h2 id="dr-title">{{ $who['full_name'] }}</h2>
        <p class="meta">{{ $who['university_id'] }} &middot; {{ $title }}@if ($submitted) &middot; submitted {{ U::date($submitted) }}@endif</p>
      </div>
      <button type="button" class="icon-btn" data-drawer-close aria-label="Close">{!! U::icon('x') !!}</button>
    </div>

    <div class="dr-b">
      @if ($prev)
        @include('student.partials.notice', ['kind' => 'wait', 'ic' => 'info', 'text' => 'This is a revision of a verified entry. Changed values are marked. The verified values stay in use until you approve.'])
      @endif
      @if (! $isDoc)
        @include('coordinator.partials.compare', ['fields' => $fields, 'hasPrev' => (bool) $prev])
      @endif
      <div>
        <p class="sect-h">{{ $isAcad ? 'Marksheet' : ($isExp ? 'Certificate' : 'Document') }}</p>
        @include('coordinator.partials.doc-box', ['doc' => $file])
      </div>

      <form class="reject-box" data-cajax data-action="{{ $reject }}" data-reason-required="other" data-done="{{ $title }} rejected." data-reject-box hidden novalidate>
        <div class="field" data-field="reason_code">
          <label for="rj-code">Reason <span class="req" aria-hidden="true">*</span></label>
          <select id="rj-code" name="reason_code" required data-msg="Choose a reason.">
            <option value="">Select a reason</option>
            @foreach (RejectionReason::cases() as $c)
              <option value="{{ $c->value }}">{{ $c->label() }}</option>
            @endforeach
          </select>
        </div>
        <div class="field" data-field="reason">
          <label for="rj-text">Note <span class="req" data-other-req aria-hidden="true" hidden>*</span></label>
          <textarea id="rj-text" name="reason" maxlength="500"></textarea>
          <span class="hint">Required only when the reason is Other. Up to 500 characters. The student sees this.</span>
        </div>
        <div class="row wrap" style="justify-content:flex-end">
          <button type="button" class="btn btn-secondary" data-reject-cancel>Cancel</button>
          <button type="submit" class="btn btn-danger">Confirm rejection</button>
        </div>
      </form>
    </div>

    <div class="dr-f">
      <div class="row wrap" data-approve-bar>
        <button type="button" class="btn btn-primary" data-post="{{ $approve }}" data-done="{{ $title }} approved.">Approve</button>
        <button type="button" class="btn btn-danger" data-reject-open>Reject</button>
      </div>
    </div>
  </aside>
</template>
