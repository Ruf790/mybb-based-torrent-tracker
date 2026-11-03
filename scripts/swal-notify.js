/* ============================================================================
   swal-notify.js — уведомления через SweetAlert2 вместо toast.js
   Даёт тот же глобальный API: showToast(message, type), поэтому bookmark.js,
   browse.js и browse-moderation.js работают без изменений.
   Нужен /scripts/sweetalert2.min.js (+ sweetalert2.min.css). Если Swal нет -
   откат на alert().
   message - HTML (как в toast.js: вызывающий код сам экранирует текст).
   type    - success | error | danger | warning | info
   Заголовки: window.AGS_TOAST_LANG (печатается header.php), иначе English.
   ============================================================================ */
(function () {
  'use strict';

  function lang(key, fallback) {
    var d = (typeof window.AGS_TOAST_LANG === 'object' && window.AGS_TOAST_LANG) ? window.AGS_TOAST_LANG : null;
    return (d && typeof d[key] === 'string' && d[key] !== '') ? d[key] : fallback;
  }

  function normType(type) {
    switch (String(type || 'info').toLowerCase()) {
      case 'success': return 'success';
      case 'error':
      case 'danger':  return 'error';
      case 'warning':
      case 'warn':    return 'warning';
      default:        return 'info';
    }
  }

  function siteTheme() {
    var t = document.documentElement.getAttribute('data-bs-theme')
         || (document.body && document.body.getAttribute('data-bs-theme'));
    return t === 'dark' ? 'dark' : 'light';
  }

  var TITLES = {
    success: ['success', 'Success'],
    error:   ['error',   'Error'],
    warning: ['warning', 'Warning'],
    info:    ['info',    'Info']
  };

  window.showToast = function (message, type) {
    var icon = normType(type);

    if (!window.Swal || typeof window.Swal.fire !== 'function') {
      var tmp = document.createElement('div');
      tmp.innerHTML = String(message == null ? '' : message);
      window.alert(tmp.textContent || '');
      return;
    }

    var timer = (icon === 'error' || icon === 'warning') ? 6000 : 3500;

    return window.Swal.fire({
      toast: true,
      position: 'top-end',
      theme: siteTheme(),
      icon: icon,
      title: lang(TITLES[icon][0], TITLES[icon][1]),
      html: String(message == null ? '' : message),
      showConfirmButton: false,
      showCloseButton: true,
      closeButtonAriaLabel: lang('close', 'Close'),
      timer: timer,
      timerProgressBar: true,
      didOpen: function (el) {
        el.addEventListener('mouseenter', window.Swal.stopTimer);
        el.addEventListener('mouseleave', window.Swal.resumeTimer);
      }
    });
  };
})();
