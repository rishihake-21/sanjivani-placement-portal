{{--
  TPO portal shell: topbar + collapsible sidebar + scrolling content, as in the Student Portal design.
  Sections:  title (string)  ·  nav (active nav id)  ·  content  ·  scripts (stack)
--}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'TPO') &middot; TPMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700&display=swap">
  <link rel="stylesheet" href="{{ asset('css/tpms-portal.css') }}">
  @stack('styles')
</head>
<body>
@include('partials.icons')

@php
  $active = trim($__env->yieldContent('nav', 'dashboard'));
  $nav = [
    ['dashboard',    'Dashboard',    'dash',     route('tpo.dashboard')],
    ['companies',    'Companies',    'building', route('tpo.companies')],
    ['drives',       'Drives',       'brief',    route('tpo.drives')],
    ['applications', 'Applications', 'file',     route('tpo.applications')],
    ['placements',   'Placements',   'award',    route('tpo.placements')],
    ['reports',      'Reports',      'chart',    route('tpo.reports')],
  ];
@endphp

<div class="app" id="app">
  <header class="topbar">
    <div class="tb-l">
      <button type="button" class="icon-btn" data-act="toggle-nav" aria-label="Collapse sidebar" aria-expanded="true">
        <svg class="icon" aria-hidden="true"><use href="#i-menu"/></svg>
      </button>
      <span class="wordmark">Sanjivani University &middot; TPMS</span>
    </div>
    <div class="tb-r">
      <div class="who">
        <b>{{ auth()->user()->name }}</b>
        <span>Training &amp; Placement Officer</span>
      </div>
    </div>
  </header>

  <div class="body">
    <nav class="sidebar" aria-label="TPO navigation">
      @foreach ($nav as [$id, $label, $icon, $url])
        <a href="{{ $url }}" class="nav-item{{ $active === $id ? ' active' : '' }}" data-tip="{{ $label }}" @if ($active === $id) aria-current="page" @endif>
          <svg class="icon" aria-hidden="true"><use href="#i-{{ $icon }}"/></svg>
          <span class="nav-label">{{ $label }}</span>
        </a>
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
    <div class="nav-scrim"></div>

    <main class="content" id="content" tabindex="-1">
      <div class="page">
        @yield('content')
      </div>
    </main>
  </div>

  <div id="toast" aria-live="polite" role="status"></div>
</div>

<script src="{{ asset('js/tpms-portal.js') }}"></script>
@stack('scripts')
</body>
</html>
