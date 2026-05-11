/**
 * showthread.js
 *
 * Handles:
 *  - Moderation panel (delete thread, merge, move, delete posts)
 *  - Inline post moderation
 *  - Move thread method selector
 *  - Poll bar animation
 *  - Quick reply spinner
 *  - Popover init
 */

(function () {
  'use strict';

  // ─── Utilities ──────────────────────────────────────────────────────────────

  function el(id) {
    return document.getElementById(id);
  }

  function qs(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  function qsa(sel, ctx) {
    return Array.from((ctx || document).querySelectorAll(sel));
  }

  function makeHidden(fields) {
    var form = document.createElement('form');
    form.method = 'post';
    form.action = 'moderation.php';
    form.style.display = 'none';
    Object.entries(fields).forEach(function (_ref) {
      var k = _ref[0], v = _ref[1];
      var inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = k;
      inp.value = v;
      form.appendChild(inp);
    });
    return form;
  }

  function getModal(id) {
    var el = document.getElementById(id);
    if (!el || typeof bootstrap === 'undefined') return null;
    return new bootstrap.Modal(el);
  }

  // ─── Poll bars: animate on load ─────────────────────────────────────────────

  function initPollBars() {
    qsa('.poll-bar').forEach(function (bar) {
      var target = bar.style.width;
      bar.style.width = '0%';
      // Trigger reflow then animate
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          bar.style.width = target;
        });
      });
    });
  }

  // ─── Quick reply spinner ─────────────────────────────────────────────────────

  function initQuickReplySpinner() {
    var form    = el('quick_reply_form');
    var spinner = el('quickreply_spinner');
    if (!form || !spinner) return;

    form.addEventListener('submit', function () {
      spinner.style.display = 'block';
    });
  }

  // ─── Bootstrap popovers ─────────────────────────────────────────────────────

  function initPopovers() {
    qsa('[data-bs-toggle="popover"]').forEach(function (node) {
      if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
        new bootstrap.Popover(node);
      }
    });
  }

  // ─── Move thread method selector ────────────────────────────────────────────

  window.mtSelectMethod = function (method, clickedEl) {
    qsa('.mt-method-option').forEach(function (opt) {
      opt.classList.remove('selected');
      var inner = qs('.mt-radio-inner', opt);
      if (inner) inner.style.display = 'none';
      var radio = qs('input[type=radio]', opt);
      if (radio) radio.checked = false;
    });

    clickedEl.classList.add('selected');
    var inner = qs('.mt-radio-inner', clickedEl);
    if (inner) inner.style.display = 'block';
    var radio = qs('input[type=radio]', clickedEl);
    if (radio) radio.checked = true;
  };

  // ─── Build delete-posts preview ─────────────────────────────────────────────

  function buildDeletePostsPreview(postIds) {
    var container = el('modal_posts_preview');
    if (!container) return;
    container.innerHTML = '';

    postIds.forEach(function (pid) {
      var card    = document.createElement('div');
      card.className = 'card mb-2 border-danger border-opacity-25';

      var postEl  = el('post_' + pid);
      if (postEl) {
        var authorEl   = qs('.card-title', postEl);
        var authorText = authorEl ? authorEl.innerText.trim() : 'Unknown';

        var contentEl = el('pid_' + pid);
        var tempDiv   = document.createElement('div');
        tempDiv.innerHTML = contentEl ? contentEl.innerHTML : '';

        // Strip nested quotes and signatures from preview
        qsa('.post_body blockquote, .signature', tempDiv).forEach(function (n) { n.remove(); });

        var plain = tempDiv.innerText || tempDiv.textContent || '';
        var previewHTML = plain.length > 400
          ? '<div style="max-height:120px;overflow:hidden;position:relative;">' +
              tempDiv.innerHTML +
              '<div style="position:absolute;bottom:0;left:0;right:0;height:40px;background:linear-gradient(transparent,white);"></div>' +
            '</div>'
          : tempDiv.innerHTML;

        card.innerHTML =
          '<div class="card-header py-2 px-3 bg-danger bg-opacity-10 d-flex justify-content-between align-items-center">' +
            '<span class="small fw-bold text-danger"><i class="fas fa-user me-1"></i>' + authorText + '</span>' +
            '<span class="badge bg-secondary small">PID: ' + pid + '</span>' +
          '</div>' +
          '<div class="card-body py-2 px-3 small">' + previewHTML + '</div>';
      } else {
        card.innerHTML =
          '<div class="card-body py-2 px-3">' +
            '<p class="small text-muted mb-0"><i class="fas fa-question-circle me-1"></i>Post ID: ' + pid + '</p>' +
          '</div>';
      }

      container.appendChild(card);
    });
  }

  // ─── Inline post moderation ──────────────────────────────────────────────────

  function initInlineMod() {
    var selector    = el('inlinemoderation_options_selector');
    var form        = el('inlinemoderation_options');
    var confirmBtn  = el('confirmDeleteBtn');
    var deleteModal = getModal('deletePostsModal');

    if (!selector || !form) return;

    form.addEventListener('submit', function (e) {
      if (selector.value !== 'multideleteposts') return; // let normal submit through

      e.preventDefault();

      var selected = qsa('input[name^="inlinemod_"]:checked');
      if (selected.length === 0) {
        alert('Please select at least one post.');
        return;
      }

      var postIds = selected.map(function (inp) {
        return inp.name.replace('inlinemod_', '');
      });

      var countEl = el('modal_post_count');
      if (countEl) countEl.textContent = postIds.length;

      buildDeletePostsPreview(postIds);

      // Build hidden submit form
      var old = el('deletePostsHiddenForm');
      if (old) old.remove();

      var postKey = qs('#inlinemoderation_options input[name="my_post_key"]');
      var tidInput = qs('#inlinemoderation_options input[name="tid"]');

      var hiddenForm = makeHidden({
        my_post_key: postKey ? postKey.value : '',
        tid:         tidInput ? tidInput.value : '',
        modtype:     'inlinepost',
        action:      'do_multideleteposts',
        posts:       postIds.join(','),
        url:         window.location.href,
      });
      hiddenForm.id = 'deletePostsHiddenForm';
      document.body.appendChild(hiddenForm);

      if (confirmBtn) {
        confirmBtn.onclick = function () {
          hiddenForm.submit();
          if (deleteModal) deleteModal.hide();
        };
      }

      if (deleteModal) deleteModal.show();
    });
  }

  // ─── Thread moderation panel ─────────────────────────────────────────────────

  function initThreadMod() {
    var selector    = el('moderator_options_selector');
    var form        = el('moderator_options');
    var confirm1    = el('dt_confirm1');
    var confirm2    = el('dt_confirm2');
    var confirmBtn  = el('confirmDeleteThreadBtn');
    var deleteModal = getModal('deleteThreadModal');
    var mergeModal  = getModal('mergeThreadModal');
    var moveModal   = getModal('moveThreadModal');

    if (!selector || !form) return;

    // Reset delete-thread modal state when closed
    var deleteModalEl = el('deleteThreadModal');
    if (deleteModalEl) {
      deleteModalEl.addEventListener('hidden.bs.modal', function () {
        if (confirm1) confirm1.checked = false;
        if (confirm2) confirm2.checked = false;
        if (confirmBtn) {
          confirmBtn.disabled  = true;
          confirmBtn.innerHTML = '<i class="fas fa-trash-alt me-2"></i>Delete Permanently';
        }
      });
    }

    // Reset merge URL on close
    var mergeModalEl = el('mergeThreadModal');
    if (mergeModalEl) {
      mergeModalEl.addEventListener('hidden.bs.modal', function () {
        var urlInput = qs('#mergeThreadForm input[name="threadurl"]');
        if (urlInput) urlInput.value = '';
      });
    }

    // Confirm checkboxes enable button
    function checkConfirm() {
      if (!confirmBtn) return;
      confirmBtn.disabled = !(confirm1 && confirm1.checked && confirm2 && confirm2.checked);
    }
    if (confirm1) confirm1.addEventListener('change', checkConfirm);
    if (confirm2) confirm2.addEventListener('change', checkConfirm);

    form.addEventListener('submit', function (e) {
      var action = selector.value;

      if (action === 'deletethread') {
        e.preventDefault();

        var old = el('deleteThreadHiddenForm');
        if (old) old.remove();

        var postKey = qs('#moderator_options input[name="my_post_key"]');
        var tidInput = qs('#moderator_options input[name="tid"]');

        var hiddenForm = makeHidden({
          my_post_key: postKey ? postKey.value : '',
          modtype:     'thread',
          tid:         tidInput ? tidInput.value : '',
          action:      'do_deletethread',
        });
        hiddenForm.id = 'deleteThreadHiddenForm';
        document.body.appendChild(hiddenForm);

        if (confirmBtn) {
          confirmBtn.onclick = function () {
            if (!confirm1.checked || !confirm2.checked) return;
            confirmBtn.disabled  = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
            hiddenForm.submit();
            if (deleteModal) deleteModal.hide();
          };
        }

        if (deleteModal) deleteModal.show();
        return;
      }

      if (action === 'merge') {
        e.preventDefault();
        if (mergeModal) mergeModal.show();
        return;
      }

      if (action === 'move') {
        e.preventDefault();
        if (moveModal) moveModal.show();
        return;
      }

      // All other actions submit normally
    });

    // Auto-submit on select change (mirrors original behaviour)
    selector.addEventListener('change', function () {
      form.dispatchEvent(new Event('submit'));
    });
  }

  // ─── Thread deleted state ───────────────────────────────────────────────────

  function applyDeletedState() {
    if (typeof thread_deleted === 'undefined' || thread_deleted !== '1') return;

    var qrf = el('quick_reply_form');
    if (qrf) qrf.style.display = 'none';

    qsa('#moderator_options_selector option.option_mirage').forEach(function (o) {
      o.disabled = true;
    });
  }

  // ─── Boot ────────────────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', function () {
    initPollBars();
    initQuickReplySpinner();
    initPopovers();
    initInlineMod();
    initThreadMod();
    applyDeletedState();
  });

})();
