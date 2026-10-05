{{--
  Unlock drawer for one verified, locked academic record.
  $rec = AcademicRecordResource array (uses id, label, version).
  Posts {reason} (5 to 300 characters) to coordinator.x.academic.unlock. The module audits the unlock.
--}}
@use('App\Support\StudentUi', 'U')
<template id="tpl-unlock-{{ $rec['id'] }}">
  <div class="scrim" data-drawer-close></div>
  <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="dr-title">
    <form class="dr-form" data-cajax data-action="{{ route('coordinator.x.academic.unlock', $rec['id']) }}" data-reason-required="always" data-min-reason="5" data-done="{{ $rec['label'] }} unlocked." novalidate>
      <div class="dr-h">
        <div>
          <h2 id="dr-title">Unlock {{ $rec['label'] }}</h2>
          <p class="meta">Verified &middot; version {{ $rec['version'] }}</p>
        </div>
        <button type="button" class="icon-btn" data-drawer-close aria-label="Close">{!! U::icon('x') !!}</button>
      </div>

      <div class="dr-b">
        @include('student.partials.notice', ['kind' => 'wait', 'ic' => 'lock', 'text' => 'Unlocking lets the student edit this verified record. Anything they save becomes a revision that you review again. The unlock is audited with your reason.'])
        <div class="field" data-field="reason">
          <label for="ul-reason">Reason <span class="req" aria-hidden="true">*</span></label>
          <textarea id="ul-reason" name="reason" maxlength="300" required data-msg="Enter a reason."></textarea>
          <span class="hint">5 to 300 characters.</span>
        </div>
      </div>

      <div class="dr-f">
        <button type="button" class="btn btn-secondary" data-drawer-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Unlock record</button>
      </div>
    </form>
  </aside>
</template>
