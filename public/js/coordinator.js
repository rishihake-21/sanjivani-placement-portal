/*
 * Coordinator portal behaviour for server-rendered Blade pages.
 * Opens <template id="tpl-*"> drawers and submits coordinator web actions with CSRF.
 */
(function () {
  'use strict';

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function csrf() { var m = $('meta[name="csrf-token"]'); return m ? m.content : ''; }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  var drawerTrigger = null;
  var toastTimer = null;

  function icon(name) {
    return '<svg class="icon sm" aria-hidden="true"><use href="#i-' + name + '"/></svg>';
  }

  function toast(message, ic) {
    var box = $('#toast');
    if (!box) { return; }
    box.innerHTML = '<div class="toast">' + icon(ic || 'check') + '<span>' + esc(message) + '</span></div>';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { box.innerHTML = ''; }, 4000);
  }

  function reloadWith(message) {
    try {
      sessionStorage.setItem('tpms.coordinator.toast', message || 'Done.');
      var c = $('#content');
      sessionStorage.setItem('tpms.coordinator.scroll', JSON.stringify({
        url: location.pathname + location.search,
        y: c ? c.scrollTop : 0,
      }));
    } catch (e) {}
    location.reload();
  }

  function restoreAfterReload() {
    try {
      var message = sessionStorage.getItem('tpms.coordinator.toast');
      if (message) {
        sessionStorage.removeItem('tpms.coordinator.toast');
        toast(message);
      }

      var raw = sessionStorage.getItem('tpms.coordinator.scroll');
      if (raw) {
        sessionStorage.removeItem('tpms.coordinator.scroll');
        var saved = JSON.parse(raw);
        var c = $('#content');
        if (c && saved.url === location.pathname + location.search && !location.hash) {
          c.scrollTop = saved.y;
        }
      }
    } catch (e) {}
  }

  function request(method, url, body) {
    var headers = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf(),
      'X-Requested-With': 'XMLHttpRequest',
    };

    return fetch(url, {
      method: method,
      headers: headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      credentials: 'same-origin',
    }).then(function (res) {
      return res.text().then(function (text) {
        var data = null;
        try { data = text ? JSON.parse(text) : null; } catch (e) {}
        if (res.ok) { return data; }
        throw {
          status: res.status,
          message: (data && data.message) || 'Something went wrong. Please try again.',
          errors: (data && data.errors) || {},
        };
      });
    }, function () {
      throw { status: 0, message: 'Could not reach the server. Check your connection and try again.', errors: {} };
    });
  }

  function clearField(field) {
    if (!field) { return; }
    field.classList.remove('bad');
    $$('.err', field).forEach(function (err) { err.remove(); });
    $$('.hint', field).forEach(function (hint) { hint.hidden = false; });
    $$('input,select,textarea', field).forEach(function (control) { control.removeAttribute('aria-invalid'); });
  }

  function setFieldError(field, message) {
    clearField(field);
    field.classList.add('bad');
    $$('.hint', field).forEach(function (hint) { hint.hidden = true; });
    var err = document.createElement('span');
    err.className = 'err';
    err.setAttribute('role', 'alert');
    err.textContent = message;
    field.appendChild(err);
    $$('input,select,textarea', field).forEach(function (control) { control.setAttribute('aria-invalid', 'true'); });
  }

  function clearErrors(root) {
    $$('.field.bad', root).forEach(clearField);
  }

  function showErrors(root, errors, aliases) {
    clearErrors(root);
    var first = null;
    var orphan = null;
    Object.keys(errors || {}).forEach(function (key) {
      var name = key.replace(/\.\d+$/, '');
      if (aliases && aliases[name]) { name = aliases[name]; }
      var field = $('[data-field="' + name + '"]', root);
      var message = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
      if (field) {
        setFieldError(field, message);
        if (!first) { first = field; }
      } else if (!orphan) {
        orphan = message;
      }
    });

    if (first) {
      first.scrollIntoView({ block: 'nearest' });
      var control = $('input:not([disabled]),select:not([disabled]),textarea:not([disabled])', first);
      if (control) { control.focus({ preventScroll: true }); }
    }
    if (orphan) { toast(orphan, 'info'); }
  }

  function collect(form) {
    var out = {};
    $$('[name]', form).forEach(function (control) {
      if (control.disabled) { return; }
      var value = control.type === 'checkbox' ? control.checked : control.value.trim();
      if (control.name === 'subject') {
        var parts = value.split('|');
        if (parts.length === 2) {
          out.subject_type = parts[0];
          out.subject_id = Number(parts[1]);
        }
        return;
      }
      if (value !== '') { out[control.name] = value; }
    });
    return out;
  }

  function validate(form, body) {
    var errors = {};
    var min = Number(form.dataset.minReason || 0);
    if (form.dataset.reasonRequired === 'always') {
      if (!body.reason || body.reason.length < min) {
        errors.reason = 'Use at least ' + min + ' characters.';
      }
    }
    if (form.dataset.reasonRequired === 'other' && body.reason_code === 'OTHER' && !body.reason) {
      errors.reason = 'Add a note when the reason is Other.';
    }
    if (form.dataset.reasonRequired === 'other' && !body.reason_code) {
      errors.reason_code = 'Choose a reason.';
    }
    if (form.querySelector('[name="subject"]') && !form.querySelector('[name="subject"]').value) {
      errors.subject = 'Choose the item to ask the student to upload again.';
    }
    return errors;
  }

  function submitForm(form) {
    clearErrors(form);
    var body = collect(form);
    var clientErrors = validate(form, body);
    if (Object.keys(clientErrors).length) {
      showErrors(form, clientErrors);
      return;
    }

    var submitters = $$('button[type="submit"]', form);
    submitters.forEach(function (button) {
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
    });

    request('POST', form.dataset.action, body).then(function () {
      reloadWith(form.dataset.done || 'Done.');
    }, function (err) {
      submitters.forEach(function (button) {
        button.disabled = false;
        button.removeAttribute('aria-busy');
      });
      if (err.status === 422 && Object.keys(err.errors || {}).length) {
        showErrors(form, err.errors, { subject_type: 'subject', subject_id: 'subject' });
      } else {
        toast(err.message, 'info');
      }
    });
  }

  function openDrawer(id, trigger) {
    var root = $('#drawer-root');
    var tpl = document.getElementById('tpl-' + id);
    if (!root || !tpl) { return; }
    closeDrawer(true);
    drawerTrigger = trigger || document.activeElement;
    root.replaceChildren(tpl.content.cloneNode(true));
    var first = $('.dr-b input:not([disabled]),.dr-b select:not([disabled]),.dr-b textarea:not([disabled]),[data-drawer-close]', root);
    if (first) { first.focus({ preventScroll: true }); }
  }

  function closeDrawer(silent) {
    var root = $('#drawer-root');
    if (!root || !root.firstChild) { return; }
    root.replaceChildren();
    if (!silent && drawerTrigger && document.contains(drawerTrigger)) {
      drawerTrigger.focus({ preventScroll: true });
    }
    drawerTrigger = null;
  }

  function drawerOpen() {
    var root = $('#drawer-root');
    return !!(root && root.firstChild);
  }

  function confirmPost(button) {
    button.disabled = true;
    request('POST', button.dataset.post).then(function () {
      reloadWith(button.dataset.done || 'Done.');
    }, function (err) {
      button.disabled = false;
      toast(err.message, 'info');
    });
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target.closest) { return; }

    var opener = target.closest('[data-open]');
    if (opener && !opener.disabled) {
      openDrawer(opener.dataset.open, opener);
      return;
    }

    if (target.closest('[data-drawer-close]')) {
      closeDrawer();
      return;
    }

    var rejectOpen = target.closest('[data-reject-open]');
    if (rejectOpen) {
      var drawer = rejectOpen.closest('.drawer');
      var box = $('[data-reject-box]', drawer);
      var bar = $('[data-approve-bar]', drawer);
      if (box) { box.hidden = false; }
      if (bar) { bar.hidden = true; }
      var first = $('[name="reason_code"]', box);
      if (first) { first.focus(); }
      return;
    }

    var rejectCancel = target.closest('[data-reject-cancel]');
    if (rejectCancel) {
      var rejectBox = rejectCancel.closest('[data-reject-box]');
      var rejectDrawer = rejectCancel.closest('.drawer');
      clearErrors(rejectBox);
      rejectBox.hidden = true;
      var approveBar = $('[data-approve-bar]', rejectDrawer);
      if (approveBar) { approveBar.hidden = false; }
      return;
    }

    var poster = target.closest('[data-post]');
    if (poster && !poster.disabled) {
      confirmPost(poster);
    }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form.matches || !form.matches('[data-cajax]')) { return; }
    event.preventDefault();
    submitForm(form);
  });

  document.addEventListener('change', function (event) {
    if (!event.target.matches || !event.target.matches('[name="reason_code"]')) { return; }
    var box = event.target.closest('[data-reject-box]');
    var required = $('[data-other-req]', box);
    if (required) { required.hidden = event.target.value !== 'OTHER'; }
  });

  document.addEventListener('input', function (event) {
    var field = event.target.closest && event.target.closest('.field.bad');
    if (field) { clearField(field); }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && drawerOpen()) {
      closeDrawer();
      return;
    }
    if (event.key !== 'Tab' || !drawerOpen()) { return; }
    var focusable = $$('#drawer-root button:not([disabled]),#drawer-root input:not([disabled]),#drawer-root select:not([disabled]),#drawer-root textarea:not([disabled]),#drawer-root a[href]');
    if (!focusable.length) { return; }
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', restoreAfterReload);
  } else {
    restoreAfterReload();
  }
})();
