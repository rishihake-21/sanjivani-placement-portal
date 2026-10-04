/* =====================================================================
   TPMS student portal: behaviour for the Blade pages.
   Plain JavaScript, no build step. Pages are rendered by Laravel; this file only
     - opens and closes the drawers (cloned from <template id="tpl-...">),
     - sends writes to the module's API controllers (JSON or multipart, session + CSRF),
     - shows validation errors next to the field,
     - runs the sidebar, chip inputs, file picker and toasts.
   Reads window.TPMS.urls (set in layouts/student.blade.php).
   ===================================================================== */
(function () {
  'use strict';

  var URLS = (window.TPMS && window.TPMS.urls) || {};
  var MAX_KB = 5120;

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function csrf() { var m = $('meta[name="csrf-token"]'); return m ? m.content : ''; }
  function iconSvg(name, cls) {
    return '<svg class="icon ' + (cls || '') + '" aria-hidden="true"><use href="#i-' + name + '"/></svg>';
  }

  /* ---------------------------------------------------------------- toast and reload messages */
  var toastTimer;
  function toast(msg, ic) {
    var t = $('#toast');
    if (!t) { return; }
    t.innerHTML = '<div class="toast">' + iconSvg(ic || 'check', 'sm') + '<span></span></div>';
    t.querySelector('span').textContent = msg;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.innerHTML = ''; }, 4200);
  }

  /** Reload the page so every badge and the readiness card are recalculated by the server. */
  function reloadWith(msg, ic) {
    try {
      sessionStorage.setItem('tpms.toast', JSON.stringify({ msg: msg, ic: ic || 'check' }));
      var c = $('#content');
      sessionStorage.setItem('tpms.scroll', JSON.stringify({ url: location.pathname + location.search, y: c ? c.scrollTop : 0 }));
    } catch (e) { /* storage may be blocked; the page still reloads */ }
    location.reload();
  }

  function restoreAfterReload() {
    var t = $('#toast');
    if (t && t.dataset.flash) { toast(t.dataset.flash); }
    try {
      var raw = sessionStorage.getItem('tpms.toast');
      if (raw) { sessionStorage.removeItem('tpms.toast'); var o = JSON.parse(raw); toast(o.msg, o.ic); }
      var sc = sessionStorage.getItem('tpms.scroll');
      if (sc) {
        sessionStorage.removeItem('tpms.scroll');
        var s = JSON.parse(sc), c = $('#content');
        if (c && s.url === location.pathname + location.search && !location.hash) { c.scrollTop = s.y; }
      }
    } catch (e) { /* ignore */ }
  }

  /* ---------------------------------------------------------------- HTTP */
  function api(method, url, body) {
    var headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' };
    var payload;
    if (typeof FormData !== 'undefined' && body instanceof FormData) {
      payload = body;
    } else if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      payload = JSON.stringify(body);
    }
    return fetch(url, { method: method, headers: headers, body: payload, credentials: 'same-origin' }).then(function (res) {
      return res.text().then(function (txt) {
        var data = null;
        try { data = txt ? JSON.parse(txt) : null; } catch (e) { data = null; }
        if (res.ok) { return data; }
        if (res.status === 401 && URLS.login) { location.href = URLS.login; }
        var message = (data && data.message) || 'Something went wrong. Try again.';
        if (res.status === 419) { message = 'Your session expired. Reload the page and sign in again.'; }
        if (res.status === 413) { message = 'The file is larger than the server allows.'; }
        throw { status: res.status, message: message, errors: (data && data.errors) || {} };
      });
    }, function () {
      throw { status: 0, message: 'Could not reach the server. Check your connection and try again.', errors: {} };
    });
  }

  /* ---------------------------------------------------------------- field errors */
  function fieldOf(form, name) { return $('[data-field="' + name + '"]', form); }

  function clearField(field) {
    if (!field) { return; }
    field.classList.remove('bad');
    $$('.err', field).forEach(function (e) { e.remove(); });
    $$('.hint', field).forEach(function (h) { h.hidden = false; });
    $$('input,select,textarea', field).forEach(function (i) { i.removeAttribute('aria-invalid'); });
  }

  function setFieldError(field, msg) {
    clearField(field);
    field.classList.add('bad');
    $$('.hint', field).forEach(function (h) { h.hidden = true; });
    var span = document.createElement('span');
    span.className = 'err';
    span.setAttribute('role', 'alert');
    span.textContent = msg;
    field.appendChild(span);
    $$('input,select,textarea', field).forEach(function (i) { i.setAttribute('aria-invalid', 'true'); });
  }

  function clearErrors(form) { $$('.field.bad', form).forEach(clearField); }

  /** errors: {name: 'message'} or Laravel's {name: ['message']}. Returns true if every error found a field. */
  function showErrors(form, errors, aliases) {
    clearErrors(form);
    var first = null, orphan = null;
    Object.keys(errors).forEach(function (k) {
      var msg = Array.isArray(errors[k]) ? errors[k][0] : errors[k];
      var name = k.replace(/\.\d+$/, '');
      if (aliases && aliases[name]) { name = aliases[name]; }
      var f = fieldOf(form, name);
      if (f) { setFieldError(f, msg); if (!first) { first = f; } } else if (!orphan) { orphan = msg; }
    });
    if (first) {
      var i = $('input:not([disabled]),select:not([disabled]),textarea:not([disabled])', first);
      if (i) { i.focus({ preventScroll: true }); }
      first.scrollIntoView({ block: 'nearest' });
    }
    if (orphan) { toast(orphan, 'info'); }
    return !orphan;
  }

  function busy(form, on) {
    $$('button[type="submit"]', form).forEach(function (b) {
      b.disabled = on;
      if (on) { b.setAttribute('aria-busy', 'true'); } else { b.removeAttribute('aria-busy'); }
    });
  }

  /* ---------------------------------------------------------------- reading forms */
  function tagValues(box) {
    var input = $('input', box), pending = input.value.trim().replace(/,$/, '');
    var vals = $$('.chip', box).map(function (c) { return c.dataset.v; });
    if (pending && !vals.some(function (x) { return x.toLowerCase() === pending.toLowerCase(); })) { vals.push(pending); }
    return vals;
  }

  /**
   * Controls with a name become the request body. Empty text is left out, or sent as null when the
   * control is marked data-nullable (so the server clears the value). Disabled controls are skipped.
   */
  function collect(form, numeric, defaults) {
    var out = {};
    $$('[name]', form).forEach(function (el) {
      if (el.disabled || el.type === 'file') { return; }
      if (el.type === 'checkbox') { out[el.name] = el.checked; return; }
      var v = el.value.trim();
      if (v === '') {
        if (defaults && Object.prototype.hasOwnProperty.call(defaults, el.name)) { out[el.name] = defaults[el.name]; }
        else if (el.hasAttribute('data-nullable')) { out[el.name] = null; }
        return;
      }
      out[el.name] = numeric && numeric.indexOf(el.name) >= 0 ? Number(v) : v;
    });
    $$('[data-tags]', form).forEach(function (box) { out[box.dataset.tags] = tagValues(box); });
    return out;
  }

  function toFormData(obj, file, fileName) {
    var fd = new FormData();
    Object.keys(obj).forEach(function (k) {
      var v = obj[k];
      if (v === null || v === undefined || Array.isArray(v)) { return; }
      fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v));
    });
    if (file) { fd.append(fileName, file); }
    return fd;
  }

  function fileOf(form) { var i = $('input[type="file"]', form); return i && i.files && i.files[0] ? i.files[0] : null; }

  function checkFile(f) {
    if (!/\.(pdf|jpe?g|png)$/i.test(f.name)) { return 'Only PDF, JPG and PNG files are accepted.'; }
    if (f.size > MAX_KB * 1024) { return 'The file is larger than ' + MAX_KB + ' KB.'; }
    return null;
  }
  function fmtSize(b) { return b < 1048576 ? Math.round(b / 1024) + ' KB' : (b / 1048576).toFixed(1) + ' MB'; }
  function todayStr() {
    var d = new Date();
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  }

  /* ---------------------------------------------------------------- client-side checks (the server repeats them) */
  var ACAD_NUM = ['passing_year', 'percentage', 'obtained_marks', 'total_marks', 'sgpa', 'cgpa', 'backlogs_in_term', 'active_backlogs_after_term'];

  function validateAcad(v, level, form) {
    var e = {};
    function num(k) { return v[k] === undefined || v[k] === null ? null : v[k]; }
    function bad(n, lo, hi) { return n === null || isNaN(n) || n < lo || n > hi; }
    if (level !== 'DEGREE_SEM') {
      if (bad(num('percentage'), 0, 100)) { e.percentage = 'Enter a percentage between 0 and 100.'; }
      var yi = $('[name="passing_year"]', form), ymax = yi && yi.max ? Number(yi.max) : new Date().getFullYear() + 1;
      var y = num('passing_year');
      if (y !== null && (Math.floor(y) !== y || y < 1990 || y > ymax)) { e.passing_year = 'Enter a year between 1990 and ' + ymax + '.'; }
      var ob = num('obtained_marks'), tot = num('total_marks');
      if (ob !== null || tot !== null) {
        if (ob === null || ob < 0) { e.obtained_marks = 'Enter the obtained marks.'; }
        if (tot === null || tot <= 0) { e.total_marks = 'Enter total marks greater than 0.'; }
        else if (ob !== null && tot < ob) { e.total_marks = 'Total marks cannot be less than obtained marks.'; }
      }
    } else {
      if (bad(num('sgpa'), 0, 10)) { e.sgpa = 'Enter an SGPA between 0 and 10.'; }
      var cg = num('cgpa');
      if (cg !== null && bad(cg, 0, 10)) { e.cgpa = 'Enter a CGPA between 0 and 10.'; }
      var b1 = num('backlogs_in_term'), b2 = num('active_backlogs_after_term');
      if (b1 !== null && (Math.floor(b1) !== b1 || b1 < 0 || b1 > 20)) { e.backlogs_in_term = 'Enter a whole number from 0 to 20.'; }
      if (b2 !== null && (Math.floor(b2) !== b2 || b2 < 0 || b2 > 40)) { e.active_backlogs_after_term = 'Enter a whole number from 0 to 40.'; }
    }
    return e;
  }

  function validateExp(v) {
    var e = {}, today = todayStr();
    if (!v.organization) { e.organization = 'Enter the organization.'; }
    if (!v.role_title) { e.role_title = 'Enter your role.'; }
    if (!v.start_date) { e.start_date = 'Enter the start date.'; }
    else if (v.start_date > today) { e.start_date = 'The start date cannot be in the future.'; }
    if (!v.is_ongoing) {
      if (!v.end_date) { e.end_date = 'An end date is required unless the entry is ongoing.'; }
      else if (v.start_date && v.end_date < v.start_date) { e.end_date = 'End date cannot be before the start date.'; }
      else if (v.end_date > today) { e.end_date = 'The end date cannot be in the future.'; }
    }
    return e;
  }

  function validateProfile(sec, v) {
    var e = {};
    function url(k) { if (v[k] && !/^https:\/\/[^\s/]+\.[^\s]+$/i.test(v[k])) { e[k] = 'Enter a link that starts with https://'; } }
    if (sec === 'identity') {
      if (v.date_of_birth && !(v.date_of_birth < todayStr() && v.date_of_birth > '1960-01-01')) { e.date_of_birth = 'Enter a date after 1 Jan 1960 and before today.'; }
    } else if (sec === 'contact') {
      if (!v.personal_email) { e.personal_email = 'Enter your personal email.'; }
      else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.personal_email)) { e.personal_email = 'Enter a valid email address.'; }
      if (!v.phone) { e.phone = 'Enter your mobile number.'; }
      else if (!/^(\+91)?[6-9][0-9]{9}$/.test(v.phone)) { e.phone = 'Enter a 10-digit mobile number starting with 6 to 9.'; }
      if (!v.current_city) { e.current_city = 'Enter your current city.'; }
      if (!v.permanent_city) { e.permanent_city = 'Enter your permanent city.'; }
    } else if (sec === 'skills') {
      url('github_url'); url('linkedin_url'); url('portfolio_url');
    } else if (sec === 'preferences') {
      if (v.opted_out_of_placement && !v.opt_out_reason) { e.opt_out_reason = 'Give a reason for opting out.'; }
    }
    return e;
  }

  /* ---------------------------------------------------------------- form handlers */
  var HANDLERS = {};

  function failed(form, err, changed, aliases) {
    busy(form, false);
    if (err.status === 422 && err.errors && Object.keys(err.errors).length) {
      if (changed) { reloadWith('Saved, but the next step failed: ' + err.message, 'info'); return; }
      showErrors(form, err.errors, aliases);
      return;
    }
    if (changed) { reloadWith('Saved, but the next step failed: ' + err.message, 'info'); return; }
    toast(err.message, 'info');
  }

  /* Profile sections: PATCH /student/x/profile */
  HANDLERS.profile = function (form) {
    clearErrors(form);
    var section = form.closest('section'), sec = section ? section.id.replace('sec-', '') : '';
    var body = collect(form, []);
    if (sec === 'identity' && !body.confirm_identity) { delete body.confirm_identity; }
    var errs = validateProfile(sec, body);
    if (Object.keys(errs).length) { showErrors(form, errs); return; }
    busy(form, true);
    api('PATCH', URLS.profile, body).then(function () { reloadWith('Profile saved.'); }, function (err) { failed(form, err, false); });
  };

  /* Academic record drawer: add, edit (draft or rejected) or revise (verified) */
  HANDLERS.acad = function (form, intent) {
    clearErrors(form);
    var mode = form.dataset.mode, isAdd = mode === 'add', level, sem = null;
    if (isAdd) {
      var parts = $('[data-key-select]', form).value.split('|');
      level = parts[0]; sem = parts[1] === '' ? null : Number(parts[1]);
    } else { level = form.dataset.level; }
    var defaults = level === 'DEGREE_SEM' ? { backlogs_in_term: 0, active_backlogs_after_term: 0 } : null;
    var vals = collect(form, ACAD_NUM, defaults);
    var file = fileOf(form), submit = intent === '1';
    var errs = validateAcad(vals, level, form);
    if (submit && !file && form.dataset.haveDoc !== '1') { errs.document = 'Upload a supporting marksheet before submitting.'; }
    if (Object.keys(errs).length) { showErrors(form, errs); return; }

    var LEVEL_NAME = { TENTH: '10th', TWELFTH: '12th', DIPLOMA: 'Diploma' };
    if (!isAdd && level === 'DEGREE_SEM') { sem = Number(form.dataset.semester); }
    var label = level === 'DEGREE_SEM' ? 'Semester ' + sem : LEVEL_NAME[level];
    busy(form, true);
    var changed = false, id;
    var base = URLS.academic;
    var first;
    if (isAdd) {
      vals.level = level;
      if (sem !== null) { vals.semester = sem; }
      first = api('POST', form.dataset.store, toFormData(vals, file, 'document')).then(function (r) { changed = true; id = r.data.id; });
    } else {
      first = api('PATCH', form.dataset.update, vals).then(function (r) {
        changed = true; id = r.data.id;
        if (file) { return api('POST', base + '/' + id + '/document', toFormData({}, file, 'document')); }
      });
    }
    first.then(function () {
      if (submit) { return api('POST', base + '/' + id + '/submit'); }
    }).then(function () {
      reloadWith(submit ? label + ' submitted for review.' : mode === 'revise' ? 'Revision of ' + label + ' saved as a draft.' : 'Draft saved.');
    }, function (err) { failed(form, err, changed, { level: 'key', semester: 'key' }); });
  };

  /* Experience drawer */
  HANDLERS.exp = function (form, intent) {
    clearErrors(form);
    var mode = form.dataset.mode, isAdd = mode === 'add';
    var vals = collect(form, []);
    var file = fileOf(form);
    var errs = validateExp(vals);
    if (Object.keys(errs).length) { showErrors(form, errs); return; }
    var wantSubmit = intent === '1';
    busy(form, true);
    var changed = false, id, last;
    var base = URLS.experience;
    var first;
    if (isAdd) {
      first = api('POST', form.dataset.store, toFormData(vals, file, 'certificate')).then(function (r) { changed = true; last = r.data; });
    } else {
      first = api('PATCH', form.dataset.update, vals).then(function (r) {
        changed = true; last = r.data; id = r.data.id;
        if (file) { return api('POST', base + '/' + id + '/certificate', toFormData({}, file, 'certificate')).then(function (r2) { last = r2.data; }); }
        if (wantSubmit) { return api('POST', base + '/' + id + '/submit').then(function (r2) { last = r2.data; }); }
      });
    }
    first.then(function () {
      var st = last && last.status;
      reloadWith(st === 'PENDING' ? 'Experience submitted for review.' : st === 'SELF_DECLARED' ? 'Saved as self-declared (unverified).' : 'Saved.');
    }, function (err) { failed(form, err, changed); });
  };

  /* Upload on the Documents page */
  HANDLERS.upload = function (form) {
    clearErrors(form);
    var file = fileOf(form);
    if (!file) { showErrors(form, { document: 'Choose a file to upload.' }); return; }
    var sel = $('[name="document_type"]', form);
    var fd = toFormData({ document_type: sel.value }, file, 'document');
    var label = sel.options[sel.selectedIndex].text;
    busy(form, true);
    api('POST', form.dataset.store, fd).then(function () {
      reloadWith(label + ' uploaded. It is in review.');
    }, function (err) { failed(form, err, false); });
  };

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.matches || !form.matches('form[data-ajax]')) { return; }
    e.preventDefault();
    var intent = (e.submitter && e.submitter.dataset && e.submitter.dataset.intent) || '0';
    var h = HANDLERS[form.dataset.ajax];
    if (h) { h(form, intent); }
  });

  /* ---------------------------------------------------------------- drawers */
  var drawerTrigger = null;

  function setKeyGroup(form) {
    var sel = $('[data-key-select]', form);
    if (!sel) { return; }
    var isSem = sel.value.split('|')[0] === 'DEGREE_SEM';
    $$('[data-group]', form).forEach(function (g) {
      var show = g.dataset.group === 'sem' ? isSem : !isSem;
      g.hidden = !show;
      $$('input,select,textarea', g).forEach(function (i) { i.disabled = !show; });
    });
  }

  function openDrawer(id, opts) {
    var tpl = document.getElementById('tpl-' + id), rootEl = $('#drawer-root');
    if (!tpl || !rootEl) { return; }
    drawerTrigger = opts.trigger || document.activeElement;
    rootEl.replaceChildren(tpl.content.cloneNode(true));
    var form = $('form', rootEl);
    if (form) {
      var sel = $('[data-key-select]', form);
      if (sel && opts.key) {
        var has = $$('option', sel).some(function (o) { return o.value === opts.key; });
        if (has) { sel.value = opts.key; }
      }
      setKeyGroup(form);
    }
    var target = opts.focus === 'file' ? $('#dr-file', rootEl) : $('.dr-b input:not([disabled]),.dr-b select:not([disabled]),.dr-b textarea:not([disabled])', rootEl);
    if (!target) { target = $('[data-drawer-close]', rootEl); }
    if (target) { target.focus({ preventScroll: true }); }
  }

  function closeDrawer() {
    var rootEl = $('#drawer-root');
    if (!rootEl || !rootEl.firstChild) { return; }
    rootEl.replaceChildren();
    if (drawerTrigger && document.contains(drawerTrigger)) { drawerTrigger.focus({ preventScroll: true }); }
    drawerTrigger = null;
  }
  function drawerOpen() { var r = $('#drawer-root'); return !!(r && r.firstChild); }

  /* ---------------------------------------------------------------- sidebar */
  function navOverlay() { return window.innerWidth < 768; }
  function syncNavToggle() {
    var b = $('[data-nav-toggle]');
    if (!b) { return; }
    var root = document.documentElement;
    if (navOverlay()) {
      b.setAttribute('aria-label', 'Open menu');
      b.setAttribute('aria-expanded', String(root.classList.contains('nav-open')));
    } else {
      var collapsed = root.classList.contains('nav-collapsed');
      b.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
      b.setAttribute('aria-expanded', String(!collapsed));
    }
  }
  function toggleNav() {
    var root = document.documentElement;
    if (navOverlay()) { root.classList.toggle('nav-open'); }
    else {
      var c = root.classList.toggle('nav-collapsed');
      try { localStorage.setItem('tpms.nav', c ? 'collapsed' : 'expanded'); } catch (e) { /* ignore */ }
    }
    syncNavToggle();
  }
  function closeOverlay() { document.documentElement.classList.remove('nav-open'); syncNavToggle(); }

  /* ---------------------------------------------------------------- chips */
  function chipMsg(box, msg) {
    var f = box.closest('.field'), m = $('.err.tmp', f);
    if (!msg) { if (m) { m.remove(); } return; }
    if (!m) { m = document.createElement('span'); m.className = 'err tmp'; m.setAttribute('role', 'alert'); f.appendChild(m); }
    m.textContent = msg;
  }
  function addChip(box) {
    var input = $('input', box), text = input.value.trim().replace(/,$/, '');
    if (!text) { input.value = ''; return; }
    var max = Number(box.dataset.max), len = Number(box.dataset.len);
    var have = $$('.chip', box).map(function (c) { return c.dataset.v; });
    if (text.length > len) { chipMsg(box, 'Each entry can be up to ' + len + ' characters.'); return; }
    if (have.some(function (x) { return x.toLowerCase() === text.toLowerCase(); })) { chipMsg(box, 'Already added.'); return; }
    if (have.length >= max) { chipMsg(box, 'Up to ' + max + ' entries.'); return; }
    chipMsg(box, null);
    var chip = document.createElement('span');
    chip.className = 'chip';
    chip.dataset.v = text;
    chip.appendChild(document.createTextNode(text));
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('data-chip-remove', '');
    btn.setAttribute('aria-label', 'Remove ' + text);
    btn.innerHTML = iconSvg('x', 'sm');
    chip.appendChild(btn);
    input.parentNode.insertBefore(chip, input);
    input.value = '';
  }

  /* ---------------------------------------------------------------- delegated events */
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t.closest) { return; }

    var el = t.closest('[data-nav-toggle]');
    if (el) { toggleNav(); return; }
    if (t.closest('[data-nav-close]')) { closeOverlay(); return; }

    el = t.closest('[data-open]');
    if (el && !el.disabled) { openDrawer(el.dataset.open, { key: el.dataset.key, focus: el.dataset.focus, trigger: el }); return; }
    if (t.closest('[data-drawer-close]')) { closeDrawer(); return; }

    el = t.closest('[data-chip-remove]');
    if (el && !el.disabled) {
      var box = el.closest('[data-tags]');
      el.closest('.chip').remove();
      chipMsg(box, null);
      $('input', box).focus();
      return;
    }

    el = t.closest('[data-post]');
    if (el && !el.disabled) {
      el.disabled = true;
      api('POST', el.dataset.post).then(function () { reloadWith(el.dataset.done || 'Done.'); }, function (err) { el.disabled = false; toast(err.message, 'info'); });
      return;
    }

    el = t.closest('[data-delete]');
    if (el && !el.disabled) {
      el.disabled = true;
      api('DELETE', el.dataset.delete).then(function () { reloadWith(el.dataset.done || 'Deleted.'); }, function (err) { el.disabled = false; toast(err.message, 'info'); });
    }
  });

  document.addEventListener('change', function (e) {
    var el = e.target;
    if (!el || !el.matches) { return; }

    if (el.matches('[data-file]')) {
      var field = el.closest('[data-field]'), box = el.closest('.file');
      var f = el.files && el.files[0];
      clearField(field);
      var nm = $('[data-file-name]', box), lb = $('[data-file-label]', box);
      if (!f) { nm.textContent = 'No file chosen'; lb.textContent = 'Choose file'; }
      else {
        var err = checkFile(f);
        if (err) { el.value = ''; nm.textContent = 'No file chosen'; lb.textContent = 'Choose file'; if (field) { setFieldError(field, err); } }
        else { nm.textContent = f.name + ' · ' + fmtSize(f.size); lb.textContent = 'Replace file'; }
      }
      var form = el.closest('form[data-ajax="exp"]');
      if (form && form.dataset.pendingCert !== '1') {
        var main = $('[data-save-main]', form), has = !!(el.files && el.files[0]);
        if (main) { main.textContent = has ? 'Save and submit' : 'Save'; main.dataset.intent = has ? '1' : '0'; }
      }
      return;
    }
    if (el.matches('[data-nav-param]')) {
      var u = new URL(location.href);
      if (el.checked) { u.searchParams.set(el.dataset.navParam, '1'); } else { u.searchParams.delete(el.dataset.navParam); }
      location.href = u.toString();
      return;
    }
    if (el.matches('[data-upload-type]')) {
      var note = $('[data-resume-note]');
      if (note) { note.hidden = el.value !== 'RESUME'; }
      return;
    }
    if (el.matches('[data-key-select]')) {
      var kf = el.closest('form');
      clearErrors(kf);
      setKeyGroup(kf);
      return;
    }
    if (el.matches('[data-ongoing]')) {
      var ef = el.closest('form'), end = $('[name="end_date"]', ef), star = $('[data-end-req]', ef);
      end.disabled = el.checked;
      if (el.checked) { end.value = ''; clearField(end.closest('[data-field]')); }
      if (star) { star.hidden = el.checked; }
      return;
    }
    if (el.matches('[data-optout]')) {
      var pf = el.closest('form'), reason = $('[name="opt_out_reason"]', pf), rs = $('[data-reason-req]', pf);
      reason.disabled = !el.checked;
      if (!el.checked) { reason.value = ''; clearField(reason.closest('[data-field]')); }
      if (rs) { rs.hidden = !el.checked; }
      if (el.checked) { reason.focus({ preventScroll: true }); }
    }
  });

  document.addEventListener('input', function (e) {
    var f = e.target.closest && e.target.closest('.field.bad');
    if (f && !e.target.matches('[data-file]')) { clearField(f); }
  });

  document.addEventListener('keydown', function (e) {
    var input = e.target.closest && e.target.closest('.tags input');
    if (input) {
      var box = input.closest('[data-tags]');
      if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addChip(box); return; }
      if (e.key === 'Backspace' && !input.value) { var cs = $$('.chip', box); if (cs.length) { cs[cs.length - 1].remove(); } }
    }
    if (e.key === 'Escape') {
      if (drawerOpen()) { closeDrawer(); } else { closeOverlay(); }
      return;
    }
    if (e.key === 'Tab' && drawerOpen()) {
      var items = $$('#drawer-root button:not([disabled]),#drawer-root input:not([disabled]),#drawer-root select:not([disabled]),#drawer-root textarea:not([disabled]),#drawer-root [href]');
      if (!items.length) { return; }
      var firstEl = items[0], lastEl = items[items.length - 1];
      if (e.shiftKey && document.activeElement === firstEl) { e.preventDefault(); lastEl.focus(); }
      else if (!e.shiftKey && document.activeElement === lastEl) { e.preventDefault(); firstEl.focus(); }
    }
  });

  document.addEventListener('focusout', function (e) {
    var input = e.target.closest && e.target.closest('.tags input');
    if (input && input.value.trim()) { addChip(input.closest('[data-tags]')); }
  });

  window.addEventListener('resize', function () {
    if (!navOverlay()) { document.documentElement.classList.remove('nav-open'); }
    syncNavToggle();
  });

  /* ---------------------------------------------------------------- boot */
  function boot() {
    syncNavToggle();
    restoreAfterReload();
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();