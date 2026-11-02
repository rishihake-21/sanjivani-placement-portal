{{--
  The one shell for every portal: topbar + grouped sidebar + scrolling content.
  A portal layout (tpo, coordinator, student) only describes itself and extends this:

    $portal = [
      'label'   => 'TPO',                              names the area (page title fallback, nav aria-label)
      'role'    => 'Training & Placement Officer',     shown under the user's name
      'profile' => url|null,                           makes the user chip a link
      'nav'     => [ ['label' => 'Overview'|null, 'items' => [[id, label, icon, url, badge?], ...]], ... ],
    ]
    $active = id of the current nav item

  Sections: title - nav (active id) - content.  Stacks: styles - topbar - portal-scripts (the portal's own JS) - scripts (the page's JS).
--}}
@php
  $brand = config('tpms.brand', 'Sanjivani University · TPMS');
  $user ??= auth()->user();
  $initials = collect(preg_split('/[^\p{L}\p{N}]+/u', (string) $user->name, -1, PREG_SPLIT_NO_EMPTY))
      ->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
  $showRole = strcasecmp(trim((string) $user->name), $portal['role']) !== 0;
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', $portal['label']) &middot; {{ $brand }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700&display=swap">
  <link rel="stylesheet" href="{{ asset('css/tpms-portal.css') }}">
  @stack('styles')
  <script>
    /* Restore the sidebar state before first paint (no flash): icon rail by default at 1024px and below. */
    (function () {
      var c = window.innerWidth <= 1024;
      try { var s = localStorage.getItem('tpms.nav'); if (s) { c = s === 'collapsed' || s === 'c'; } } catch (e) {}
      if (c) { document.documentElement.classList.add('nav-collapsed'); }
    })();
  </script>
</head>
<body>
@include('partials.icons')
<a class="skip" href="#content">Skip to content</a>

<div class="app" id="app">
  <header class="topbar">
    <div class="tb-l">
      <button type="button" class="icon-btn" data-nav-toggle aria-label="Collapse sidebar" aria-expanded="true">
        <svg class="icon" aria-hidden="true"><use href="#i-menu"/></svg>
      </button>
      <span class="wordmark">{{ $brand }}</span>
    </div>
    <div class="tb-r">
      @stack('topbar')
      @if (! empty($portal['profile']))
        <a class="user" href="{{ $portal['profile'] }}">
      @else
        <div class="user">
      @endif
          <span class="avatar" aria-hidden="true">{{ $initials }}</span>
          <span class="who"><b>{{ $user->name }}</b>@if ($showRole)<span>{{ $portal['role'] }}</span>@endif</span>
      @if (! empty($portal['profile']))
        </a>
      @else
        </div>
      @endif
    </div>
  </header>

  <div class="body">
    <nav class="sidebar" aria-label="{{ $portal['label'] }} navigation">
      @foreach ($portal['nav'] as $group)
        @if (! empty($group['label']))<div class="nav-h">{{ $group['label'] }}</div>@endif
        @foreach ($group['items'] as $item)
          @php([$id, $label, $icon, $url] = $item)
          @php($badge = $item[4] ?? null)
          <a href="{{ $url }}" class="nav-item{{ $active === $id ? ' active' : '' }}" data-tip="{{ $label }}" @if ($active === $id) aria-current="page" @endif>
            <svg class="icon" aria-hidden="true"><use href="#i-{{ $icon }}"/></svg>
            <span class="nav-label">{{ $label }}</span>
            @if ($badge)<span class="nav-count" aria-label="{{ $badge }} unread">{{ $badge }}</span>@endif
          </a>
        @endforeach
      @endforeach

      <div class="nav-gap"></div>
      <div class="nav-sep"></div>
      <form method="POST" action="{{ route('logout') }}" class="nav-form">
        @csrf
        <button type="submit" class="nav-item" data-tip="Sign out">
          <svg class="icon" aria-hidden="true"><use href="#i-out"/></svg>
          <span class="nav-label">Sign out</span>
        </button>
      </form>
    </nav>
    <div class="nav-scrim" data-nav-close></div>

    <main class="content" id="content" tabindex="-1">
      <div class="page">
        @yield('content')
      </div>
    </main>
  </div>

  <div id="drawer-root"></div>
  <div id="toast" aria-live="polite" role="status" data-flash="{{ session('status') }}"></div>
</div>

<script src="{{ asset('js/shell.js') }}"></script>
@stack('portal-scripts')
@stack('scripts')
</body>
</html>
