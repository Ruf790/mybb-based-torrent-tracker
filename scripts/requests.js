/* requests.php — single request page: vote (+ bounty), comments, confirmations.
   Needs AGS_LANG and RQ_POST_KEY (printed by requests.php), SweetAlert2 + swal-notify.js (showToast). */
(function () {
  'use strict';

  var L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
  var POST_KEY = (typeof RQ_POST_KEY === 'string') ? RQ_POST_KEY : '';

  // t(key, fallback, ...args): {1} and %1$s substitution
  function t(key, fallback) {
    var args = Array.prototype.slice.call(arguments, 2);
    var s = (typeof L[key] === 'string') ? L[key] : fallback;
    args.forEach(function (a, i) {
      var n = i + 1;
      s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
    });
    return s;
  }

  function esc(text) {
    var d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
  }

  function notify(msg, type) {
    if (typeof window.showToast === 'function') window.showToast(esc(msg), type || 'error');
    else window.alert(msg);
  }

  function httpError(err) {
    var m = String(err && err.message || '');
    return m.indexOf('HTTP') === 0
      ? t('server_error', 'Server error (HTTP {1}). Please try again.', m.replace('HTTP ', ''))
      : t('network_error', 'Network error. Please check your connection and try again.');
  }

  function post(url, data) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams(Object.assign({ my_post_key: POST_KEY }, data))
    }).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  function busy(btn, on, html) {
    if (!btn) return;
    if (on) {
      btn.dataset.html = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>' + (html ? ' ' + html : '');
      btn.disabled = true;
    } else {
      btn.innerHTML = btn.dataset.html || btn.innerHTML;
      btn.disabled = false;
    }
  }

  // ── Vote (+ optional bounty) ──
  function vote(btn) {
    var rid = btn.getAttribute('data-rq-vote');
    var input = document.getElementById('bountyInput');
    var bounty = input ? (parseFloat(String(input.value).replace(',', '.')) || 0) : 0;

    busy(btn, true, esc(t('voting', 'Voting...')));
    post('requests.php?action=vote&rid=' + encodeURIComponent(rid), { bounty: bounty })
      .then(function (data) {
        if (!data || !data.success) {
          busy(btn, false);
          notify((data && data.error) || t('vote_error', 'Error processing vote'));
          return;
        }
        ['vote-count', 'vote-count-sidebar'].forEach(function (id) {
          var el = document.getElementById(id);
          if (!el) return;
          el.textContent = data.votes;
          el.classList.remove('rq-pop');
          void el.offsetWidth;
          el.classList.add('rq-pop');
        });
        setTimeout(function () { location.reload(); }, 600);
      })
      .catch(function (err) {
        busy(btn, false);
        notify(httpError(err));
      });
  }

  // ── Comment ──
  function comment(btn) {
    var rid = btn.getAttribute('data-rq-comment');
    var box = document.getElementById('commentText');
    var msg = box ? box.value.trim() : '';
    if (!msg) {
      notify(t('comment_empty', 'Please enter a comment.'), 'warning');
      if (box) box.focus();
      return;
    }
    busy(btn, true);
    post('requests.php?action=add_comment&rid=' + encodeURIComponent(rid), { message: msg })
      .then(function (data) {
        if (data && data.success) {
          location.hash = 'rq-comments';
          location.reload();
          return;
        }
        busy(btn, false);
        notify((data && data.error) || t('comment_error', 'Error adding comment'));
      })
      .catch(function (err) {
        busy(btn, false);
        notify(httpError(err));
      });
  }

  document.addEventListener('click', function (e) {
    var v = e.target.closest('[data-rq-vote]');
    if (v) { e.preventDefault(); vote(v); return; }
    var c = e.target.closest('[data-rq-comment]');
    if (c) { e.preventDefault(); comment(c); }
  });

  // Enter sends the comment, Shift+Enter = new line
  document.addEventListener('keydown', function (e) {
    if (e.target && e.target.id === 'commentText' && e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      var btn = document.getElementById('commentBtn');
      if (btn && !btn.disabled) comment(btn);
    }
  });

  // Cancel / delete: SweetAlert2 confirmation, confirm() if Swal didn't load
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches('form[data-rq-confirm]') || f.dataset.ok === '1') return;
    e.preventDefault();
    var go = function () {
      f.dataset.ok = '1';
      var b = f.querySelector('button');
      if (b) b.disabled = true;
      f.submit();
    };
    if (window.Swal) {
      window.Swal.fire({
        icon: 'warning', text: f.dataset.rqConfirm, showCancelButton: true, reverseButtons: true, focusCancel: true,
        confirmButtonText: t('yes', 'Yes'), cancelButtonText: t('no', 'No'), confirmButtonColor: '#dc3545'
      }).then(function (r) { if (r.isConfirmed) go(); });
    } else if (window.confirm(f.dataset.rqConfirm)) {
      go();
    }
  });
})();
