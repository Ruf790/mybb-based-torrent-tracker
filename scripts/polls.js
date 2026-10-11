/**
 * polls.js
 *
 * Handles new-poll form and edit-poll form.
 * Loaded on both pages — detects which form is present at init.
 *
 * Dependencies (loaded by the page before this file):
 *   - AGS_LANG    (const, printed by polls.php; optional — English fallback)
 *   - SortableJS  (window.Sortable)
 *   - SweetAlert2 (window.Swal)
 */

(function () {
  'use strict';

  // ─── i18n ───────────────────────────────────────────────────────────────────

  const LANG = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};

  /**
   * Translate key with English fallback; substitutes {1}, {2}… and %1$s, %2$s….
   */
  function t(key, fallback, ...args) {
    let s = (typeof LANG[key] === 'string') ? LANG[key] : fallback;
    args.forEach((arg, i) => {
      const n = i + 1;
      s = s.split('{' + n + '}').join(String(arg))
           .split('%' + n + '$s').join(String(arg));
    });
    return s;
  }

  // ─── Utilities ──────────────────────────────────────────────────────────────

  /** SweetAlert2 with plain-text title/body (shorthand Swal.fire(a, b) renders HTML). */
  function alertText(title, text, icon) {
    return Swal.fire({ titleText: title, text: text, icon: icon });
  }

  function $(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  function $$(sel, ctx) {
    return Array.from((ctx || document).querySelectorAll(sel));
  }

  // ─── Option builder ─────────────────────────────────────────────────────────

  /**
   * @param {string}  text       Raw option text (assigned via .value)
   * @param {number}  votes      Vote count (edit mode only)
   * @param {boolean} showVotes  Whether to show the votes input
   * @param {number}  num        Display number (#1, #2 …)
   */
  function buildOptionRow(text, votes, showVotes, num) {
    const div = document.createElement('div');
    div.className = 'input-group mb-2 option-item';

    const label = document.createElement('span');
    label.className = 'input-group-text';
    label.title = t('drag_hint', 'Drag to reorder');
    label.textContent = `#${num}`;
    div.appendChild(label);

    const input = document.createElement('input');
    input.type = 'text';
    input.name = `options[${num}]`;
    input.className = 'form-control option-input';
    input.required = true;
    input.value = text || '';
    input.setAttribute('aria-label', t('option_n', 'Option {1}', num));
    div.appendChild(input);

    if (showVotes) {
      const vi = document.createElement('input');
      vi.type = 'number';
      vi.name = `votes[${num}]`;
      vi.className = 'form-control votes-input';
      vi.style.maxWidth = '90px';
      vi.min = '0';
      vi.value = String(parseInt(votes, 10) || 0);
      vi.title = t('votes', 'Votes');
      vi.setAttribute('aria-label', t('votes', 'Votes'));
      div.appendChild(vi);
    }

    const btn = document.createElement('button');
    btn.className = 'btn btn-outline-danger btn-remove-option';
    btn.type = 'button';
    btn.setAttribute('aria-label', t('remove_option', 'Remove option'));
    btn.title = t('remove_option', 'Remove option');
    btn.textContent = '✕';
    div.appendChild(btn);

    return div;
  }

  // ─── PollForm class ─────────────────────────────────────────────────────────

  class PollForm {
    /**
     * @param {HTMLFormElement} form
     * @param {object} opts
     * @param {boolean}  opts.showVotes     Edit mode: show votes inputs
     * @param {object[]} opts.initialItems  [{text, votes}] for edit mode
     * @param {number}   opts.minOptions
     */
    constructor(form, opts = {}) {
      this.form        = form;
      this.showVotes   = opts.showVotes   || false;
      this.minOptions  = opts.minOptions  || 2;
      this.optionCount = 0;
      this.submitting  = false;

      this.list        = $('#optionsList', form);
      this.preview     = $('#previewBox', form);
      this.multiCheck  = $('#multiVote', form);
      this.maxBlock    = $('#maxOptionsBlock', form);
      this.addBtn      = $('#addOption', form);

      this._bindEvents();
      this._initOptions(opts.initialItems || []);
      this._initSortable();
    }

    // ── Setup ────────────────────────────────────────────────────────────────

    _bindEvents() {
      // Add option button
      if (this.addBtn) {
        this.addBtn.addEventListener('click', () => this.addOption());
      }

      // Remove option — delegated
      this.list.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-remove-option')) {
          const item = e.target.closest('.option-item');
          if (this._optionCount() <= this.minOptions) {
            alertText('', t('min_options', 'Minimum {1} options required', this.minOptions), 'warning');
            return;
          }
          item.remove();
          this._renumberOptions();
          this._updatePreview();
        }
      });

      // Live preview
      this.list.addEventListener('input', () => this._updatePreview());

      // Multiple-vote toggle
      if (this.multiCheck && this.maxBlock) {
        this.multiCheck.addEventListener('change', () => this._toggleMaxBlock());
        this._toggleMaxBlock();
      }

      // Form submit
      this.form.addEventListener('submit', (e) => {
        e.preventDefault();
        this._submit();
      });
    }

    _initOptions(items) {
      if (items.length > 0) {
        items.forEach((item) => this.addOption(item.text, item.votes));
      } else {
        // New form: start with N blank options (count comes from PHP via window.__POLL_COUNT__)
        const count = Math.max(2, window.__POLL_COUNT__ || 2);
        for (let i = 0; i < count; i++) this.addOption();
      }
    }

    _initSortable() {
      if (window.Sortable) {
        new Sortable(this.list, {
          animation: 150,
          handle: '.input-group-text',
          onEnd: () => {
            this._renumberOptions();
            this._updatePreview();
          },
        });
      }
    }

    // ── Options management ───────────────────────────────────────────────────

    addOption(text = '', votes = 0) {
      this.optionCount++;
      const row = buildOptionRow(
        text,
        votes,
        this.showVotes,
        this.optionCount
      );
      this.list.appendChild(row);
      this._updatePreview();
    }

    _optionCount() {
      return $$('.option-item', this.list).length;
    }

    /** Re-index #N labels and input names after drag-sort or removal. */
    _renumberOptions() {
      $$('.option-item', this.list).forEach((row, i) => {
        const num = i + 1;
        const label = row.querySelector('.input-group-text');
        if (label) label.textContent = `#${num}`;

        const textInput = row.querySelector('.option-input');
        if (textInput) {
          textInput.name = `options[${num}]`;
          textInput.setAttribute('aria-label', t('option_n', 'Option {1}', num));
        }

        const votesInput = row.querySelector('.votes-input');
        if (votesInput) votesInput.name = `votes[${num}]`;
      });
      this.optionCount = this._optionCount();
    }

    // ── UI helpers ───────────────────────────────────────────────────────────

    _toggleMaxBlock() {
      if (!this.maxBlock) return;
      this.maxBlock.style.display = this.multiCheck.checked ? 'block' : 'none';
    }

    _updatePreview() {
      if (!this.preview) return;
      const values = $$('.option-input', this.list)
        .map((el) => el.value.trim())
        .filter(Boolean);

      this.preview.replaceChildren();

      if (!values.length) {
        const empty = document.createElement('span');
        empty.className = 'text-muted';
        empty.textContent = t('no_options', 'No options yet');
        this.preview.appendChild(empty);
        return;
      }

      values.forEach((v) => {
        const line = document.createElement('div');
        line.textContent = `• ${v}`;
        this.preview.appendChild(line);
      });
    }

    // ── Submission ───────────────────────────────────────────────────────────

    _validate() {
      const question = $('[name=question]', this.form)?.value.trim();
      if (!question) {
        alertText(t('error', 'Error'), t('question_required', 'Please enter a question'), 'error');
        return false;
      }
      const valid = $$('.option-input', this.list).filter((el) => el.value.trim()).length;
      if (valid < this.minOptions) {
        alertText(t('error', 'Error'),
          t('at_least_options', 'At least {1} options are required', this.minOptions),
          'error'
        );
        return false;
      }
      return true;
    }

    _submit() {
      if (this.submitting) return;
      if (!this._validate()) return;

      this.submitting = true;

      fetch('polls.php', {
        method: 'POST',
        body: new FormData(this.form),
        credentials: 'same-origin',
      })
        .then((res) => {
          // PHP issued a redirect (non-AJAX fallback path)
          if (res.redirected) {
            window.location.href = res.url;
            return null;
          }
          return res.json();
        })
        .then((data) => {
          if (!data) return;

          if (data.status === 'success') {
            Swal.fire({
              icon: 'success',
              titleText: t('done', 'Done!'),
              text: data.message || t('saved', 'Saved successfully'),
              timer: 1500,
              showConfirmButton: false,
            }).then(() => {
              window.location.href = data.redirect;
            });
          } else {
            alertText(t('error', 'Error'), data.message || t('something_wrong', 'Something went wrong'), 'error');
            this.submitting = false;
          }
        })
        .catch(() => {
          alertText(t('error', 'Error'), t('server_unavailable', 'Server unavailable'), 'error');
          this.submitting = false;
        });
    }
  }

  // ─── Bootstrap ──────────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('pollForm');
    if (!form) return;

    const isEdit = form.querySelector('[name=action]')?.value === 'do_editpoll';

    new PollForm(form, {
      showVotes:    isEdit,
      initialItems: isEdit ? (window.__POLL_INITIAL__ || []) : [],
    });
  });
})();
