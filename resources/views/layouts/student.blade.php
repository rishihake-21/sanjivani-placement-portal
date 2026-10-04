@use('App\Support\StudentUi', 'U')
@php
  /* StudentPageController supplies $active, $user and $unread for every page.
     The ??= fallbacks keep the chrome safe if a view is rendered another way. */
  $user ??= auth()->user();
  $unread ??= auth()->user()?->notifications()->whereNull('read_at')->count() ?? 0;
  $active ??= \Illuminate\Support\Facades\Route::currentRouteName();
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Student portal') &middot; {{ config('tpms.brand', 'Sanjivani University · TPMS') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/student.css') }}">
  <script>
    /* Restore the sidebar state before first paint: collapsed by default at 1024px and below. */
    (function () {
      var c = window.innerWidth <= 1024;
      try { var s = localStorage.getItem('tpms.nav'); if (s) { c = s === 'collapsed'; } } catch (e) {}
      if (c) { document.documentElement.classList.add('nav-collapsed'); }
    })();
  </script>
</head>
<body>
@include('student.partials.icons')
<div class="app">
  <header class="topbar">
    <div class="tb-l">
      <button type="button" class="icon-btn" data-nav-toggle aria-label="Collapse sidebar" aria-expanded="true">{!! U::icon('menu') !!}</button>
      <span class="wordmark">{{ config('tpms.brand', 'Sanjivani University · TPMS') }}</span>
    </div>
    <div class="tb-r">
      <a class="icon-btn" href="{{ route('student.notifications') }}" aria-label="Notifications, {{ $unread }} unread">
        {!! U::icon('bell') !!}@if ($unread)<span class="count">{{ $unread }}</span>@endif
      </a>
      <div class="who"><b>{{ $user->name }}</b><span>Student</span></div>
    </div>
  </header>

  <div class="body">
    <nav class="sidebar" aria-label="Student navigation">
      @foreach (U::NAV as $routeName => [$label, $ic])
        <a class="nav-item{{ $active === $routeName ? ' active' : '' }}" href="{{ route($routeName) }}" data-tip="{{ $label }}"@if ($active === $routeName) aria-current="page"@endif>
          {!! U::icon($ic) !!}<span class="nav-label">{{ $label }}</span>@if ($routeName === 'student.notifications' && $unread)<span class="nav-count">{{ $unread }}</span>@endif
        </a>
      @endforeach
      <div class="nav-gap"></div>
      <div class="nav-sep"></div>
      <form method="post" action="{{ route('logout') }}" class="nav-form">
        @csrf
        <button type="submit" class="nav-item btn-reset" data-tip="Sign out">{!! U::icon('out') !!}<span class="nav-label">Sign out</span></button>
      </form>
    </nav>
    <div class="nav-scrim" data-nav-close></div>
    <main class="content" id="content" tabindex="-1">
      @yield('content')
    </main>
  </div>

  <div id="drawer-root"></div>
  <div id="toast" aria-live="polite" role="status" data-flash="{{ session('status') }}"></div>
</div>
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
</body>
</html>
