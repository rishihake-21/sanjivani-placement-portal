{{-- Styled file input. $id, $name (form field), $disabled (bool). Validated in public/js/student.js. --}}
@use('App\Support\StudentUi', 'U')
<div class="file">
  <label class="btn btn-secondary" for="{{ $id }}"@if (! empty($disabled)) style="opacity:.45;cursor:not-allowed"@endif>{!! U::icon('up', 'sm') !!} <span data-file-label>Choose file</span></label>
  <input type="file" id="{{ $id }}" name="{{ $name }}" class="sr" accept=".pdf,.jpg,.jpeg,.png" data-file @disabled(! empty($disabled))>
  <span class="nm" data-file-name>No file chosen</span>
</div>
