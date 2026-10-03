(function () {
  'use strict';

  // Our container: found through our own forms, in case the panel layout has another .ex element
  var own = document.getElementById('ex-bulk-form') || document.getElementById('ex-form') || document.getElementById('ex-end-form');
  var root = (own && own.closest('.ex')) || document.querySelector('.ex');
  if (!root) return;

  var hasSwal = typeof window.Swal !== 'undefined';
  var COLORS = { danger: '#dc3545', warning: '#f0ad4e', primary: '#0d6efd' };

  function ask(text, tone) {
    if (!hasSwal) return Promise.resolve(window.confirm(text));
    return window.Swal.fire({
      icon: tone === 'danger' ? 'warning' : 'question',
      text: text,
      showCancelButton: true,
      confirmButtonText: 'Yes',
      cancelButtonText: 'Cancel',
      confirmButtonColor: COLORS[tone] || COLORS.primary,
      reverseButtons: true,
      focusCancel: tone === 'danger'
    }).then(function (r) { return r.isConfirmed; });
  }

  // Confirmation for forms with data-ex-confirm
  root.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('form[data-ex-confirm]') || form.dataset.exOk === '1') return;
    e.preventDefault();
    ask(form.dataset.exConfirm, form.dataset.exTone || 'primary').then(function (ok) {
      if (!ok) return;
      form.dataset.exOk = '1';
      form.submit();
    });
  });

  // Prevent double submit
  root.addEventListener('submit', function (e) {
    if (e.defaultPrevented || e.target.method.toLowerCase() !== 'post') return;
    var btn = e.target.querySelector('button[type="submit"], button:not([type])');
    if (btn) setTimeout(function () { btn.disabled = true; }, 0);
  });

  // ── Exam form ──
  var form = document.getElementById('ex-form');
  if (form) {
    var syncType = function () {
      var t = (form.querySelector('input[name="type"]:checked') || {}).value || '1';
      form.querySelectorAll('[data-for-type]').forEach(function (el) {
        el.hidden = el.dataset.forType !== t;
      });
    };
    var syncMode = function () {
      var m = (form.querySelector('input[name="time_mode"]:checked') || {}).value || 'duration';
      form.querySelectorAll('[data-mode]').forEach(function (el) {
        el.hidden = el.dataset.mode !== m;
      });
    };
    form.addEventListener('change', function (e) {
      if (e.target.name === 'type') syncType();
      if (e.target.name === 'time_mode') syncMode();
      if (e.target.name && e.target.name.indexOf('idx_checked') === 0) {
        e.target.closest('.ex-idx').classList.toggle('is-on', e.target.checked);
      }
    });
    syncType();
    syncMode();

    // ── Requirements changed while people are doing the exam: confirm before saving ──
    if (form.dataset.exOngoing) {
      var ongoing = parseInt(form.dataset.exOngoing, 10) || 0;
      var orig = JSON.parse(form.dataset.exOrigIdx || '{}');
      var meta = JSON.parse(form.dataset.exIdxMeta || '{}');
      var fmtN = function (k, v) {
        var u = (meta[k] && meta[k][1]) || '';
        return Number(v).toLocaleString('en-US') + (u ? ' ' + u : '');
      };
      var current = function () {
        var m = {};
        Object.keys(meta).forEach(function (k) {
          var cb = form.querySelector('input[name="idx_checked[' + k + ']"]');
          var val = form.querySelector('input[name="idx_value[' + k + ']"]');
          if (cb && cb.checked) m[k] = parseInt(val && val.value, 10) || 0;
        });
        return m;
      };
      var diff = function () {
        var now = current(), out = [];
        Object.keys(meta).forEach(function (k) {
          var name = meta[k][0], had = k in orig, has = k in now;
          if (had && has && orig[k] !== now[k]) out.push(name + ': ' + fmtN(k, orig[k]) + ' → ' + fmtN(k, now[k]));
          else if (had && !has) out.push(name + ': removed (was ' + fmtN(k, orig[k]) + ')');
          else if (!had && has) out.push(name + ': added, ' + fmtN(k, now[k]));
        });
        return out;
      };
      var escH = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };

      // On the form itself, so it runs before the root "prevent double submit" handler
      form.addEventListener('submit', function (e) {
        if (form.elements.confirm_requirements.value === '1') return;
        var lines = diff();
        if (!lines.length) return;
        e.preventDefault();

        var go = function (notify) {
          form.elements.confirm_requirements.value = '1';
          form.elements.notify_requirements.value = notify ? '1' : '';
          var btn = form.querySelector('.ex-bar button:not([type="button"])');
          if (btn) btn.disabled = true;
          form.submit();
        };
        var text = 'Requirements changed:\n- ' + lines.join('\n- ') + '\n\nThis applies at once to ' + ongoing
                 + ' ongoing attempt(s): their progress is checked against the new requirements.';

        if (!hasSwal) {
          if (window.confirm(text + '\n\nSave anyway?')) go(window.confirm('Send these users a PM about the change?'));
          return;
        }
        window.Swal.fire({
          icon: 'warning',
          title: 'Requirements changed',
          html: '<ul style="text-align:left;margin:0 0 1rem">' + lines.map(function (l) { return '<li>' + escH(l) + '</li>'; }).join('') + '</ul>'
              + '<p style="text-align:left">This applies at once to <b>' + ongoing + '</b> ongoing attempt(s): their progress is checked against the new requirements'
              + ' and the result is counted by them.</p>'
              + '<label style="display:flex;gap:.5rem;align-items:center;justify-content:flex-start"><input type="checkbox" id="ex-rq-notify" checked> Send these users a PM about the change</label>',
          showCancelButton: true,
          confirmButtonText: 'Save anyway',
          cancelButtonText: 'Cancel',
          confirmButtonColor: COLORS.warning,
          reverseButtons: true,
          focusCancel: true,
          preConfirm: function () { return document.getElementById('ex-rq-notify').checked; }
        }).then(function (r) { if (r.isConfirmed) go(r.value); });
      });
    }

    // ── "Check who gets it": coverage for the filters in the form, nothing saved ──
    var simBtn = form.querySelector('[data-ex-simulate]');
    var simOut = form.querySelector('[data-ex-sim-out]');
    if (simBtn && simOut && window.fetch && window.FormData) {
      var simulate = function () {
        var fd = new FormData(form);
        fd.set('do', 'simulate');
        simBtn.disabled = true;
        simOut.innerHTML = '<div class="ex-sub"><i class="fa-solid fa-spinner fa-spin"></i> Counting…</div>';
        fetch(form.getAttribute('action'), { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) { simOut.innerHTML = d && d.html ? d.html : '<div class="ex-sub">No answer.</div>'; })
          .catch(function () { simOut.innerHTML = '<div class="ex-alert ex-tone-danger"><i class="fa-solid fa-triangle-exclamation"></i><div>Could not check. Reload the page and try again.</div></div>'; })
          .then(function () { simBtn.disabled = false; });
      };
      simBtn.addEventListener('click', simulate);
      // Filters changed after a check: the old numbers are no longer true
      form.addEventListener('input', function (e) {
        if (simOut.innerHTML !== '' && e.target.closest('.ex-section') === simBtn.closest('.ex-section')) {
          simOut.innerHTML = '<div class="ex-sub"><i class="fa-solid fa-rotate"></i> Filters changed, check again.</div>';
        }
      });
      form.addEventListener('change', function (e) {
        if (simOut.innerHTML !== '' && (e.target.name === 'is_discovered' || e.target.name === 'enabled' || e.target.name === 'type')) {
          simOut.innerHTML = '<div class="ex-sub"><i class="fa-solid fa-rotate"></i> Settings changed, check again.</div>';
        }
      });
    }
  }

  // ── Date pickers: flatpickr, same options as datepicker.js plus time ──
  var FP_OPTS = {
    enableTime:    true,
    time_24hr:     true,
    minuteIncrement: 1,              // default 5 makes e.g. 13:56 "invalid" and the browser blocks the form submit
    dateFormat:    'Y-m-d H:i',      // what the server gets
    altInput:      true,
    altFormat:     'd F Y, H:i',     // what the user sees
    allowInput:    true,
    disableMobile: true,
    static:        true
  };
  if (window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.ru && document.documentElement.lang === 'ru') {
    FP_OPTS.locale = window.flatpickr.l10ns.ru;
  }

  var pickers = {};
  if (window.flatpickr) {
    root.querySelectorAll('input.ex-date').forEach(function (el) {
      pickers['#' + el.id] = window.flatpickr(el, FP_OPTS);
    });

    // Begin <= End, as linkRange() in datepicker.js
    root.querySelectorAll('input.ex-date[data-ex-from]').forEach(function (el) {
      var to = pickers['#' + el.id];
      var from = pickers[el.dataset.exFrom];
      if (!to || !from) return;
      from.config.onChange.push(function (sel) { to.set('minDate', sel && sel[0] ? sel[0] : null); });
      to.config.onChange.push(function (sel) { from.set('maxDate', sel && sel[0] ? sel[0] : null); });
      if (from.selectedDates[0]) to.set('minDate', from.selectedDates[0]);
      if (to.selectedDates[0]) from.set('maxDate', to.selectedDates[0]);
    });
  }

  // Clear button next to a date field (as data-clear in datepicker.js)
  root.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-ex-clear]');
    if (!btn) return;
    var el = document.querySelector(btn.dataset.exClear);
    if (!el) return;
    if (el._flatpickr) el._flatpickr.clear(); else el.value = '';
    Object.keys(pickers).forEach(function (k) {
      pickers[k].set('minDate', null);
      pickers[k].set('maxDate', null);
    });
  });

  // ── Bulk actions on participants ──
  var bulk = document.getElementById('ex-bulk-form');
  if (bulk) {
    var boxes = function () { return document.querySelectorAll('input[name="euids[]"][form="ex-bulk-form"]'); };
    var all = document.querySelector('[data-ex-check-all]');
    var counter = bulk.querySelector('[data-ex-bulk-count]');

    var refresh = function () {
      var list = boxes(), n = 0;
      list.forEach(function (b) {
        if (b.checked) n++;
        var tr = b.closest('tr');
        if (tr) tr.classList.toggle('is-selected', b.checked);
      });
      counter.textContent = n;
      bulk.hidden = n === 0;
      if (all) {
        all.checked = n > 0 && n === list.length;
        all.indeterminate = n > 0 && n < list.length;
      }
      return n;
    };

    // Document level and capture phase: a panel script stopping propagation can't swallow it.
    // "click" too, for checkbox stylers that change .checked without firing "change".
    var onToggle = function (e) {
      var t = e.target;
      if (!t || t.type !== 'checkbox') return;
      if (t === all) {
        var on = all.checked;
        boxes().forEach(function (b) { b.checked = on; });
        refresh();
      } else if (t.name === 'euids[]') {
        refresh();
      }
    };
    document.addEventListener('change', onToggle, true);
    document.addEventListener('click', function (e) { setTimeout(function () { onToggle(e); }, 0); }, true);
    bulk.querySelector('[data-ex-bulk-clear]').addEventListener('click', function () {
      boxes().forEach(function (b) { b.checked = false; });
      bulk.elements.bulk_action.value = '';
      syncApply();
      refresh();
    });

    // Apply: disabled until an action is chosen; red for destructive actions, icon per action
    var applyBtn = bulk.querySelector('[data-ex-bulk-apply]');
    var ICONS = { update_end: 'fa-calendar-days', pm: 'fa-envelope', avoid: 'fa-user-shield',
                  recover: 'fa-rotate-left', finish: 'fa-flag-checkered', remove: 'fa-trash-can' };
    var syncApply = function () {
      var act = bulk.elements.bulk_action.value;
      applyBtn.disabled = !act;
      applyBtn.classList.toggle('ex-btn--danger', act === 'remove' || act === 'finish');
      var ic = applyBtn.querySelector('i');
      if (ic) ic.className = 'fa-solid ' + (ICONS[act] || 'fa-bolt');
    };
    bulk.elements.bulk_action.addEventListener('change', syncApply);
    syncApply();

    var esc = function (s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
    var send = function () {
      bulk.querySelector('[data-ex-bulk-apply]').disabled = true;
      bulk.submit();
    };

    // On the form itself, so it runs before the root "prevent double submit" handler
    bulk.addEventListener('submit', function (e) {
      e.preventDefault();
      var n = refresh();
      var act = bulk.elements.bulk_action.value;
      if (!n) return;
      if (!act) {
        if (hasSwal) window.Swal.fire({ icon: 'info', text: 'Choose an action first.' }); else window.alert('Choose an action first.');
        return;
      }
      var who = n + ' selected attempt(s)';

      if (act === 'update_end') {
        if (!hasSwal) {
          var end = window.prompt('New end time for ' + who + ' (YYYY-MM-DD HH:MM):', '');
          if (!end) return;
          bulk.elements.end.value = end;
          bulk.elements.reason.value = window.prompt('Reason (optional):', '') || '';
          send();
          return;
        }
        window.Swal.fire({
          title: 'Change end time',
          html: '<p>' + esc(who) + '. Each user gets a PM.</p>' +
                '<input type="text" id="ex-bk-end" class="swal2-input" autocomplete="off" placeholder="New end time">' +
                '<input type="text" id="ex-bk-reason" class="swal2-input" placeholder="Reason (sent by PM)">',
          showCancelButton: true, confirmButtonText: 'Save', cancelButtonText: 'Cancel', reverseButtons: true,
          didOpen: function () {
            if (window.flatpickr) window.flatpickr('#ex-bk-end', Object.assign({}, FP_OPTS, { static: false }));
          },
          willClose: function () {
            var el = document.getElementById('ex-bk-end');
            if (el && el._flatpickr) el._flatpickr.destroy();
          },
          preConfirm: function () {
            var v = document.getElementById('ex-bk-end').value;
            if (!v) { window.Swal.showValidationMessage('Enter the time'); return false; }
            return { end: v, reason: document.getElementById('ex-bk-reason').value };
          }
        }).then(function (r) {
          if (!r.isConfirmed) return;
          bulk.elements.end.value = r.value.end;
          bulk.elements.reason.value = r.value.reason;
          send();
        });
        return;
      }

      if (act === 'pm') {
        if (!hasSwal) {
          var subj = window.prompt('PM subject:', '');
          var msg = subj ? window.prompt('PM message:', '') : '';
          if (!subj || !msg) return;
          bulk.elements.subject.value = subj;
          bulk.elements.message.value = msg;
          send();
          return;
        }
        window.Swal.fire({
          title: 'Send PM',
          html: '<p>To the users of ' + esc(who) + ', from you.</p>' +
                '<input type="text" id="ex-bk-subj" class="swal2-input" maxlength="120" placeholder="Subject">' +
                '<textarea id="ex-bk-msg" class="swal2-textarea" rows="5" placeholder="Message (MyCode allowed)"></textarea>',
          showCancelButton: true, confirmButtonText: 'Send', cancelButtonText: 'Cancel', reverseButtons: true,
          preConfirm: function () {
            var s = document.getElementById('ex-bk-subj').value.trim();
            var m = document.getElementById('ex-bk-msg').value.trim();
            if (!s || !m) { window.Swal.showValidationMessage('Enter the subject and the message'); return false; }
            return { s: s, m: m };
          }
        }).then(function (r) {
          if (!r.isConfirmed) return;
          bulk.elements.subject.value = r.value.s;
          bulk.elements.message.value = r.value.m;
          send();
        });
        return;
      }

      var texts = {
        avoid:   ['Avoid ' + who + '? They can be recovered later.', 'warning'],
        recover: ['Recover ' + who + '? Users that already have another exam or task are skipped.', 'primary'],
        finish:  ['Finish ' + who + ' now? Results are counted by the usual rules: requirements met = passed, otherwise failed (exams: account disabled, tasks: penalty).', 'danger'],
        remove:  ['Delete ' + who + ' with no consequences? Progress is lost.', 'danger']
      };
      var t = texts[act] || ['Apply to ' + who + '?', 'primary'];
      ask(t[0], t[1]).then(function (ok) { if (ok) send(); });
    });

    refresh();
  }

  // ── Change end time ──
  var endForm = document.getElementById('ex-end-form');
  root.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-ex-end]');
    if (!btn || !endForm) return;
    var current = btn.dataset.exEndValue || '';

    var submit = function (end, reason) {
      endForm.elements.euid.value = btn.dataset.exEnd;
      endForm.elements.end.value = end;
      endForm.elements.reason.value = reason || '';
      endForm.submit();
    };

    if (!hasSwal) {
      var end = window.prompt('New end time (YYYY-MM-DD HH:MM):', current);
      if (end) submit(end, window.prompt('Reason (optional):', '') || '');
      return;
    }

    window.Swal.fire({
      title: 'Change end time',
      html: '<input type="text" id="ex-sw-end" class="swal2-input" autocomplete="off" value="' + current + '">' +
            '<input type="text" id="ex-sw-reason" class="swal2-input" placeholder="Reason (sent to the user by PM)">',
      showCancelButton: true,
      confirmButtonText: 'Save',
      cancelButtonText: 'Cancel',
      reverseButtons: true,
      didOpen: function () {
        if (window.flatpickr) {
          // Not static inside the dialog: the calendar floats above it
          window.flatpickr('#ex-sw-end', Object.assign({}, FP_OPTS, { static: false }));
        }
      },
      willClose: function () {
        var el = document.getElementById('ex-sw-end');
        if (el && el._flatpickr) el._flatpickr.destroy();
      },
      preConfirm: function () {
        var v = document.getElementById('ex-sw-end').value;
        if (!v) { window.Swal.showValidationMessage('Enter the time'); return false; }
        return { end: v, reason: document.getElementById('ex-sw-reason').value };
      }
    }).then(function (r) {
      if (r.isConfirmed) submit(r.value.end, r.value.reason);
    });
  });
})();