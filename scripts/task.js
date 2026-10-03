(function () {
  'use strict';

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('form[data-tk-claim]') || form.dataset.tkOk === '1') return;
    e.preventDefault();

    var name = form.dataset.tkClaim;
    var penalty = parseInt(form.dataset.tkPenalty || '0', 10);
    var text = 'Claim the task "' + name + '"?' +
      (penalty > 0 ? ' If you do not complete it in time, ' + penalty + ' bonus points will be deducted.' : '');

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
      confirmButtonText: 'Claim',
      cancelButtonText: 'Cancel',
      reverseButtons: true
    }).then(function (r) { if (r.isConfirmed) go(); });
  });
})();