@props(['title', 'meta' => null])
{{-- .ph from the design: title + meta on the left, action buttons (slot) on the right --}}
<div class="ph">
  <div>
    <h1>{{ $title }}</h1>
    @if ($meta)<p class="meta">{!! $meta !!}</p>@endif
  </div>
  @if (! $slot->isEmpty())
    <div class="row wrap">{{ $slot }}</div>
  @endif
</div>
