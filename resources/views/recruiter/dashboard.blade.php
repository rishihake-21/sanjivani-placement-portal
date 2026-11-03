@php
  $portal = [
    'key' => 'recruiter',
    'label' => 'Recruiter',
    'role' => 'Recruiter',
    'profile' => null,
    'nav' => [
      ['label' => 'Overview', 'items' => [
        ['overview', 'Dashboard', 'dash', '#overview'],
      ]],
      ['label' => 'Recruitment', 'items' => [
        ['drives', 'Hiring drives', 'brief', '#drives'],
        ['applicants', 'Applicants', 'user', '#applicants'],
      ]],
      ['label' => 'Organization', 'items' => [
        ['company', 'Company profile', 'building', '#company'],
      ]],
    ],
  ];
  $active = 'overview';
@endphp
@extends('layouts.portal')

@section('title', 'Recruiter dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/recruiter-workspace.css') }}">
@endpush

@section('content')
<div class="stack recruiter-page">
  <div id="alert" class="notice no" role="status" hidden></div>

  <section id="overview" class="stack recruiter-section">
    <header class="ph">
      <div>
        <h1>Recruiter dashboard</h1>
        <p class="meta">Hiring activity and applicants for your company</p>
      </div>
      <span class="meta" id="today"></span>
    </header>

    <div class="stat-grid recruiter-stats">
      <article class="card stat"><span class="cap">Visible hiring drives</span><div class="v" id="stat-drives">—</div><p class="meta">Published and closed drives</p></article>
      <article class="card stat"><span class="cap">Total applicants</span><div class="v" id="stat-applicants">—</div><p class="meta">Across your company drives</p></article>
      <article class="card stat"><span class="cap">In selection stages</span><div class="v" id="stat-progress">—</div><p class="meta">Shortlisted through HR</p></article>
      <article class="card stat"><span class="cap">Selected and beyond</span><div class="v" id="stat-selected">—</div><p class="meta">Selected, offered or placed</p></article>
    </div>

    <div class="grid">
      <section class="card c7">
        <div class="card-h"><h2>Hiring pipeline</h2><span class="meta">Applications by stage</span></div>
        <div id="pipeline" class="recruiter-pipeline"><p class="empty">Loading pipeline…</p></div>
        <p class="meta">Counts include applications visible to your company. Withdrawn applications are excluded.</p>
      </section>
      <section class="card c5">
        <div class="card-h"><h2>Company snapshot</h2></div>
        <h2 id="snapshot-company">Loading…</h2>
        <p class="meta" id="snapshot-industry">Company information</p>
        <dl class="dl recruiter-company-facts">
          <div><dt>Website</dt><dd id="snapshot-website">—</dd></div>
          <div><dt>Recruiter email</dt><dd id="snapshot-email">—</dd></div>
          <div><dt>Designation</dt><dd id="snapshot-designation">—</dd></div>
        </dl>
        <a class="linkbtn" href="#company">View company profile</a>
      </section>
    </div>
  </section>

  <section id="drives" class="stack recruiter-section">
    <header class="ph">
      <div><h1>Hiring drives</h1><p class="meta">Drives associated with your company</p></div>
      <span class="meta" id="drive-count">— drives</span>
    </header>
    <div id="drive-grid" class="grid recruiter-drive-grid">
      <div class="card c12 empty">Loading your drives…</div>
    </div>
  </section>

  <section id="applicants" class="stack recruiter-section">
    <header class="ph">
      <div><h1>Applicants</h1><p class="meta">Search and review candidates who applied to your company's drives</p></div>
      <span class="meta" id="applicant-count">Loading…</span>
    </header>
    <section class="card recruiter-applicant-card">
      <div class="filters">
        <div class="field grow"><label for="f-search">Search candidate</label><input id="f-search" type="search" placeholder="Name or university ID"></div>
        <div class="field"><label for="f-drive">Hiring drive</label><select id="f-drive"><option value="">All drives</option></select></div>
        <div class="field"><label for="f-stage">Application stage</label><select id="f-stage"><option value="">All stages</option><option>APPLIED</option><option>SHORTLISTED</option><option>APTITUDE</option><option>TECHNICAL</option><option>HR</option><option>SELECTED</option><option>OFFER_RECEIVED</option><option>PLACED</option><option>REJECTED</option></select></div>
        <button id="f-clear" class="btn btn-secondary" type="button">Clear filters</button>
      </div>
      <div class="filters recruiter-extra-filters">
        <div class="field"><label for="f-branch">Branch ID</label><input id="f-branch" type="number" min="1" placeholder="Optional"></div>
        <div class="field"><label for="f-department">Department ID</label><input id="f-department" type="number" min="1" placeholder="Optional"></div>
        <div class="field"><label for="f-year">Graduation year</label><input id="f-year" type="number" min="2000" max="2100" placeholder="e.g. 2027"></div>
        <div class="field"><label for="f-skill">Skill</label><input id="f-skill" maxlength="50" placeholder="e.g. Java"></div>
        <div class="field"><label for="f-size">Rows per page</label><select id="f-size"><option>10</option><option selected>20</option><option>50</option></select></div>
      </div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Candidate</th><th>Drive</th><th>Department / branch</th><th>Graduation</th><th>Stage</th><th>Applied</th><th></th></tr></thead>
          <tbody id="applicant-rows"><tr><td colspan="7"><div class="skel"></div></td></tr></tbody>
        </table>
      </div>
      <div class="pager">
        <span id="page-summary" class="meta">—</span>
        <div class="row">
          <button id="prev" class="btn btn-secondary" type="button" disabled>Previous</button>
          <span id="page-number" class="meta">Page 1</span>
          <button id="next" class="btn btn-secondary" type="button" disabled>Next</button>
        </div>
      </div>
    </section>
  </section>

  <section id="company" class="stack recruiter-section">
    <header class="ph"><div><h1>Company profile</h1><p class="meta">Read-only information associated with your recruiter account</p></div></header>
    <section class="card">
      <h2 id="profile-company">Loading…</h2>
      <p class="meta" id="profile-industry">—</p>
      <dl class="dl recruiter-profile-facts">
        <div><dt>Recruiter</dt><dd id="profile-name">{{ auth()->user()->name }}</dd></div>
        <div><dt>Email</dt><dd id="profile-email">—</dd></div>
        <div><dt>Phone</dt><dd id="profile-phone">—</dd></div>
        <div><dt>Designation</dt><dd id="profile-designation">—</dd></div>
        <div><dt>Website</dt><dd><a id="profile-website">—</a></dd></div>
      </dl>
    </section>
  </section>
</div>

<div id="drawer-backdrop" class="scrim recruiter-backdrop" hidden></div>
<aside id="drawer" class="drawer recruiter-drawer" aria-hidden="true">
  <header class="dr-h">
    <div><small class="cap">Candidate profile</small><h2 id="detail-name">Applicant</h2><p id="detail-subtitle" class="meta"></p></div>
    <button id="detail-close" class="icon-btn" type="button" aria-label="Close candidate profile">×</button>
  </header>
  <div id="detail-body" class="dr-b recruiter-detail-body">Select an applicant to view verified details.</div>
</aside>
@endsection

@push('scripts')
<script src="{{ asset('js/recruiter-workspace.js') }}" defer></script>
@endpush
