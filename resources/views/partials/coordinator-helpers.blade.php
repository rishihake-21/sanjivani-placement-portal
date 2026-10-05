{{--
  Coordinator helpers on top of public/js/tpms-portal.js (that file is used as it is). Adds window.TPMS.C:
  record / document / request badges and labels, and three drawers built with TPMS.openDrawer:
    reviewDrawer   file and submitted details SIDE BY SIDE, with Approve / Reject (reject reason inline)
    unlockDrawer   unlock a verified, locked academic record (reason, audited)
    reuploadDrawer ask the student to upload an accepted item again
  Optional variable: $rejectionReasons  [code => label]  (App\Enums\RejectionReason). The labels below are used when it is missing.
--}}
@php
  $__reasons = $rejectionReasons ?? [
      'UNREADABLE' => 'Document is not readable',
      'WRONG_DOCUMENT' => 'Document does not match the selected type',
      'DATA_MISMATCH' => 'Entered values do not match the document',
      'INCOMPLETE' => 'Document is incomplete (missing pages or details)',
      'INVALID_OR_EXPIRED' => 'Document is invalid or expired',
      'OTHER' => 'Other',
  ];
@endphp
<script>
(function () {
  'use strict';
  const T = window.TPMS;
  const { $, api, esc, icon, dash, num2, fmtDate, notice, fld, textarea, options, openDrawer, closeDrawer, clearErrors, showErrors, fail, toast } = T;
  const REASONS = @json($__reasons);

  /* ---------------- badges and labels ---------------- */
  const BADGES = {
    rec: { VERIFIED: ['ok', 'Verified'], PENDING: ['wait', 'In review'], REJECTED: ['no', 'Rejected'], DRAFT: ['neutral', 'Draft'], SELF_DECLARED: ['neutral', 'Self-declared (unverified)'], SUPERSEDED: ['neutral', 'Superseded'] },
    doc: { APPROVED: ['ok', 'Approved'], PENDING: ['wait', 'In review'], REJECTED: ['no', 'Rejected'], SUPERSEDED: ['neutral', 'Superseded'] },
    sec: { COMPLETE: ['ok', 'Complete'], IN_REVIEW: ['wait', 'In review'], ACTION_REQUIRED: ['no', 'Action required'], INCOMPLETE: ['neutral', 'Incomplete'], OPTIONAL_EMPTY: ['neutral', 'Optional, not added'] },
    req: { OPEN: ['wait', 'Open'], FULFILLED: ['ok', 'Fulfilled'], CANCELLED: ['neutral', 'Cancelled'] },
  };
  const badge = (kind, v) => {
    const p = (BADGES[kind] || {})[v];
    return p ? `<span class="badge b-${p[0]}">${esc(p[1])}</span>` : T.badge(kind, v);
  };
  const DOC_TYPE = { RESUME: 'Resume', MARKSHEET_10TH: '10th marksheet', MARKSHEET_12TH: '12th marksheet', MARKSHEET_DIPLOMA: 'Diploma marksheet', MARKSHEET_SEMESTER: 'Semester marksheet', CERT_INTERNSHIP: 'Internship certificate', CERT_EXPERIENCE: 'Experience certificate', CERT_OTHER: 'Other certificate' };
  const EXP_TYPE = { INTERNSHIP: 'Internship', JOB: 'Job', PROJECT_WORK: 'Project work', TRAINING: 'Training' };
  const ADMISSION = { REGULAR: 'Regular', LATERAL: 'Lateral' };
  const GENDER = { MALE: 'Male', FEMALE: 'Female', OTHER: 'Other', PREFER_NOT_TO_SAY: 'Prefer not to say' };
  const SECTIONS = [['A_identity', 'A', 'Identity'], ['B_contact', 'B', 'Contact'], ['C_academic', 'C', 'Academic'], ['D_skills_links', 'D', 'Skills and links'], ['E_experience', 'E', 'Experience'], ['F_preferences', 'F', 'Preferences'], ['G_resume', 'G', 'Resume']];
  const STANDALONE = ['RESUME', 'CERT_OTHER']; // marksheets and certificates are reviewed together with their record
  const docType = (t) => DOC_TYPE[t] || t;
  const expType = (t) => EXP_TYPE[t] || t;

  /* ---------------- formats ---------------- */
  const trimNum = (v) => Number(v).toFixed(2).replace(/\.?0+$/, ''); // "456.00" -> "456", "456.50" -> "456.5"
  const size = (b) => (Number(b) < 1048576 ? Math.round(Number(b) / 1024) + ' KB' : (Number(b) / 1048576).toFixed(1) + ' MB');
  const plain = (v) => (v == null || v === '' ? '–' : String(v));
  const expDates = (e) => fmtDate(e.start_date) + ' – ' + (e.is_ongoing ? 'Ongoing' : fmtDate(e.end_date));
  const marks = (r) => (r.level === 'DEGREE_SEM' ? (r.sgpa != null ? num2(r.sgpa) + ' SGPA' : null) : (r.percentage != null ? num2(r.percentage) + '%' : null));
  const fileUrl = (d) => '/api/documents/' + encodeURIComponent(d.uuid) + '/download';
  const fileLink = (d) => (d ? `<a class="linkbtn" href="${esc(fileUrl(d))}" target="_blank" rel="noopener">${esc(d.original_name)}</a>` : dash);
  const why = (rej) => (rej ? `<span class="why">${esc(rej.label)}${rej.text ? ': ' + esc(rej.text) : ''}</span>` : '');

  /* ---------------- side-by-side review ---------------- */
  function fieldRows(kind, item, prev) {
    const rows = [];
    if (kind === 'academic') {
      const defs = item.level === 'DEGREE_SEM'
        ? [['SGPA', 'sgpa', 'n2'], ['CGPA', 'cgpa', 'n2'], ['Backlogs in term', 'backlogs_in_term', ''], ['Active backlogs after term', 'active_backlogs_after_term', '']]
        : [['Institution', 'institution_name', ''], ['Board or university', 'board_or_university', ''], ['Passing year', 'passing_year', ''], ['Percentage', 'percentage', 'pct'], ['Obtained marks', 'obtained_marks', 'trim'], ['Total marks', 'total_marks', 'trim']];
      const fmt = (r, f, k) => {
        if (!r) return null;
        const x = r[f];
        if (x == null || x === '') return '–';
        return k === 'n2' ? num2(x) : k === 'pct' ? num2(x) + '%' : k === 'trim' ? trimNum(x) : String(x);
      };
      defs.forEach(([l, f, k]) => rows.push({ label: l, cur: fmt(item, f, k), prev: fmt(prev, f, k) }));
    } else if (kind === 'experience') {
      const defs = [['Type', (r) => expType(r.type)], ['Organization', (r) => plain(r.organization)], ['Role', (r) => plain(r.role_title)], ['Dates', expDates], ['Description', (r) => plain(r.description)]];
      defs.forEach(([l, fn]) => rows.push({ label: l, cur: fn(item), prev: prev ? fn(prev) : null }));
    }
    return rows;
  }

  function compareTable(rows, hasPrev) {
    return `<div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Field</th>${hasPrev ? '<th>Currently verified</th>' : ''}<th>${hasPrev ? 'Submitted' : 'Value'}</th></tr></thead>
      <tbody>${rows.map((r) => `<tr><td class="nw"><span class="name">${esc(r.label)}</span></td>${hasPrev ? `<td>${esc(r.prev)}</td>` : ''}<td${hasPrev && r.prev !== r.cur ? ' class="rv-chg"' : ''}>${esc(r.cur)}</td></tr>`).join('')}</tbody>
    </table></div>`;
  }

  function docPane(file) {
    const bar = file
      ? `<div><b>${esc(docType(file.document_type))}</b><div class="meta">${esc(file.original_name)} &middot; ${size(file.size_bytes)} &middot; Version ${esc(file.version)}</div></div>
         <div class="row wrap">${badge('doc', file.status)}<a class="btn btn-secondary" href="${esc(fileUrl(file))}" target="_blank" rel="noopener">${icon('down', 'sm')} Open in new tab</a></div>`
      : '<b>Document</b>';
    return `<div class="rv-doc"><div class="rv-bar">${bar}</div>
      <div class="rv-view" data-viewer>${file ? '<div class="skel" style="width:60%"></div><div class="skel"></div>' : '<p class="empty">No file is attached.</p>'}</div></div>`;
  }

  /** Fetches the file with the session cookie (audited download) and shows it inline, so the reviewer never leaves the drawer. */
  async function loadViewer(aside, file) {
    const box = $('[data-viewer]', aside);
    if (!file || !box) return null;
    try {
      const res = await fetch(fileUrl(file), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (res.status === 401 || res.status === 419) { location.href = '/login'; return null; }
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const mime = file.mime_type || res.headers.get('content-type') || 'application/octet-stream';
      const blob = new Blob([await res.arrayBuffer()], { type: mime });
      if (!aside.isConnected) return null;
      const url = URL.createObjectURL(blob);
      if (mime === 'application/pdf') box.innerHTML = `<iframe title="${esc(file.original_name)}" src="${url}"></iframe>`;
      else if (mime.indexOf('image/') === 0) box.innerHTML = `<img alt="${esc(file.original_name)}" src="${url}">`;
      else box.innerHTML = notice('neutral', 'info', 'This file type cannot be shown here. Use Open in new tab.');
      return url;
    } catch (e) {
      if (box.isConnected) box.innerHTML = notice('no', 'info', 'The file could not be loaded. Use Open in new tab.');
      return null;
    }
  }

  function rejectBox() {
    return `<div class="rv-reject" data-reject-box hidden>
      ${fld('f-reason_code', 'Reason', `<select id="f-reason_code" data-f="reason_code">${options(Object.entries(REASONS), '', 'Select a reason')}</select>`, { req: true })}
      <div class="field"><label for="f-reason">Note <span class="req" data-other-req aria-hidden="true" hidden>*</span></label>
        <textarea id="f-reason" data-f="reason" maxlength="500"></textarea>
        <span class="hint">Required only when the reason is Other. Up to 500 characters. The student sees this.</span>
        <span class="err" role="alert" hidden></span></div>
      <div class="row wrap" style="justify-content:flex-end">
        <button type="button" class="btn btn-secondary" data-reject-cancel>Cancel</button>
        <button type="button" class="btn btn-danger" data-reject-send>Confirm rejection</button>
      </div></div>`;
  }

  /**
   * o = { kind: 'academic' | 'experience' | 'document', item, onDone }
   * item = a verification-queue entry: the resource fields + student{id,university_id,full_name} (+ previous_verified for revisions).
   */
  function reviewDrawer(o) {
    const kind = o.kind;
    const item = o.item;
    const who = item.student;
    const isDoc = kind === 'document';
    const key = isDoc ? item.uuid : item.id;
    const prev = item.previous_verified || null;
    const title = kind === 'academic' ? item.label : kind === 'experience' ? expType(item.type) + ' at ' + item.organization : docType(item.document_type);
    const file = kind === 'academic' ? item.document : kind === 'experience' ? item.certificate : item;
    const submitted = isDoc ? item.uploaded_at : item.submitted_at;
    const path = '/coordinator/' + ({ academic: 'academic-records', experience: 'experiences', document: 'documents' })[kind] + '/' + encodeURIComponent(key);

    const details = isDoc
      ? `<dl class="kv"><dt>Document</dt><dd>${esc(docType(item.document_type))}</dd><dt>File</dt><dd>${esc(item.original_name)}</dd><dt>Version</dt><dd>${esc(item.version)}</dd><dt>Uploaded</dt><dd>${fmtDate(item.uploaded_at)}</dd></dl>`
      : compareTable(fieldRows(kind, item, prev), !!prev);
    const side = (prev ? notice('wait', 'info', 'This is a revision of a verified entry. Changed values are marked. The verified values stay in use until you approve.') : '') + details + rejectBox();

    let blobUrl = null;
    let closed = false;
    openDrawer({
      title: who.full_name,
      meta: `${esc(who.university_id)} &middot; ${esc(title)}${submitted ? ' &middot; submitted ' + fmtDate(submitted) : ''}`,
      body: docPane(file) + `<div class="rv-side">${side}</div>`,
      footer: `<span class="sp"></span><span class="row wrap" data-bar><button type="button" class="btn btn-danger" data-reject-open>Reject</button><button type="button" class="btn btn-primary" data-approve>Approve</button></span>`,
      onClose: () => { closed = true; if (blobUrl) URL.revokeObjectURL(blobUrl); },
      onMount: (el) => {
        el.classList.add('review');
        loadViewer(el, file).then((u) => { blobUrl = u; if (closed && u) URL.revokeObjectURL(u); });
        const box = $('[data-reject-box]', el);
        const bar = $('[data-bar]', el);

        el.addEventListener('change', (e) => {
          if (e.target.matches('[data-f="reason_code"]')) $('[data-other-req]', box).hidden = e.target.value !== 'OTHER';
        });
        el.addEventListener('click', async (e) => {
          if (e.target.closest('[data-reject-open]')) { box.hidden = false; bar.hidden = true; $('[data-f="reason_code"]', box).focus(); return; }
          if (e.target.closest('[data-reject-cancel]')) { clearErrors(box); box.hidden = true; bar.hidden = false; return; }

          const approve = e.target.closest('[data-approve]');
          if (approve) {
            approve.disabled = true;
            try {
              await api(path + '/approve', { method: 'POST' });
              closeDrawer();
              toast(title + ' approved.');
              if (o.onDone) o.onDone();
            } catch (err) { fail(err); approve.disabled = false; }
            return;
          }

          const send = e.target.closest('[data-reject-send]');
          if (send) {
            clearErrors(box);
            const code = $('[data-f="reason_code"]', box).value;
            const text = $('[data-f="reason"]', box).value.trim();
            const errs = {};
            if (!code) errs.reason_code = ['Choose a reason.'];
            if (code === 'OTHER' && !text) errs.reason = ['Add a note when the reason is Other.'];
            if (Object.keys(errs).length) { showErrors(box, errs); return; }
            const body = { reason_code: code };
            if (text) body.reason = text;
            send.disabled = true;
            try {
              await api(path + '/reject', { method: 'POST', body });
              closeDrawer();
              toast(title + ' rejected.');
              if (o.onDone) o.onDone();
            } catch (err) { fail(err, box); send.disabled = false; }
          }
        });
      },
    });
  }

  /* ---------------- unlock a verified, locked academic record ---------------- */
  function unlockDrawer(rec, done) {
    openDrawer({
      title: 'Unlock ' + rec.label,
      meta: 'Verified &middot; version ' + esc(rec.version),
      body: `<div data-summary></div>
        ${notice('wait', 'lock', 'Unlocking lets the student edit this verified record. Anything they save becomes a revision that you review again. The unlock is audited with your reason.')}
        ${fld('f-reason', 'Reason', textarea('reason', '', 'maxlength="300"'), { req: true, hint: '5 to 300 characters.' })}`,
      footer: '<span class="sp"></span><button type="button" class="btn btn-secondary" data-close>Cancel</button><button type="button" class="btn btn-primary" data-save>Unlock record</button>',
      onMount: (el) => el.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-save]');
        if (!b) return;
        clearErrors(el);
        const reason = $('[data-f="reason"]', el).value.trim();
        if (reason.length < 5) { showErrors(el, { reason: ['Use at least 5 characters.'] }); return; }
        b.disabled = true;
        try {
          await api('/coordinator/academic-records/' + rec.id + '/unlock', { method: 'POST', body: { reason } });
          closeDrawer();
          toast(rec.label + ' unlocked.');
          if (done) done();
        } catch (err) { fail(err, el); b.disabled = false; }
      }),
    });
  }

  /* ---------------- ask the student to upload an accepted item again ---------------- */
  /** s = { student, records, experiences, documents }  (as returned by GET /api/coordinator/students/{id}) */
  function reuploadDrawer(s, done) {
    const items = [];
    (s.records || []).filter((r) => r.status === 'VERIFIED').forEach((r) => items.push(['ACADEMIC_RECORD|' + r.id, 'Academic record: ' + r.label]));
    (s.experiences || []).filter((x) => x.status === 'VERIFIED').forEach((x) => items.push(['EXPERIENCE|' + x.id, 'Experience: ' + expType(x.type) + ' at ' + x.organization]));
    // DocumentResource exposes only a uuid; a document can be targeted when the row also carries its numeric id.
    (s.documents || []).filter((d) => d.status === 'APPROVED' && d.id != null && STANDALONE.includes(d.document_type)).forEach((d) => items.push(['DOCUMENT|' + d.id, 'Document: ' + docType(d.document_type) + ' (' + d.original_name + ')']));

    openDrawer({
      title: 'Request re-upload',
      meta: `${esc(s.student.full_name)} &middot; ${esc(s.student.university_id)}`,
      body: items.length
        ? `<div data-summary></div>${notice('neutral', 'info', 'The student is notified and the item reopens for editing. The verified values stay in use until you approve the new ones.')}
           ${fld('f-subject', 'Item to upload again', `<select id="f-subject" data-f="subject">${options(items, '', 'Select an item')}</select>`, { req: true })}
           ${fld('f-reason', 'Reason', textarea('reason', '', 'maxlength="500"'), { req: true, hint: '10 to 500 characters. The student sees this.' })}`
        : '<p class="empty">Nothing has been accepted yet, so there is nothing to send back.</p>',
      footer: '<span class="sp"></span><button type="button" class="btn btn-secondary" data-close>Cancel</button>' + (items.length ? '<button type="button" class="btn btn-primary" data-save>Send request</button>' : ''),
      onMount: (el) => el.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-save]');
        if (!b) return;
        clearErrors(el);
        const subject = $('[data-f="subject"]', el).value;
        const reason = $('[data-f="reason"]', el).value.trim();
        const errs = {};
        if (!subject) errs.subject = ['Choose the item to ask the student to upload again.'];
        if (reason.length < 10) errs.reason = ['Use at least 10 characters.'];
        if (Object.keys(errs).length) { showErrors(el, errs); return; }
        const [type, id] = subject.split('|');
        b.disabled = true;
        try {
          await api('/coordinator/students/' + s.student.id + '/reupload-requests', { method: 'POST', body: { subject_type: type, subject_id: Number(id), reason } });
          closeDrawer();
          toast('Re-upload requested from ' + s.student.full_name + '.');
          if (done) done();
        } catch (err) {
          if (err.status === 422 && (err.errors.subject_type || err.errors.subject_id)) showErrors(el, { subject: err.errors.subject_type || err.errors.subject_id, reason: err.errors.reason });
          else fail(err, el);
          b.disabled = false;
        }
      }),
    });
  }

  T.C = { badge, docType, expType, expDates, marks, trimNum, size, plain, fileUrl, fileLink, why, STANDALONE, SECTIONS, ADMISSION, GENDER, reviewDrawer, unlockDrawer, reuploadDrawer };
})();
</script>
