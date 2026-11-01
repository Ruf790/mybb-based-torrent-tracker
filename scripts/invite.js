/* invite.js — invite.php: flash toast, copy invite link, revoke confirmation */
(function () {
  'use strict';

  var L = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};

  // t(key, fallback, ...args): translation with fallback; substitutes {1} and %1$s
  function t(key, fallback) {
    var args = Array.prototype.slice.call(arguments, 2);
    var s = (typeof L[key] === 'string') ? L[key] : fallback;
    args.forEach(function (a, i) {
      var n = i + 1;
      s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
    });
    return s;
  }

  function toast(msg, type) {
    if (typeof window.showToast === 'function') window.showToast(msg, type);
  }

  // Flash message after Post/Redirect/Get
  document.addEventListener('DOMContentLoaded', function () {
    var page = document.querySelector('.iv-page[data-flash-msg]');
    if (page) toast(page.getAttribute('data-flash-msg'), page.getAttribute('data-flash-type') || 'info');
  });

  // Copy invite link
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      document.body.removeChild(ta);
      if (ok) { resolve(); } else { reject(new Error('copy failed')); }
    });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.iv-page [data-iv-link]');
    if (!btn) return;
    var url = btn.getAttribute('data-iv-link');
    var label = btn.querySelector('.iv-code__txt');

    copyText(url).then(function () {
      if (label && !btn.dataset.ivOrig) {
        btn.dataset.ivOrig = label.textContent;
        label.textContent = t('copied', 'Copied!');
        btn.classList.add('is-copied');
        setTimeout(function () {
          label.textContent = btn.dataset.ivOrig;
          delete btn.dataset.ivOrig;
          btn.classList.remove('is-copied');
        }, 2000);
      }
      toast(t('copied_toast', 'Invite link copied to clipboard!'), 'success');
    }).catch(function () {
      window.prompt(t('copy_manual', 'Copy the invite link:'), url);
    });
  });

  // Revoke confirmation (SweetAlert2 if loaded, otherwise the browser dialog)
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('.iv-page form.iv-revoke-form') || form.dataset.ivOk === '1') return;
    e.preventDefault();

    var text = t('revoke_confirm', 'Revoke this invite?');
    var go = function () {
      form.dataset.ivOk = '1';
      var b = form.querySelector('button');
      if (b) b.disabled = true;
      form.submit();
    };

    if (window.Swal) {
      window.Swal.fire({
        icon: 'warning',
        text: text,
        showCancelButton: true,
        confirmButtonText: t('btn_revoke', 'Revoke'),
        cancelButtonText: t('cancel', 'Cancel'),
        confirmButtonColor: '#dc3545',
        reverseButtons: true,
        focusCancel: true
      }).then(function (r) { if (r.isConfirmed) go(); });
    } else if (window.confirm(text)) {
      go();
    }
  });
})();
