{{--
  "Request re-upload" drawer on the student detail page.
  $student    profile array (uses id, full_name)
  $records    the student's academic records (AcademicRecordResource arrays)
  $experiences the student's experiences (ExperienceResource arrays)
  $documents  the student's documents (DocumentResource arrays). A document can only be targeted when its row also
              carries the numeric `id` the API expects as subject_id (DocumentResource itself exposes only `uuid`,
              so the controller has to add `id` to each row; rows without it are left out of the list).
  Only accepted items can be sent back: VERIFIED records and experiences, APPROVED resume and other certificates.
  Posts {subject_type, subject_id, reason (10 to 500)} to coordinator.x.reupload.store.
--}}
@use('App\Support\StudentUi', 'U')
@php
  $subjects = [];
  foreach ($records as $rr) {
      if ($rr['status'] === 'VERIFIED') {
          $subjects[] = ['ACADEMIC_RECORD|' . $rr['id'], 'Academic record: ' . $rr['label']];
      }
  }
  foreach ($experiences as $xx) {
      if ($xx['status'] === 'VERIFIED') {
          $subjects[] = ['EXPERIENCE|' . $xx['id'], 'Experience: ' . U::expType($xx['type']) . ' at ' . $xx['organization']];
      }
  }
  foreach ($documents as $dd) {
      if ($dd['status'] === 'APPROVED' && isset($dd['id']) && in_array($dd['document_type'], ['RESUME', 'CERT_OTHER'], true)) {
          $subjects[] = ['DOCUMENT|' . $dd['id'], 'Document: ' . U::docType($dd['document_type']) . ' (' . $dd['original_name'] . ')'];
      }
  }
@endphp
<template id="tpl-reupload">
  <div class="scrim" data-drawer-close></div>
  <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="dr-title">
    <form class="dr-form" data-cajax data-action="{{ route('coordinator.x.reupload.store', $student['id']) }}" data-reason-required="always" data-min-reason="10" data-done="Re-upload requested from {{ $student['full_name'] }}." novalidate>
      <div class="dr-h">
        <div>
          <h2 id="dr-title">Request re-upload</h2>
          <p class="meta">{{ $student['full_name'] }} &middot; {{ $student['university_id'] }}</p>
        </div>
        <button type="button" class="icon-btn" data-drawer-close aria-label="Close">{!! U::icon('x') !!}</button>
      </div>

      <div class="dr-b">
        @if (count($subjects))
          @include('student.partials.notice', ['kind' => 'neutral', 'ic' => 'info', 'text' => 'The student is notified and the item reopens for editing. The verified values stay in use until you approve the new ones.'])
          <div class="field" data-field="subject">
            <label for="ru-subject">Item to upload again <span class="req" aria-hidden="true">*</span></label>
            <select id="ru-subject" name="subject">
              <option value="">Select an item</option>
              @foreach ($subjects as [$val, $text])
                <option value="{{ $val }}">{{ $text }}</option>
              @endforeach
            </select>
          </div>
          <div class="field" data-field="reason">
            <label for="ru-reason">Reason <span class="req" aria-hidden="true">*</span></label>
            <textarea id="ru-reason" name="reason" maxlength="500" required data-msg="Enter a reason."></textarea>
            <span class="hint">10 to 500 characters. The student sees this.</span>
          </div>
        @else
          <p class="empty">Nothing has been accepted yet, so there is nothing to send back.</p>
        @endif
      </div>

      <div class="dr-f">
        <button type="button" class="btn btn-secondary" data-drawer-close>Cancel</button>
        @if (count($subjects))
          <button type="submit" class="btn btn-primary">Send request</button>
        @endif
      </div>
    </form>
  </aside>
</template>
