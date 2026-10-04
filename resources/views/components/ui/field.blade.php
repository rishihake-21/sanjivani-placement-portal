@props(['name', 'label', 'type' => 'text', 'required' => false, 'hint' => null, 'value' => null, 'cls' => ''])
<div class="field {{ $cls }}">
  <label for="f-{{ $name }}">{{ $label }}@if ($required) <span class="req" aria-hidden="true">*</span>@endif</label>
  <input id="f-{{ $name }}" data-f="{{ $name }}" type="{{ $type }}" @if (! is_null($value)) value="{{ $value }}" @endif {{ $attributes }}>
  @if ($hint)<span class="hint">{{ $hint }}</span>@endif
  <span class="err" role="alert" hidden></span>
</div>
