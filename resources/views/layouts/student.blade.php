@use('App\Support\StudentUi', 'U')
{{--
  Student portal: its navigation on top of the shared shell (layouts/portal.blade.php).
  StudentPageController supplies $active, $user and $unread for every page; the ??= fallbacks keep the
  chrome safe if a view is rendered another way.
--}}
@php
  $user ??= auth()->user();
  $unread ??= auth()->user()?->notifications()->whereNull('read_at')->count() ?? 0;
  $active ??= \Illuminate\Support\Facades\Route::currentRouteName();
  $nav = fn (string $route) => [$route, ...U::NAV[$route], route($route)];
  $portal = [
    'label' => 'Student portal',
    'role' => 'Student',
    'profile' => route('student.profile'),
    'nav' => [
      ['label' => 'Overview', 'items' => [
        $nav('student.dashboard'),
        [...$nav('student.notifications'), $unread],
      ]],
      ['label' => 'My profile', 'items' => [
        $nav('student.profile'), $nav('student.academic'), $nav('student.experience'), $nav('student.documents'),
      ]],
      ['label' => 'Placements', 'items' => [
        $nav('student.drives'), $nav('student.applications'),
      ]],
    ],
  ];
@endphp
@push('topbar')
<a class="icon-btn" href="{{ route('student.notifications') }}" aria-label="Notifications, {{ $unread }} unread">
  {!! U::icon('bell') !!}@if ($unread)<span class="count">{{ $unread }}</span>@endif
</a>
@endpush
@push('portal-scripts')
<script>
  window.TPMS = {
    urls: {
      login: @json(route('login')),
      profile: @json(route('student.x.profile.update')),
      academic: @json(route('student.x.academic.store')),
      experience: @json(route('student.x.experience.store')),
      documents: @json(route('student.x.documents.store'))
    }
  };
</script>
<script src="{{ asset('js/student.js') }}" defer></script>
@endpush
@extends('layouts.portal')
