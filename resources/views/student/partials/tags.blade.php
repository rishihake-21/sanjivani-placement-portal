{{-- Chip input. $name, $label (singular, for the aria label), $values (list), $max, $len, $disabled. --}}
@use('App\Support\StudentUi', 'U')
<div class="tags" data-tags="{{ $name }}" data-max="{{ $max }}" data-len="{{ $len }}">
  @foreach ($values ?? [] as $v)
    <span class="chip" data-v="{{ $v }}">{{ $v }}<button type="button" data-chip-remove aria-label="Remove {{ $v }}" @disabled(! empty($disabled))>{!! U::icon('x', 'sm') !!}</button></span>
  @endforeach
  <input type="text" id="f-{{ $name }}" aria-label="Add {{ $label }}" placeholder="Type and press Enter" autocomplete="off" @disabled(! empty($disabled))>
</div>
