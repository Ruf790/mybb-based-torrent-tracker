/**
 * polls.js
 *
 * Handles new-poll form and edit-poll form.
 * Loaded on both pages — detects which form is present at init.
 *
 * Dependencies (loaded by the page before this file):
 *   - SortableJS  (window.Sortable)
 *   - SweetAlert2 (window.Swal)
 */

(function () {
  'use strict';

  // ─── Utilities ──────────────────────────────────────────────────────────────

  function escHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function $(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  function $$(sel, ctx) {
    return Array.from((ctx || document).querySelectorAll(sel));
  }

  // ─── Option builder ─────────────────────────────────────────────────────────

  /**
   * @param {string}  text       Pre-escaped option text
   * @param {number}  votes      Vote count (edit mode only)
   * @param {boolean} showVotes  Whether to show the votes input
   * @param {number}  num        Display number (#1, #2 …)
   */
  function buildOptionRow(text, votes, showVotes, num) {
    const div = document.createElement('div');
    div.className = 'input-group mb-2 option-item';

    const votesInput = showVotes
      ? `<input type="number" name="votes[${num}]" class="form-control votes-input"
               style="max-width:90px" value="${votes}" min="0">`
      : '';

    div.innerHTML = `
      <span class="input-group-text">#${num}</span>
      <input type="text" name="options[${num}]" class="form-control option-input"
             value="${text}" required>
      ${votesInput}
      <button class="btn btn-outline-danger btn-remove-option" type="button"
              aria-label="Remove option">✕</button>
    `;
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
            Swal.fire('', `Minimum ${this.minOptions} options required`, 'warning');
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
        escHtml(text),
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
        if (textInput) textInput.name = `options[${num}]`;

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
      const lines = $$('.option-input', this.list)
        .map((el) => el.value.trim())
        .filter(Boolean)
        .map((v) => `<div>• ${escHtml(v)}</div>`)
        .join('');
      this.preview.innerHTML = lines || '<span class="text-muted">No options yet</span>';
    }

    // ── Submission ───────────────────────────────────────────────────────────

    _validate() {
      const question = $('[name=question]', this.form)?.value.trim();
      if (!question) {
        Swal.fire('Error', 'Please enter a question', 'error');
        return false;
      }
      const valid = $$('.option-input', this.list).filter((el) => el.value.trim()).length;
      if (valid < this.minOptions) {
        Swal.fire('Error', `At least ${this.minOptions} options are required`, 'error');
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
              title: 'Done!',
              text: data.message || 'Saved successfully',
              timer: 1500,
              showConfirmButton: false,
            }).then(() => {
              window.location.href = data.redirect;
            });
          } else {
            Swal.fire('Error', data.message || 'Something went wrong', 'error');
            this.submitting = false;
          }
        })
        .catch(() => {
          Swal.fire('Error', 'Server unavailable', 'error');
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