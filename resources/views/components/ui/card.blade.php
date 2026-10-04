@props(['id' => null, 'title' => null, 'actions' => null])
<section {{ $attributes->merge(['class' => 'card']) }} @if ($id) id="{{ $id }}" aria-labelledby="h-{{ $id }}" @endif>
  @if ($title)
    <div class="card-h">
      <h2 @if ($id) id="h-{{ $id }}" @endif>{{ $title }}</h2>
      @if ($actions)<div class="acts">{{ $actions }}</div>@endif
    </div>
  @endif
  {{ $slot }}
</section>
