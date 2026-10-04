{{-- One notification. $n (see StudentPageController::loadNotifications), $withAction (show "Mark as read"). --}}
@use('App\Support\StudentUi', 'U')
<div class="li">
  <div class="l">
    <div class="t{{ $n['read'] ? '' : ' unread' }}">{{ $n['subject'] }}</div>
    @if (U::reasonLine($n) !== '')<div class="meta">{{ U::reasonLine($n) }}</div>@endif
    <div class="meta">{{ U::dateTime($n['created_at']) }}</div>
  </div>
  <div class="r">
    {!! U::badge('dec', $n['decision']) !!}
    @if (! empty($withAction) && ! $n['read'])
      <button type="button" class="btn btn-secondary" data-post="{{ route('student.x.notifications.read', $n['id']) }}" data-done="Marked as read.">Mark as read</button>
    @endif
  </div>
</div>
