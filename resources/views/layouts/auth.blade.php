<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') &middot; {{ config('tpms.brand', 'Sanjivani University · TPMS') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/student.css') }}">
</head>
<body>
<div class="auth-wrap">
  <main class="card auth-card">
    <span class="wordmark">{{ config('tpms.brand', 'Sanjivani University · TPMS') }}</span>
    @yield('content')
  </main>
</div>
</body>
</html>
