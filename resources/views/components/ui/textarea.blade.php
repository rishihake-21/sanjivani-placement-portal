@props(['name', 'label', 'required' => false, 'hint' => null, 'cls' => ''])
<div class="field {{ $cls }}">
  <label for="f-{{ $name }}">{{ $label }}@if ($required) <span class="req" aria-hidden="true">*</span>@endif</label>
  <textarea id="f-{{ $name }}" data-f="{{ $name }}" {{ $attributes }}>{{ $slot }}</textarea>
  @if ($hint)<span class="hint">{{ $hint }}</span>@endif
  <span class="err" role="alert" hidden></span>
</div>
