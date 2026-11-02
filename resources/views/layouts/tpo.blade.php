{{-- TPO portal: its navigation on top of the shared shell (layouts/portal.blade.php). --}}
@php
  $active = trim($__env->yieldContent('nav', 'dashboard'));
  $portal = [
    'label' => 'TPO',
    'role' => 'Training & Placement Officer',
    'profile' => null,
    'nav' => [
      ['label' => 'Overview', 'items' => [
        ['dashboard', 'Dashboard', 'dash', route('tpo.dashboard')],
      ]],
      ['label' => 'Recruitment', 'items' => [
        ['companies', 'Companies', 'building', route('tpo.companies')],
        ['drives', 'Drives', 'brief', route('tpo.drives')],
        ['applications', 'Applications', 'file', route('tpo.applications')],
        ['placements', 'Placements', 'award', route('tpo.placements')],
      ]],
      ['label' => 'Insights', 'items' => [
        ['reports', 'Reports', 'chart', route('tpo.reports')],
      ]],
    ],
  ];
@endphp
@push('portal-scripts')
<script src="{{ asset('js/tpms-portal.js') }}"></script>
@endpush
@extends('layouts.portal')
