{{--
  One drive. Route: coordinator.drives.show
  $drive  GET /api/coordinator/drives/{id} -> data: the list fields plus description, additional_requirements,
          eligibility{min_cgpa, min_tenth_percentage, min_twelfth_percentage, min_diploma_percentage,
          max_active_backlogs, max_total_backlogs} (each nullable), department_branches[{id,code,name}],
          department_applications_by_stage {STAGE: count}
--}}
@extends('layouts.coordinator')
@use('App\Support\StudentUi', 'U')
@section('title', $drive['company'] . ' · ' . $drive['title'])

@section('content')
@php
  $el = $drive['eligibility'] ?? [];
  $limits = [
    ['Minimum CGPA', 'min_cgpa', 'num'],
    ['Minimum 10th percentage', 'min_tenth_percentage', 'pct'],
    ['Minimum 12th percentage', 'min_twelfth_percentage', 'pct'],
    ['Minimum diploma percentage', 'min_diploma_percentage', 'pct'],
    ['Maximum active backlogs', 'max_active_backlogs', ''],
    ['Maximum total backlogs', 'max_total_backlogs', ''],
  ];
  $show = function ($v, $fmt) {
      if ($v === null || $v === '') { return 'Not set'; }

      return $fmt === 'num' ? U::num2($v) : ($fmt === 'pct' ? U::num2($v) . '%' : (string) $v);
  };
  $stages = ['APPLIED', 'SHORTLISTED', 'APTITUDE', 'TECHNICAL', 'HR', 'SELECTED', 'REJECTED', 'OFFER_RECEIVED', 'PLACED', 'WITHDRAWN'];
  $byStage = $drive['department_applications_by_stage'] ?? [];
  $ctc = '–';
  if (($drive['ctc_lpa'] ?? null) !== null) {
      $max = $drive['ctc_max_lpa'] ?? null;
      $ctc = U::num2($drive['ctc_lpa']) . ($max !== null && (float) $max !== (float) $drive['ctc_lpa'] ? ' – ' . U::num2($max) : '') . ' LPA';
  }
@endphp
<div class="page">
  <div class="ph">
    <div>
      <h1>{{ $drive['company'] }}</h1>
      <p class="meta">{{ $drive['title'] }} &middot; Graduation year {{ $drive['graduation_year'] }}</p>
    </div>
    <div class="row wrap">
      @include('coordinator.partials.badge', ['kind' => 'drive', 'value' => $drive['status']])
      <a class="btn btn-secondary" href="{{ route('coordinator.drives') }}">Back to drives</a>
      <a class="btn btn-primary" href="{{ route('coordinator.applications', ['drive_id' => $drive['id']]) }}">View applications</a>
    </div>
  </div>

  <div class="stack">
    <section class="card" aria-labelledby="h-det">
      <div class="card-h"><h2 id="h-det">Details</h2></div>
      <dl class="dl">
        <div><dt>Employment type</dt><dd>{{ U::orDash(ucfirst(strtolower(str_replace('_', ' ', (string) ($drive['employment_type'] ?? ''))))) }}</dd></div>
        <div><dt>CTC</dt><dd>{{ $ctc }}</dd></div>
        <div><dt>Location</dt><dd>{{ U::orDash($drive['location'] ?? null) }}</dd></div>
        <div><dt>Drive date</dt><dd>{{ U::date($drive['drive_date'] ?? null) ?: '–' }}</dd></div>
        <div><dt>Application deadline</dt><dd>{{ U::date($drive['application_deadline'] ?? null) ?: '–' }}</dd></div>
        <div><dt>Applications from your department</dt><dd>{{ $drive['department_applications_count'] ?? 0 }}</dd></div>
      </dl>
      @if (! empty($drive['description']))
        <p class="sect-h">Description</p>
        <p style="white-space:pre-line">{{ $drive['description'] }}</p>
      @endif
      @if (! empty($drive['additional_requirements']))
        <p class="sect-h">Additional requirements</p>
        <p style="white-space:pre-line">{{ $drive['additional_requirements'] }}</p>
      @endif
    </section>

    <div class="grid">
      <div class="c6">
        <section class="card" aria-labelledby="h-el">
          <div class="card-h"><h2 id="h-el">Eligibility</h2></div>
          <table class="cmp">
            <tbody>
              @foreach ($limits as [$label, $key, $fmt])
                <tr><td class="fn">{{ $label }}</td><td>{{ $show($el[$key] ?? null, $fmt) }}</td></tr>
              @endforeach
            </tbody>
          </table>
          <p class="sect-h">Branches from your department</p>
          @if (! empty($drive['department_branches']))
            <div>@foreach ($drive['department_branches'] as $b)<span class="tag">{{ $b['code'] }} &middot; {{ $b['name'] }}</span> @endforeach</div>
          @else
            <p class="empty">None of your branches is eligible.</p>
          @endif
        </section>
      </div>

      <div class="c6">
        <section class="card" aria-labelledby="h-st">
          <div class="card-h"><h2 id="h-st">Applications by stage</h2></div>
          <table class="cmp">
            <thead><tr><th>Stage</th><th>Applications</th></tr></thead>
            <tbody>
              @foreach ($stages as $st)
                <tr><td>@include('coordinator.partials.badge', ['kind' => 'stage', 'value' => $st])</td><td class="num">{{ $byStage[$st] ?? 0 }}</td></tr>
              @endforeach
            </tbody>
          </table>
        </section>
      </div>
    </div>
  </div>
</div>
@endsection
