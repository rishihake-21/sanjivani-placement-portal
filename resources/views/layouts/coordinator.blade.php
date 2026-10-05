{{--
  Coordinator portal shell: the same shell as layouts/tpo.blade.php (topbar + collapsible sidebar + scrolling content).
  Sections:  title (string)  ·  nav (active nav id)  ·  content  ·  styles / scripts (stacks)
  Variables: $department  ['id','name','code']  (the coordinator's own department, passed by the page controller)
--}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Coordinator') &middot; TPMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700&display=swap">
  <link rel="stylesheet" href="{{ asset('css/tpms-portal.css') }}">
  <style>
    /* Coordinator additions: built from the portal tokens (flat fills, 4px/8px radii, borders, no shadows).
       Only the side-by-side review drawer needs them. */
    .drawer.review,.drawer.wide{width:min(1200px,100%)}
    .drawer.review .dr-b{padding:0;display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,1fr);gap:0;overflow:hidden}
    .rv-doc{display:flex;flex-direction:column;min-width:0;min-height:0;border-right:1px solid var(--border);background:var(--bg-canvas)}
    .rv-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 16px;border-bottom:1px solid var(--border);background:var(--bg-surface)}
    .rv-view{flex:1;min-height:0;overflow:auto;display:flex;flex-direction:column}
    .rv-view iframe{flex:1;width:100%;min-height:0;border:0;background:#fff}
    .rv-view img{display:block;max-width:100%;height:auto;margin:0 auto}
    .rv-view .empty,.rv-view .notice,.rv-view .skel{margin:16px}
    .rv-side{min-width:0;overflow-y:auto;padding:24px;display:flex;flex-direction:column;gap:16px}
    .rv-reject{display:flex;flex-direction:column;gap:16px;padding:16px;border:1px solid var(--no-fg);border-radius:4px}
    .rv-chg{border-left:3px solid var(--wait-fg)}
    @@container dr (max-width:860px){
      .drawer.review .dr-b{grid-template-columns:minmax(0,1fr);overflow-y:auto}
      .rv-doc{flex:none;height:60vh;border-right:0;border-bottom:1px solid var(--border)}
      .rv-side{overflow:visible}
    }
  </style>
  @stack('styles')
</head>
<body>
@include('partials.icons')

@php
  $active = trim($__env->yieldContent('nav', 'dashboard'));
  $nav = [
    ['dashboard',    'Dashboard',          'dash',  route('coordinator.dashboard')],
    ['queue',        'Verification queue', 'check', route('coordinator.queue')],
    ['students',     'Students',           'user',  route('coordinator.students')],
    ['reuploads',    'Re-upload requests', 'up',    route('coordinator.reuploads')],
    ['drives',       'Drives',             'brief', route('coordinator.drives')],
    ['applications', 'Applications',       'file',  route('coordinator.applications')],
    ['placements',   'Placements',         'award', route('coordinator.placements')],
    ['reports',      'Reports',            'chart', route('coordinator.reports')],
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
      <a class="who" href="{{ route('coordinator.profile') }}" style="text-decoration:none;color:inherit">
        <b>{{ auth()->user()->name }}</b>
        <span>T&amp;P Coordinator @if (! empty($department)) &middot; {{ $department['code'] }} @endif</span>
      </a>
    </div>
  </header>

  <div class="body">
    <nav class="sidebar" aria-label="Coordinator navigation">
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
  <div id="drawer-root"></div>
</div>

<script src="{{ asset('js/tpms-portal.js') }}"></script>
@include('partials.coordinator-helpers')
<script src="{{ asset('js/coordinator.js') }}" defer></script>
@stack('scripts')
</body>
</html>
