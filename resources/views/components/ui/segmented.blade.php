@props(['name', 'label', 'options' => [], 'allLabel' => 'All', 'selected' => ''])
{{--
  Segmented filter for a short list of choices (a handful of batches). The value lives in a hidden
  input[data-filter], so TPMS.readFilters() / filterValues() and the page's own `change` listener work
  exactly as they do for a <select>; tpms-portal.js turns a click on a segment into that change.
  More than six choices stop fitting a header, so they fall back to a normal select.
--}}
@if (count($options) > 6)
  <x-ui.select filter :name="$name" :label="$label" :placeholder="$allLabel" :options="$options" :selected="$selected" />
@else
  <div class="seg-wrap">
    <span class="cap" id="seg-{{ $name }}">{{ $label }}</span>
    <div class="segmented" role="group" aria-labelledby="seg-{{ $name }}">
      <input type="hidden" id="flt-{{ $name }}" data-filter="{{ $name }}" value="{{ $selected }}">
      <button type="button" data-value="" aria-pressed="{{ (string) $selected === '' ? 'true' : 'false' }}">{{ $allLabel }}</button>
      @foreach ($options as $value => $text)
        <button type="button" data-value="{{ $value }}" aria-pressed="{{ (string) $selected === (string) $value ? 'true' : 'false' }}">{{ $text }}</button>
      @endforeach
    </div>
  </div>
@endif
