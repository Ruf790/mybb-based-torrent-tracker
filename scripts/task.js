(function () {
  'use strict';

  var L = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};

  // t(key, fallback, ...args): translation with English fallback and {1}, {2}... substitution
  function t(key, fallback) {
    var args = Array.prototype.slice.call(arguments, 2);
    var s = (typeof L[key] === 'string') ? L[key] : fallback;
    return s.replace(/\{(\d+)\}/g, function (m, n) {
      var v = args[parseInt(n, 10) - 1];
      return v === undefined ? m : String(v);
    });
  }

  // Confirm "Claim"
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('form[data-tk-claim]') || form.dataset.tkOk === '1') return;
    e.preventDefault();

    var name = form.dataset.tkClaim;
    var penalty = parseInt(form.dataset.tkPenalty || '0', 10);
    var text = t('claim_q', 'Claim the task "{1}"?', name) +
      (penalty > 0 ? ' ' + t('claim_penalty', 'If you do not complete it in time, {1} bonus points will be deducted.', penalty) : '');

    var go = function () {
      form.dataset.tkOk = '1';
      var btn = form.querySelector('button');
      if (btn) btn.disabled = true;
      form.submit();
    };

    if (typeof window.Swal === 'undefined') {
      if (window.confirm(text)) go();
      return;
    }
    window.Swal.fire({
      icon: 'question',
      text: text,
      showCancelButton: true,
      confirmButtonText: t('claim_btn', 'Claim'),
      cancelButtonText: t('cancel', 'Cancel'),
      reverseButtons: true
    }).then(function (r) { if (r.isConfirmed) go(); });
  });

  // Confirm "Abandon" (SweetAlert2 if loaded, otherwise the browser dialog)
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('form[data-tk-abandon]') || form.dataset.ok === '1') return;
    e.preventDefault();

    var pen = form.dataset.tkAbandonPenalty || '0';
    var text = t('abandon_q', 'Abandon the task "{1}"?', form.dataset.tkAbandon) + ' '
      + (pen !== '0' ? t('abandon_pen', '{1} bonus points will be deducted.', pen) : t('abandon_nopen', 'No bonus points will be deducted.')) + ' '
      + (form.dataset.tkAbandonDone === '1' ? t('abandon_done', 'All requirements are already met - you will lose the reward.') + ' ' : '')
      + t('abandon_after', 'You can claim another task right after.');

    var go = function () {
      form.dataset.ok = '1';
      form.querySelector('button').disabled = true;
      form.submit();
    };

    if (window.Swal) {
      window.Swal.fire({
        icon: 'warning',
        text: text,
        showCancelButton: true,
        confirmButtonText: t('abandon_btn', 'Abandon'),
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
