{{-- System Admin portal shell. Navigation is limited to capabilities implemented for this role. --}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'System Admin') &middot; TPMS</title>
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
  $dashboard = route('admin.dashboard');
  $nav = [
    ['dashboard', 'Dashboard', 'dash', $dashboard],
    ['structure', 'Departments & branches', 'building', $dashboard . '#structure'],
    ['coordinators', 'Coordinator accounts', 'user', $dashboard . '#coordinators'],
    ['master-list', 'Master list import', 'up', $dashboard . '#master-list'],
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
        <span>System Administrator</span>
      </div>
    </div>
  </header>

  <div class="body">
    <nav class="sidebar" aria-label="System administration navigation">
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
