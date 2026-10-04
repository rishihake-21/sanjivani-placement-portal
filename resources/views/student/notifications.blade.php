@extends('layouts.student')
@use('App\Support\StudentUi', 'U')
@section('title', 'Notifications')

@section('content')
<div class="page">
  <div class="ph">
    <div>
      <h1>Notifications</h1>
      <p class="meta"><span class="num">{{ $unread }} unread</span> &middot; latest 50 decisions from your T&amp;P Coordinator</p>
    </div>
    <div class="row wrap">
      <label class="chk"><input type="checkbox" data-nav-param="unread" @checked($unreadOnly)> Unread only</label>
    </div>
  </div>
  <div class="stack">
    <section class="card" aria-label="Notifications">
      @if (count($notifications))
        <div class="list">@foreach ($notifications as $n)@include('student.partials.note-row', ['n' => $n, 'withAction' => true])@endforeach</div>
      @else
        <p class="empty">{{ $hasNotifications ? 'No unread notifications.' : 'No notifications yet.' }}</p>
      @endif
    </section>
  </div>
</div>
@endsection
