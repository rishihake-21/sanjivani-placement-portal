@props(['id', 'label'])
{{-- Value is filled in by the page script: TPMS.$('#id').innerHTML = ... --}}
<div class="stat"><span class="cap">{{ $label }}</span><div class="v" id="{{ $id }}">&ndash;</div></div>
