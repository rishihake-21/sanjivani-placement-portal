@props(['name', 'label', 'hint' => null, 'checked' => false])
<div class="field">
  <label class="chk" for="f-{{ $name }}">
    <input type="checkbox" id="f-{{ $name }}" data-f="{{ $name }}" @checked($checked) {{ $attributes }}>
    <span>{{ $label }}</span>
  </label>
  @if ($hint)<span class="hint">{{ $hint }}</span>@endif
  <span class="err" role="alert" hidden></span>
</div>
