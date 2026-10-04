 /*
 * TPMS portal - shared browser helpers for the Blade views (no build step, no dependencies).
 *
 *  - api()            JSON calls to /api/* with the session cookie + CSRF token (Sanctum stateful auth)
 *  - badges/format    the status badges and number/date formats of the Student Portal design
 *  - drawer/confirm   right-hand drawer (the design's only overlay) used for forms and confirmations
 *  - form helpers     collect() / showErrors() map Laravel 422 responses onto the design's .field.bad + .err
 *  - tables/pager     fillRows() adds the data-l labels that the responsive table layout needs
 *
 * The server stays authoritative: every rule repeated here (stage moves, locked fields) only decides what to
 * OFFER; the API still validates and answers 409/422 with a message that is shown to the user.
 */
(function () {
  'use strict';

  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const icon = (n, c) => `<svg class="icon ${c || ''}" aria-hidden="true"><use href="#i-${n}"/></svg>`;
  const dash = '<span class="meta">&ndash;</span>';
  const num2 = (n) => Number(n).toFixed(2);
  const orDash = (v) => (v === null || v === undefined || v === '' ? dash : v);
  const lpa = (v) => (v === null || v === undefined || v === '' ? dash : `<span class="num">${num2(v)} LPA</span>`);
  const pct = (v) => (v === null || v === undefined ? dash : `<span class="num">${num2(v)}%</span>`);
  const fmtDate = (iso) => (iso ? new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Asia/Kolkata' }) : '');
  const fmtTime = (iso) => new Date(iso).toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Kolkata' });
  const fmtDT = (iso) => (iso ? fmtDate(iso) + ', ' + fmtTime(iso) : '');
  const csrf = () => { const m = $('meta[name="csrf-token"]'); return m ? m.content : ''; };
  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms || 300); }; };

  /** ISO instant -> value for <input type="datetime-local"> in India time (the app timezone is Asia/Kolkata). */
  function toLocalInput(iso) {
    if (!iso) return '';
    const p = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
      .formatToParts(new Date(iso)).reduce((a, x) => { a[x.type] = x.value; return a; }, {});
    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
  }
  /** datetime-local value -> "YYYY-MM-DD HH:mm:ss" (Laravel parses it in the app timezone). */
  const fromLocalInput = (v) => (v ? v.replace('T', ' ') + ':00' : null);

  /* ---------------- badges ---------------- */
  const BADGES = {
    drive: { DRAFT: ['neutral', 'Draft'], PUBLISHED: ['ok', 'Published'], CLOSED: ['neutral', 'Closed'], CANCELLED: ['no', 'Cancelled'] },
    stage: {
      APPLIED: ['neutral', 'Applied'], SHORTLISTED: ['wait', 'Shortlisted'], APTITUDE: ['wait', 'Aptitude'], TECHNICAL: ['wait', 'Technical'], HR: ['wait', 'HR'],
      SELECTED: ['ok', 'Selected'], OFFER_RECEIVED: ['ok', 'Offer received'], PLACED: ['ok', 'Placed'], REJECTED: ['no', 'Rejected'], WITHDRAWN: ['neutral', 'Withdrawn'],
    },
    placement: { OFFERED: ['wait', 'Offered, not verified'], PLACED: ['ok', 'Placed'], DECLINED: ['no', 'Declined'] },
    elig: { ELIGIBLE: ['ok', 'Eligible'], VERIFICATION_PENDING: ['wait', 'Verification pending'], NOT_ELIGIBLE: ['no', 'Not eligible'] },
    check: { PASS: ['ok', 'Pass'], FAIL: ['no', 'Fail'], PENDING: ['wait', 'Not verified'], NA: ['neutral', 'Not applicable'] },
    company: { true: ['ok', 'Active'], false: ['neutral', 'Inactive'] },
  };
  const badge = (kind, value) => {
    const pair = (BADGES[kind] || {})[value] || ['neutral', String(value == null ? '' : value).replace(/_/g, ' ')];
    return `<span class="badge b-${pair[0]}">${esc(pair[1])}</span>`;
  };
  const label = (kind, value) => ((BADGES[kind] || {})[value] || [null, value])[1];
  const EMPLOYMENT = { FULL_TIME: 'Full time', INTERNSHIP: 'Internship', INTERNSHIP_PPO: 'Internship with PPO' };

  /* ---------------- application stages (mirror of App\Support\StageTransitions; the API is authoritative) ---------------- */
  const PIPELINE = ['APPLIED', 'SHORTLISTED', 'APTITUDE', 'TECHNICAL', 'HR', 'SELECTED', 'OFFER_RECEIVED', 'PLACED'];
  const NEXT = {
    APPLIED: ['SHORTLISTED', 'REJECTED'],
    SHORTLISTED: ['APTITUDE', 'TECHNICAL', 'HR', 'SELECTED', 'REJECTED'],
    APTITUDE: ['TECHNICAL', 'HR', 'SELECTED', 'REJECTED'],
    TECHNICAL: ['HR', 'SELECTED', 'REJECTED'],
    HR: ['SELECTED', 'REJECTED'],
    SELECTED: ['REJECTED'],
    OFFER_RECEIVED: [], PLACED: [], REJECTED: [], WITHDRAWN: [],
  };
  const SETTABLE = ['APPLIED', 'SHORTLISTED', 'APTITUDE', 'TECHNICAL', 'HR', 'SELECTED', 'REJECTED'];
  const correctable = (from) => !['OFFER_RECEIVED', 'PLACED', 'WITHDRAWN'].includes(from);

  /* ---------------- api ---------------- */
  class ApiError extends Error {
    constructor(status, body) {
      super((body && body.message) || 'Something went wrong. Please try again.');
      this.status = status;
      this.errors = (body && body.errors) || {};
    }
  }

  async function request(path, opts) {
    opts = opts || {};
    const url = new URL('/api' + path, location.origin);
    Object.entries(opts.query || {}).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) url.searchParams.set(k, v); });
    const headers = { Accept: opts.accept || 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() };
    let body;
    if (opts.body !== undefined) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(opts.body); }
    return fetch(url, { method: opts.method || 'GET', headers, body, credentials: 'same-origin' });
  }

  async function api(path, opts) {
    const res = await request(path, opts);
    if (res.status === 401 || res.status === 419) { location.href = '/login'; throw new ApiError(res.status, { message: 'Your session has expired.' }); }
    if (res.status === 204) return null;
    const isJson = (res.headers.get('content-type') || '').includes('json');
    const data = isJson ? await res.json() : null;
    if (!res.ok) throw new ApiError(res.status, data);
    return data;
  }

  /** Save a CSV (or any file) the API streams. Uses fetch so the session cookie and headers are sent. */
  async function download(path, query, filename) {
    const res = await request(path, { query, accept: 'text/csv' });
    if (!res.ok) { toast('The export could not be created.', 'info'); return; }
    const a = document.createElement('a');
    a.href = URL.createObjectURL(await res.blob());
    a.download = filename;
    document.body.append(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 2000);
  }

  /** One place that turns an API failure into something visible. Returns true when field errors were shown. */
  function fail(err, scope) {
    if (err && err.status === 422 && scope) { showErrors(scope, err.errors); return true; }
    const first = err && err.errors ? Object.values(err.errors).flat()[0] : null;
    toast(first || (err && err.message) || 'Something went wrong.', 'info');
    return false;
  }

  /* ---------------- toast, flash ---------------- */
  let toastTimer;
  function toast(msg, ic) {
    const t = $('#toast');
    if (!t) return;
    t.innerHTML = `<div class="toast">${icon(ic || 'check', 'sm')}<span>${esc(msg)}</span></div>`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.innerHTML = ''; }, 3800);
  }
  const flash = (msg) => sessionStorage.setItem('tpms.flash', msg);

  /* ---------------- small building blocks ---------------- */
  const notice = (kind, ic, html) => `<div class="notice ${kind}">${icon(ic, 'sm')}<div>${html}</div></div>`;
  const loadingCard = () => '<div class="skel" style="width:180px"></div><div class="skel"></div><div class="skel"></div><div class="skel" style="width:70%"></div>';

  /** <div class="field">...</div>; inputs carry data-f so collect() and showErrors() can find them. */
  function fld(id, labelText, inner, o) {
    o = o || {};
    return `<div class="field${o.cls ? ' ' + o.cls : ''}"><label for="${id}">${esc(labelText)}${o.req ? ' <span class="req" aria-hidden="true">*</span>' : ''}</label>${inner}${o.hint ? `<span class="hint">${o.hint}</span>` : ''}<span class="err" role="alert" hidden></span></div>`;
  }
  const input = (name, type, value, attrs) => `<input id="f-${name}" data-f="${name}" type="${type}" value="${esc(value == null ? '' : value)}" ${attrs || ''}>`;
  const textarea = (name, value, attrs) => `<textarea id="f-${name}" data-f="${name}" ${attrs || ''}>${esc(value == null ? '' : value)}</textarea>`;
  const options = (pairs, selected, placeholder) => (placeholder === undefined ? '' : `<option value="">${esc(placeholder)}</option>`)
    + pairs.map(([v, l]) => `<option value="${esc(v)}"${String(v) === String(selected) ? ' selected' : ''}>${esc(l)}</option>`).join('');

  function setPath(obj, path, value) {
    const parts = path.split('.');
    let o = obj;
    parts.forEach((p, i) => { if (i === parts.length - 1) o[p] = value; else o = o[p] = o[p] || {}; });
  }

  /** Read every enabled [data-f] inside root. Empty text/number -> null, checkbox -> boolean, dotted names -> nested objects. */
  function collect(root) {
    const out = {};
    $$('[data-f]', root).forEach((el) => {
      if (el.disabled || el.dataset.skip !== undefined) return;
      let v;
      if (el.type === 'checkbox') v = el.checked;
      else if (el.type === 'number') v = el.value === '' ? null : Number(el.value);
      else { v = (el.value || '').trim(); if (v === '') v = null; }
      setPath(out, el.dataset.f, v);
    });
    return out;
  }

  function clearErrors(root) {
    $$('.field.bad', root).forEach((f) => f.classList.remove('bad'));
    $$('.field .err', root).forEach((e) => { e.hidden = true; e.textContent = ''; });
    const s = $('[data-summary]', root);
    if (s) s.innerHTML = '';
  }

  /** Laravel's {errors:{"eligibility.min_cgpa":["..."]}} -> .field.bad + .err; leftovers go to [data-summary] or a toast. */
  function showErrors(root, errors) {
    clearErrors(root);
    const leftovers = [];
    Object.entries(errors || {}).forEach(([key, msgs]) => {
      const base = key.replace(/\.\d+$/, '');
      const el = root.querySelector(`[data-f="${key}"]`) || root.querySelector(`[data-f="${base}"]`) || root.querySelector(`[data-err="${base}"]`);
      const field = el && el.closest('.field');
      const msg = Array.isArray(msgs) ? msgs[0] : msgs;
      if (field) {
        field.classList.add('bad');
        const e = $('.err', field);
        if (e) { e.textContent = msg; e.hidden = false; }
      } else {
        leftovers.push(...[].concat(msgs));
      }
    });
    const summary = $('[data-summary]', root);
    if (summary && leftovers.length) summary.innerHTML = notice('no', 'info', leftovers.map(esc).join('<br>'));
    else if (leftovers.length) toast(leftovers[0], 'info');
    const bad = $('.field.bad input,.field.bad select,.field.bad textarea', root);
    if (bad) bad.focus({ preventScroll: false });
  }

  /* ---------------- tables ---------------- */
  /**
   * Fill a <tbody>. render(item) returns an array of cells (html string, or {html, cls}).
   * Column names come from the table's data-cols attribute (set by <x-ui.table>) and become data-l labels,
   * which is what the design's stacked mobile layout shows in front of every value.
   */
  function fillRows(tbody, items, render, empty) {
    const table = tbody.closest('table');
    const cols = JSON.parse((table && table.dataset.cols) || '[]');
    if (!items.length) {
      tbody.innerHTML = `<tr><td colspan="${cols.length || 1}"><p class="empty">${empty || 'Nothing to show.'}</p></td></tr>`;
      return;
    }
    tbody.innerHTML = items.map((item) => {
      const cells = render(item);
      return '<tr>' + cells.map((c, i) => {
        const o = typeof c === 'string' ? { html: c } : c;
        const l = cols[i] ? ` data-l="${esc(cols[i])}"` : '';
        return `<td${l}${o.cls ? ` class="${o.cls}"` : ''}>${o.html}</td>`;
      }).join('') + '</tr>';
    }).join('');
  }

  /** Previous / next controls for a Laravel paginator (plain or wrapped in {meta}). */
  function pager(el, p, go) {
    const m = p.meta || p;
    const cur = m.current_page || 1;
    const last = m.last_page || 1;
    el.innerHTML = `<span class="meta num">${m.total ? `${m.from}&ndash;${m.to} of ${m.total}` : 'No results'}</span>
      <div class="row"><button type="button" class="btn btn-secondary" data-p="${cur - 1}"${cur <= 1 ? ' disabled' : ''}>Previous</button>
      <span class="meta num">Page ${cur} of ${last}</span>
      <button type="button" class="btn btn-secondary" data-p="${cur + 1}"${cur >= last ? ' disabled' : ''}>Next</button></div>`;
    el.onclick = (e) => { const b = e.target.closest('[data-p]'); if (b && !b.disabled) go(Number(b.dataset.p)); };
  }

  /* ---------------- query string <-> filters ---------------- */
  const params = () => Object.fromEntries(new URLSearchParams(location.search));
  function syncQuery(obj) {
    const q = new URLSearchParams();
    Object.entries(obj).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) q.set(k, v); });
    history.replaceState(null, '', location.pathname + (q.toString() ? '?' + q : ''));
  }
  /** Put the current query-string values into the filter controls ([data-filter="name"]). */
  function readFilters(root) {
    const p = params();
    $$('[data-filter]', root).forEach((el) => { if (p[el.dataset.filter] !== undefined) el.value = p[el.dataset.filter]; });
    return p;
  }
  function filterValues(root) {
    const o = {};
    $$('[data-filter]', root).forEach((el) => { o[el.dataset.filter] = el.value.trim(); });
    return o;
  }

  /* ---------------- drawer + confirmation ---------------- */
  let drawer = null;

  function trapFocus(e, box) {
    const f = $$('a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled])', box);
    if (!f.length) return;
    const first = f[0];
    const last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  function closeDrawer(silent) {
    if (!drawer) return;
    const d = drawer;
    drawer = null;
    d.scrim.remove();
    d.aside.remove();
    document.removeEventListener('keydown', d.onKey);
    if (!silent && d.prev && d.prev.focus) d.prev.focus({ preventScroll: true });
    if (d.onClose) d.onClose();
  }

  function openDrawer(o) {
    closeDrawer(true);
    const scrim = document.createElement('div');
    scrim.className = 'scrim';
    scrim.dataset.close = '1';
    const aside = document.createElement('aside');
    aside.className = 'drawer';
    aside.setAttribute('role', 'dialog');
    aside.setAttribute('aria-modal', 'true');
    aside.setAttribute('aria-labelledby', 'dr-title');
    aside.innerHTML = `<div class="dr-h"><div><h2 id="dr-title">${esc(o.title)}</h2>${o.meta ? `<p class="meta">${o.meta}</p>` : ''}</div>
      <button type="button" class="icon-btn" data-close aria-label="Close">${icon('x')}</button></div>
      <div class="dr-b" id="dr-body">${o.body || ''}</div><div class="dr-f">${o.footer || ''}</div>`;
    $('#app').append(scrim, aside);

    const onKey = (e) => { if (e.key === 'Escape') closeDrawer(); else if (e.key === 'Tab') trapFocus(e, aside); };
    document.addEventListener('keydown', onKey);
    const onClick = (e) => { if (e.target.closest('[data-close]')) closeDrawer(); };
    scrim.addEventListener('click', onClick);
    aside.addEventListener('click', onClick);

    drawer = { scrim, aside, onKey, prev: document.activeElement, onClose: o.onClose };
    const first = $('input:not([type=hidden]):not([disabled]),select:not([disabled]),textarea:not([disabled])', $('#dr-body')) || $('.btn', aside);
    if (first) first.focus({ preventScroll: true });
    if (o.onMount) o.onMount(aside);
    return aside;
  }

  /**
   * Confirmation drawer. Resolves {reason} on confirm, null when dismissed.
   * o: { title, message, kind, confirmLabel, danger, reason: { label, required, min, hint } }
   */
  function confirmAction(o) {
    return new Promise((resolve) => {
      let done = false;
      const settle = (v) => { if (done) return; done = true; resolve(v); };
      const reason = o.reason
        ? fld('f-reason', o.reason.label, textarea('reason', '', 'maxlength="500"'), { req: o.reason.required, hint: o.reason.hint })
        : '';
      openDrawer({
        title: o.title,
        body: (o.message ? notice(o.kind || 'neutral', 'info', o.message) : '') + reason,
        footer: `<span class="sp"></span><button type="button" class="btn btn-secondary" data-close>Cancel</button>
          <button type="button" class="btn ${o.danger ? 'btn-danger' : 'btn-primary'}" data-ok>${esc(o.confirmLabel || 'Confirm')}</button>`,
        onClose: () => settle(null),
        onMount: (el) => el.addEventListener('click', (e) => {
          if (!e.target.closest('[data-ok]')) return;
          let text = null;
          if (o.reason) {
            text = ($('[data-f="reason"]', el).value || '').trim();
            const min = o.reason.min || (o.reason.required ? 1 : 0);
            if (text.length < min) { showErrors(el, { reason: [`Enter at least ${min} character${min === 1 ? '' : 's'}.`] }); return; }
          }
          settle({ reason: text });
          closeDrawer();
        }),
      });
    });
  }

  /* ---------------- shell ---------------- */
  function initShell() {
    const app = $('#app');
    if (!app) return;
    const mq = window.matchMedia('(max-width:767px)');
    const toggle = $('[data-act="toggle-nav"]');
    const sync = () => {
      app.classList.toggle('is-overlay', mq.matches);
      if (mq.matches) app.classList.remove('collapsed');
      else { app.classList.toggle('collapsed', localStorage.getItem('tpms.nav') === 'c'); app.classList.remove('overlay-open'); }
      if (toggle) {
        toggle.setAttribute('aria-label', mq.matches ? 'Open menu' : app.classList.contains('collapsed') ? 'Expand sidebar' : 'Collapse sidebar');
        toggle.setAttribute('aria-expanded', String(mq.matches ? app.classList.contains('overlay-open') : !app.classList.contains('collapsed')));
      }
    };
    mq.addEventListener('change', sync);
    sync();
    if (toggle) {
      toggle.addEventListener('click', () => {
        if (mq.matches) app.classList.toggle('overlay-open');
        else { app.classList.toggle('collapsed'); localStorage.setItem('tpms.nav', app.classList.contains('collapsed') ? 'c' : 'e'); }
        sync();
      });
    }
    const scrim = $('.nav-scrim');
    if (scrim) scrim.addEventListener('click', () => { app.classList.remove('overlay-open'); sync(); });

    const msg = sessionStorage.getItem('tpms.flash');
    if (msg) { sessionStorage.removeItem('tpms.flash'); toast(msg); }
  }
  document.addEventListener('DOMContentLoaded', initShell);

  window.TPMS = {
    $, $$, esc, icon, dash, num2, orDash, lpa, pct, fmtDate, fmtTime, fmtDT, debounce, toLocalInput, fromLocalInput,
    badge, label, EMPLOYMENT, PIPELINE, NEXT, SETTABLE, correctable,
    api, download, fail, ApiError, toast, flash, notice, loadingCard,
    fld, input, textarea, options, collect, clearErrors, showErrors,
    fillRows, pager, params, syncQuery, readFilters, filterValues,
    openDrawer, closeDrawer, confirmAction,
  };
})();