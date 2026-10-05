{{-- $pg = ['current_page','last_page','total','prev_url','next_url'] (urls keep the active filters) --}}
<div class="pager">
  <span class="meta num">{{ $pg['total'] }} {{ $pg['total'] === 1 ? 'result' : 'results' }}@if ($pg['last_page'] > 1) &middot; page {{ $pg['current_page'] }} of {{ $pg['last_page'] }}@endif</span>
  @if ($pg['last_page'] > 1)
    <div class="row">
      @if ($pg['prev_url'])<a class="btn btn-secondary" href="{{ $pg['prev_url'] }}">Previous</a>@endif
      @if ($pg['next_url'])<a class="btn btn-secondary" href="{{ $pg['next_url'] }}">Next</a>@endif
    </div>
  @endif
</div>
