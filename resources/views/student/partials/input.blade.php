{{--
  One labelled input. Needs $name and $label. Optional: $type (text), $value, $req, $hint, $max (maxlength),
  $ph, $inputmode, $step, $min, $maxv, $disabled, $cls (extra wrapper classes), $nullable, $id.
  The wrapper carries data-field so public/js/student.js can attach server and client errors to it.
--}}
@php
  $id = $id ?? 'f-' . $name;
@endphp
<div class="field{{ ! empty($cls) ? ' ' . $cls : '' }}" data-field="{{ $name }}">
  <label for="{{ $id }}">{{ $label }}@if (! empty($req)) <span class="req" aria-hidden="true">*</span>@endif</label>
  <input id="{{ $id }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" value="{{ $value ?? '' }}"
    @if (! empty($max)) maxlength="{{ $max }}"@endif
    @if (! empty($ph)) placeholder="{{ $ph }}"@endif
    @if (! empty($inputmode)) inputmode="{{ $inputmode }}"@endif
    @if (isset($step)) step="{{ $step }}"@endif
    @if (isset($min)) min="{{ $min }}"@endif
    @if (isset($maxv)) max="{{ $maxv }}"@endif
    @if (! empty($nullable)) data-nullable @endif
    @disabled(! empty($disabled))>
  @if (! empty($hint))<span class="hint">{{ $hint }}</span>@endif
</div>
