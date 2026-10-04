@props(['kind' => 'neutral', 'icon' => 'info'])
{{-- kind: neutral | wait | no | ok --}}
<div {{ $attributes->merge(['class' => 'notice ' . $kind]) }}>
  <svg class="icon sm" aria-hidden="true"><use href="#i-{{ $icon }}"/></svg>
  <div>{{ $slot }}</div>
</div>
