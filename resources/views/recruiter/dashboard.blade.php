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
@section('content')
<div class="rw-content stack"><div id="alert" class="rw-alert" role="status" hidden></div>
<section id="overview" class="rw-section">
<div class="rw-welcome card"><div><small class="rw-eyebrow">HIRING OVERVIEW <span id="today"></span></small><h1>Good to see you, <span id="first-name">{{ auth()->user()->name }}</span>.</h1><p>Here’s what’s happening across your company’s university hiring drives.</p></div><div class="rw-orbit"><span>T</span></div></div>
<div class="rw-stats stat-grid">
<article><div><small>Visible hiring drives</small><i class="mint">▤</i></div><strong id="stat-drives">–</strong><p>Published and closed campaigns</p></article>
<article><div><small>Total applicants</small><i class="blue">♙</i></div><strong id="stat-applicants">–</strong><p>Across your company drives</p></article>
<article><div><small>In selection stages</small><i class="amber">◎</i></div><strong id="stat-progress">–</strong><p>Shortlisted through HR</p></article>
<article><div><small>Selected and beyond</small><i class="violet">✦</i></div><strong id="stat-selected">–</strong><p>Selected, offer received or placed</p></article></div>
<div class="rw-overview-grid">
<article class="rw-panel card"><div class="rw-panel-heading"><div><small class="rw-eyebrow">CANDIDATE FLOW</small><h2>Hiring pipeline</h2></div><span class="rw-live"><i></i> Live from applications</span></div><div id="pipeline" class="rw-pipeline"><p class="rw-muted">Loading pipeline…</p></div><p class="rw-footnote">Counts reflect applications visible to your company. Withdrawn applications are excluded.</p></article>
<article class="rw-panel"><div class="rw-panel-heading"><div><small class="rw-eyebrow">YOUR ORGANIZATION</small><h2>Company snapshot</h2></div><span class="rw-company-symbol">▦</span></div><h3 id="snapshot-company">Loading…</h3><p id="snapshot-industry" class="rw-muted">Company information</p><dl class="rw-company-facts"><div><dt>Website</dt><dd id="snapshot-website">—</dd></div><div><dt>Recruiter email</dt><dd id="snapshot-email">—</dd></div><div><dt>Designation</dt><dd id="snapshot-designation">—</dd></div></dl><button class="rw-link-button" type="button" data-jump="company">View company profile →</button></article></div></section>
<section id="drives" class="rw-section"><div class="rw-section-heading"><div><small class="rw-eyebrow">YOUR RECRUITMENT</small><h2>Hiring drives</h2><p>Explore the drives associated with your company.</p></div><span class="rw-count" id="drive-count">– drives</span></div><div id="drive-grid" class="rw-drive-grid"><div class="rw-panel rw-loading">Loading your drives…</div></div></section>
<section id="applicants" class="rw-section"><div class="rw-section-heading"><div><small class="rw-eyebrow">TALENT DISCOVERY</small><h2>Applicants</h2><p>Search and review candidates who applied to your company’s drives.</p></div><span class="rw-count" id="applicant-count">Loading…</span></div>
<article class="rw-panel card rw-applicant-panel"><div class="rw-filter-main"><label class="rw-search">⌕ <input id="f-search" type="search" placeholder="Search name or university ID…"></label><select id="f-drive"><option value="">All drives</option></select><select id="f-stage"><option value="">All stages</option><option>APPLIED</option><option>SHORTLISTED</option><option>APTITUDE</option><option>TECHNICAL</option><option>HR</option><option>SELECTED</option><option>OFFER_RECEIVED</option><option>PLACED</option><option>REJECTED</option></select><button id="f-clear" type="button">Clear</button></div>
<div class="rw-filter-extra"><label>Branch ID <input id="f-branch" type="number" min="1" placeholder="Optional"></label><label>Department ID <input id="f-department" type="number" min="1" placeholder="Optional"></label><label>Graduation year <input id="f-year" type="number" min="2000" max="2100" placeholder="e.g. 2027"></label><label>Skill <input id="f-skill" maxlength="50" placeholder="e.g. Java"></label><label>Rows <select id="f-size"><option>10</option><option selected>20</option><option>50</option></select></label></div>
<div class="rw-table-wrap"><table class="rw-table tbl"><thead><tr><th>Candidate</th><th>Drive</th><th>Department / branch</th><th>Graduation</th><th>Stage</th><th>Applied</th><th></th></tr></thead><tbody id="applicant-rows"><tr><td colspan="7" class="rw-empty">Loading applicants…</td></tr></tbody></table></div>
<div class="rw-pagination"><span id="page-summary">–</span><div><button id="prev" type="button" disabled>← Previous</button><span id="page-number">Page 1</span><button id="next" type="button" disabled>Next →</button></div></div></article></section>
<section id="company" class="rw-section"><div class="rw-section-heading"><div><small class="rw-eyebrow">ACCOUNT DETAILS</small><h2>Company profile</h2><p>Read-only information associated with your recruiter account.</p></div></div><article class="rw-panel rw-profile"><span class="rw-profile-mark">▦</span><div class="rw-profile-company"><small class="rw-eyebrow">COMPANY</small><h2 id="profile-company">Loading…</h2><p id="profile-industry" class="rw-muted">—</p><a id="profile-website" target="_blank" rel="noopener">—</a></div><div class="rw-profile-rule"></div><dl class="rw-profile-facts"><div><dt>Recruiter</dt><dd id="profile-name">{{ auth()->user()->name }}</dd></div><div><dt>Email</dt><dd id="profile-email">—</dd></div><div><dt>Phone</dt><dd id="profile-phone">—</dd></div><div><dt>Designation</dt><dd id="profile-designation">—</dd></div></dl></article></section>
<footer class="rw-footer"><span>TalentDesk · Recruiter workspace</span><span>Read-only access · Sanjivani University TPMS</span></footer>
</div>
<div id="drawer-backdrop" class="rw-backdrop scrim" hidden></div>
<aside id="drawer" class="rw-drawer drawer" aria-hidden="true">
  <header class="dr-h">
    <div><small class="cap">Candidate profile</small><h2 id="detail-name">Applicant</h2><p id="detail-subtitle" class="meta"></p></div>
    <button id="detail-close" class="icon-btn" type="button" aria-label="Close">×</button>
  </header>
  <div id="detail-body" class="rw-drawer-body dr-b">Select an applicant to view verified details.</div>
</aside>
@endsection
@push('scripts')
<script src="{{ asset('js/recruiter-workspace.js') }}" defer></script>
@endpush
