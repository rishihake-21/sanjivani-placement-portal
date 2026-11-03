<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>TalentDesk · Recruiter workspace</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/recruiter-workspace.css') }}">
</head><body class="rw-body">
<div class="rw-app">
<aside class="rw-sidebar">
<a class="rw-brand" href="#overview"><span class="rw-mark">S<span>+</span></span><span><b>TalentDesk</b><small>RECRUITER WORKSPACE</small></span></a>
<div class="rw-company"><span class="rw-company-icon">▦</span><span><small>COMPANY PORTAL</small><b id="company-name">Loading company…</b></span><i></i></div>
<nav class="rw-nav" aria-label="Recruiter navigation"><small>WORKSPACE</small>
<a class="active" href="#overview" data-nav="overview"><span>◫</span> Overview</a>
<a href="#drives" data-nav="drives"><span>▤</span> Hiring drives <em id="nav-drives">–</em></a>
<a href="#applicants" data-nav="applicants"><span>♙</span> Applicants <em id="nav-applicants">–</em></a>
<small class="rw-account-label">ACCOUNT</small><a href="#company" data-nav="company"><span>▧</span> Company profile</a></nav>
<div class="rw-sidebar-bottom"><div class="rw-help"><b>?</b><span><strong>Need assistance?</strong><small>Contact your T&amp;P coordinator.</small></span></div>
<form method="POST" action="{{ route('logout') }}">@csrf<button class="rw-signout" type="submit">↗ &nbsp; Sign out</button></form><div class="rw-foot-mark">SANJIVANI UNIVERSITY · TPMS</div></div>
</aside>
<main class="rw-main">
<header class="rw-topbar"><div class="rw-crumb">Workspace <span>/</span> <b id="crumb">Overview</b></div><div class="rw-top-right"><span class="rw-secure"><i></i> Secure company access</span><div class="rw-user"><span id="avatar" class="rw-avatar">–</span><span><b id="recruiter-name">{{ auth()->user()->name }}</b><small id="designation">Recruiter</small></span></div></div></header>
<div class="rw-content"><div id="alert" class="rw-alert" role="status" hidden></div>
<section id="overview" class="rw-section">
<div class="rw-welcome"><div><small class="rw-eyebrow">HIRING OVERVIEW <span id="today"></span></small><h1>Good to see you, <span id="first-name">{{ auth()->user()->name }}</span>.</h1><p>Here’s what’s happening across your company’s university hiring drives.</p></div><div class="rw-orbit"><span>T</span></div></div>
<div class="rw-stats">
<article><div><small>Visible hiring drives</small><i class="mint">▤</i></div><strong id="stat-drives">–</strong><p>Published and closed campaigns</p></article>
<article><div><small>Total applicants</small><i class="blue">♙</i></div><strong id="stat-applicants">–</strong><p>Across your company drives</p></article>
<article><div><small>In selection stages</small><i class="amber">◎</i></div><strong id="stat-progress">–</strong><p>Shortlisted through HR</p></article>
<article><div><small>Selected and beyond</small><i class="violet">✦</i></div><strong id="stat-selected">–</strong><p>Selected, offer received or placed</p></article></div>
<div class="rw-overview-grid">
<article class="rw-panel"><div class="rw-panel-heading"><div><small class="rw-eyebrow">CANDIDATE FLOW</small><h2>Hiring pipeline</h2></div><span class="rw-live"><i></i> Live from applications</span></div><div id="pipeline" class="rw-pipeline"><p class="rw-muted">Loading pipeline…</p></div><p class="rw-footnote">Counts reflect applications visible to your company. Withdrawn applications are excluded.</p></article>
<article class="rw-panel"><div class="rw-panel-heading"><div><small class="rw-eyebrow">YOUR ORGANIZATION</small><h2>Company snapshot</h2></div><span class="rw-company-symbol">▦</span></div><h3 id="snapshot-company">Loading…</h3><p id="snapshot-industry" class="rw-muted">Company information</p><dl class="rw-company-facts"><div><dt>Website</dt><dd id="snapshot-website">—</dd></div><div><dt>Recruiter email</dt><dd id="snapshot-email">—</dd></div><div><dt>Designation</dt><dd id="snapshot-designation">—</dd></div></dl><button class="rw-link-button" type="button" data-jump="company">View company profile →</button></article></div></section>
<section id="drives" class="rw-section"><div class="rw-section-heading"><div><small class="rw-eyebrow">YOUR RECRUITMENT</small><h2>Hiring drives</h2><p>Explore the drives associated with your company.</p></div><span class="rw-count" id="drive-count">– drives</span></div><div id="drive-grid" class="rw-drive-grid"><div class="rw-panel rw-loading">Loading your drives…</div></div></section>
<section id="applicants" class="rw-section"><div class="rw-section-heading"><div><small class="rw-eyebrow">TALENT DISCOVERY</small><h2>Applicants</h2><p>Search and review candidates who applied to your company’s drives.</p></div><span class="rw-count" id="applicant-count">Loading…</span></div>
<article class="rw-panel rw-applicant-panel"><div class="rw-filter-main"><label class="rw-search">⌕ <input id="f-search" type="search" placeholder="Search name or university ID…"></label><select id="f-drive"><option value="">All drives</option></select><select id="f-stage"><option value="">All stages</option><option>APPLIED</option><option>SHORTLISTED</option><option>APTITUDE</option><option>TECHNICAL</option><option>HR</option><option>SELECTED</option><option>OFFER_RECEIVED</option><option>PLACED</option><option>REJECTED</option></select><button id="f-clear" type="button">Clear</button></div>
<div class="rw-filter-extra"><label>Branch ID <input id="f-branch" type="number" min="1" placeholder="Optional"></label><label>Department ID <input id="f-department" type="number" min="1" placeholder="Optional"></label><label>Graduation year <input id="f-year" type="number" min="2000" max="2100" placeholder="e.g. 2027"></label><label>Skill <input id="f-skill" maxlength="50" placeholder="e.g. Java"></label><label>Rows <select id="f-size"><option>10</option><option selected>20</option><option>50</option></select></label></div>
<div class="rw-table-wrap"><table class="rw-table"><thead><tr><th>Candidate</th><th>Drive</th><th>Department / branch</th><th>Graduation</th><th>Stage</th><th>Applied</th><th></th></tr></thead><tbody id="applicant-rows"><tr><td colspan="7" class="rw-empty">Loading applicants…</td></tr></tbody></table></div>
<div class="rw-pagination"><span id="page-summary">–</span><div><button id="prev" type="button" disabled>← Previous</button><span id="page-number">Page 1</span><button id="next" type="button" disabled>Next →</button></div></div></article></section>
<section id="company" class="rw-section"><div class="rw-section-heading"><div><small class="rw-eyebrow">ACCOUNT DETAILS</small><h2>Company profile</h2><p>Read-only information associated with your recruiter account.</p></div></div><article class="rw-panel rw-profile"><span class="rw-profile-mark">▦</span><div class="rw-profile-company"><small class="rw-eyebrow">COMPANY</small><h2 id="profile-company">Loading…</h2><p id="profile-industry" class="rw-muted">—</p><a id="profile-website" target="_blank" rel="noopener">—</a></div><div class="rw-profile-rule"></div><dl class="rw-profile-facts"><div><dt>Recruiter</dt><dd id="profile-name">{{ auth()->user()->name }}</dd></div><div><dt>Email</dt><dd id="profile-email">—</dd></div><div><dt>Phone</dt><dd id="profile-phone">—</dd></div><div><dt>Designation</dt><dd id="profile-designation">—</dd></div></dl></article></section>
<footer class="rw-footer"><span>TalentDesk · Recruiter workspace</span><span>Read-only access · Sanjivani University TPMS</span></footer>
</div></main></div>
<div id="drawer-backdrop" class="rw-backdrop" hidden></div>
<aside id="drawer" class="rw-drawer" aria-hidden="true"><header><div><small class="rw-eyebrow">CANDIDATE PROFILE</small><h2 id="detail-name">Applicant</h2><p id="detail-subtitle" class="rw-muted"></p></div><button id="detail-close" aria-label="Close">×</button></header><div id="detail-body" class="rw-drawer-body">Select an applicant to view verified details.</div></aside>
<script src="{{ asset('js/recruiter-workspace.js') }}" defer></script></body></html>