@props(['name', 'label', 'options' => [], 'placeholder' => null, 'required' => false, 'hint' => null, 'selected' => null, 'cls' => '', 'filter' => false])
{{-- $options: [value => label]. With filter=true the control is a list filter ([data-filter]) instead of a form field. --}}
<div class="field {{ $cls }}">
  <label for="{{ $filter ? 'flt-' : 'f-' }}{{ $name }}">{{ $label }}@if ($required) <span class="req" aria-hidden="true">*</span>@endif</label>
  <select id="{{ $filter ? 'flt-' : 'f-' }}{{ $name }}" @if ($filter) data-filter="{{ $name }}" @else data-f="{{ $name }}" @endif {{ $attributes }}>
    @if (! is_null($placeholder))<option value="">{{ $placeholder }}</option>@endif
    @foreach ($options as $value => $text)
      <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
    @endforeach
  </select>
  @if ($hint)<span class="hint">{{ $hint }}</span>@endif
  <span class="err" role="alert" hidden></span>
</div>
