{{--
  Coordinator portal: its navigation on top of the shared shell (layouts/portal.blade.php).
  Variables: $department  ['id','name','code']  (the coordinator's own department, passed by the page controller)
--}}
@php
  // A page can name its nav item with @section('nav', ...); otherwise it comes from the controller's $active ('coordinator.students' -> 'students').
  $active = trim($__env->yieldContent('nav')) ?: str((string) ($active ?? 'dashboard'))->after('coordinator.')->toString();
  $portal = [
    'label' => 'Coordinator',
    'role' => 'T&P Coordinator' . (! empty($department) ? ' · ' . $department['code'] : ''),
    'profile' => route('coordinator.profile'),
    'nav' => [
      ['label' => 'Overview', 'items' => [
        ['dashboard', 'Dashboard', 'dash', route('coordinator.dashboard')],
      ]],
      ['label' => 'Verification', 'items' => [
        ['queue', 'Verification queue', 'check', route('coordinator.queue')],
        ['reuploads', 'Re-upload requests', 'up', route('coordinator.reuploads')],
        ['students', 'Students', 'user', route('coordinator.students')],
      ]],
      ['label' => 'Placement', 'items' => [
        ['drives', 'Drives', 'brief', route('coordinator.drives')],
        ['applications', 'Applications', 'file', route('coordinator.applications')],
        ['placements', 'Placements', 'award', route('coordinator.placements')],
      ]],
      ['label' => 'Insights', 'items' => [
        ['reports', 'Reports', 'chart', route('coordinator.reports')],
      ]],
    ],
  ];
@endphp
@push('styles')
<link rel="stylesheet" href="{{ asset('css/coordinator.css') }}">
@endpush
@push('portal-scripts')
<script src="{{ asset('js/tpms-portal.js') }}"></script>
@include('partials.coordinator-helpers')
<script src="{{ asset('js/coordinator.js') }}" defer></script>
@endpush
@extends('layouts.portal')
