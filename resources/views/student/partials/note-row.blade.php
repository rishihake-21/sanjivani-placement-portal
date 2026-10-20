{{-- One notification. Supports legacy RecordReviewed notices and placement notices. --}}
@use('App\Support\StudentUi', 'U')
@php
  $title = $n['title'] ?? $n['subject'] ?? 'Notification';
  $body = $n['body'] ?? null;
  $reasonLine = U::reasonLine($n);
  $kind = $n['kind'] ?? null;
  $kindLabels = [
      'drive_published' => 'New drive',
      'drive_cancelled' => 'Drive cancelled',
      'stage_changed' => 'Stage updated',
      'offer_recorded' => 'Offer recorded',
      'offer_declined' => 'Offer declined',
      'placed' => 'Placed',
  ];
  $kindVariants = [
      'drive_published' => 'ok',
      'drive_cancelled' => 'no',
      'stage_changed' => 'wait',
      'offer_recorded' => 'ok',
      'offer_declined' => 'no',
      'placed' => 'ok',
  ];
  $badgeLabel = $n['decision'] ?? ($kindLabels[$kind] ?? ($kind ? ucwords(str_replace('_', ' ', $kind)) : 'Update'));
  $badgeVariant = $kindVariants[$kind] ?? 'neutral';
@endphp
<div class="li">
  <div class="l">
    <div class="t{{ ! ($n['read'] ?? true) ? ' unread' : '' }}">{{ $title }}</div>
    @if ($body !== null && $body !== '')
      <div class="meta">{{ $body }}</div>
    @endif
    @if ($reasonLine !== '' && $reasonLine !== $body)
      <div class="meta">{{ $reasonLine }}</div>
    @endif
    <div class="meta">{{ U::dateTime($n['created_at'] ?? null) }}</div>
  </div>
  <div class="r">
    @if (isset($n['decision']))
      {!! U::badge('dec', $n['decision']) !!}
    @else
      <span class="badge b-{{ $badgeVariant }}">{{ $badgeLabel }}</span>
    @endif
    @if (! empty($withAction) && ! ($n['read'] ?? true))
      <button type="button" class="btn btn-secondary" data-post="{{ route('student.x.notifications.read', $n['id']) }}" data-done="Marked as read.">Mark as read</button>
    @endif
  </div>
</div>
