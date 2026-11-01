/* =============================================================================
   details.js — torrent page (details.php)
   AGS_LANG is printed by the page before the scripts (js_* keys without the
   prefix). Missing dictionary/key -> English fallback. {1} and %1$s are both
   substituted. Translations are inserted as text, not HTML.
   ============================================================================= */
function t(key, fallback, ...args) {
    const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : null;
    const str  = (dict && typeof dict[key] === 'string') ? dict[key] : fallback;
    return String(str).replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
        const i = parseInt(a || b, 10) - 1;
        return (i >= 0 && i < args.length) ? String(args[i]) : m;
    });
}

// Replaces the element content with <tag class="iconClass"></tag> + text node.
function agsIconText(el, iconClass, text, tag) {
    el.textContent = '';
    const icon = document.createElement(tag || 'i');
    icon.className = iconClass;
    el.appendChild(icon);
    el.appendChild(document.createTextNode(text));
}

// HTML-escape for the few places where a string has to go into an HTML template.
function agsEsc(str) {
    return String(str ?? '').replace(/[&<>"']/g, function (ch) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
}

function dtToast(msg, type) {
    if (typeof showToast === 'function') {
        showToast(msg, type);
    } else {
        window.alert(msg);
    }
}

/* Confirm via SweetAlert2, plain confirm() if it is not loaded.
   opts: { title, text, html, yes, no, danger } -> Promise<boolean> */
function dtConfirm(opts) {
    if (window.Swal) {
        return window.Swal.fire({
            icon: opts.danger ? 'warning' : 'question',
            title: opts.title || '',
            text: opts.html ? undefined : (opts.text || ''),
            html: opts.html || undefined,
            showCancelButton: true,
            confirmButtonText: opts.yes || 'OK',
            cancelButtonText: opts.no || 'Cancel',
            confirmButtonColor: opts.danger ? '#dc3545' : undefined,
            reverseButtons: true,
            focusCancel: !!opts.danger
        }).then(function (r) { return r.isConfirmed; });
    }
    const plain = [opts.title, opts.text].filter(Boolean).join('\n\n');
    return Promise.resolve(window.confirm(plain));
}

function dtPostKey() {
    const page = document.getElementById('torrent_details');
    return (page && page.dataset.postKey)
        || document.querySelector('input[name="my_post_key"]')?.value
        || '';
}

/* ── Tabs: open a tab by pane id (links like #peers, ?tab=peers) ────────── */
function dtShowTab(key, scroll) {
    const btn = document.getElementById(key + '-tab');
    if (!btn || typeof bootstrap === 'undefined') {
        return false;
    }
    bootstrap.Tab.getOrCreateInstance(btn).show();
    if (scroll) {
        btn.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const tab    = params.get('tab');
    if (tab && tab !== 'comments' && document.getElementById(tab + '-tab')) {
        dtShowTab(tab, false);
        const hash = window.location.hash.replace('#', '');
        if (hash) {
            document.getElementById(tab + '-tab').addEventListener('shown.bs.tab', function () {
                document.getElementById(hash)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, { once: true });
        }
    }

    // In-page links to a tab pane (KPI tiles, .scroll-to-tab)
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href^="#"], .scroll-to-tab');
        if (!link) {
            return;
        }
        const key = link.dataset.scrollTab || (link.getAttribute('href') || '').slice(1);
        if (!key) {
            return;
        }
        const pane = document.getElementById(key);
        if (pane && pane.classList.contains('tab-pane')) {
            e.preventDefault();
            dtShowTab(key, true);
        }
    });

    // Health bar: grow from 0 on load (only this page's bar)
    document.querySelectorAll('.dt-health__bar .progress-bar').forEach(function (bar) {
        const width = bar.style.width;
        bar.style.width = '0%';
        requestAnimationFrame(function () {
            setTimeout(function () { bar.style.width = width; }, 150);
        });
    });

    // Broken similar-torrent posters -> placeholder
    document.querySelectorAll('.dt-sim__poster img[data-fallback]').forEach(function (img) {
        img.addEventListener('error', function onErr() {
            img.removeEventListener('error', onErr);
            img.src = img.dataset.fallback;
        });
    });

    // Rating widget (rating.js)
    const rating = document.getElementById('dt-rating');
    if (rating && typeof ratingInit === 'function') {
        ratingInit(parseInt(rating.dataset.rating, 10) || 0, parseInt(rating.dataset.torrent, 10) || 0,
            typeof baseurl !== 'undefined' ? baseurl : '', dtPostKey());
    }
});

/* ── Dismiss buttons (comment not found) ────────────────────────────────── */
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-dt-dismiss]');
    if (btn) {
        document.querySelector(btn.dataset.dtDismiss)?.remove();
    }
});

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('comment-not-found')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
});

/* ── Copy (NFO) ─────────────────────────────────────────────────────────── */
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-dt-copy]');
    if (!btn) {
        return;
    }
    const src = document.querySelector(btn.dataset.dtCopy);
    if (!src || !navigator.clipboard) {
        dtToast(t('dt_copy_failed', 'Copy failed'), 'danger');
        return;
    }
    navigator.clipboard.writeText(src.textContent).then(function () {
        dtToast(t('dt_nfo_copied', 'NFO copied to clipboard!'), 'success');
    }).catch(function () {
        dtToast(t('dt_copy_failed', 'Copy failed'), 'danger');
    });
});

// Kept for old markup / other callers
function copyNfo() {
    document.querySelector('[data-dt-copy="#nfoText"]')?.click();
}

/* ── File tree: expand / collapse all ───────────────────────────────────── */
function dtTreeAll(show) {
    if (typeof bootstrap === 'undefined') {
        return;
    }
    document.querySelectorAll('.file-tree .collapse').forEach(function (el) {
        const inst = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
        if (show) {
            inst.show();
        } else {
            inst.hide();
        }
    });
}
function expandAllFiles()   { dtTreeAll(true); }
function collapseAllFiles() { dtTreeAll(false); }

document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-dt-tree]');
    if (btn) {
        dtTreeAll(btn.dataset.dtTree === 'expand');
    }
});

/* ── Confirmed forms (claim, reseed): data-dt-confirm ───────────────────── */
document.addEventListener('submit', function (e) {
    const f = e.target;
    if (!f.matches || !f.matches('form[data-dt-confirm]') || f.dataset.ok === '1') {
        return;
    }
    e.preventDefault();
    dtConfirm({ text: f.dataset.dtConfirm, yes: f.dataset.dtYes, no: f.dataset.dtNo }).then(function (ok) {
        if (!ok) {
            return;
        }
        f.dataset.ok = '1';
        const b = f.querySelector('button[type="submit"], button:not([type])');
        if (b) {
            b.disabled = true;
        }
        f.submit();
    });
});

/* ── Delete torrent (owner / staff) ─────────────────────────────────────── */
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.dt-delete-torrent');
    if (!btn) {
        return;
    }
    e.preventDefault();

    const name = btn.dataset.name || '';
    dtConfirm({
        danger: true,
        title: btn.dataset.title,
        text: name + '\n\n' + (btn.dataset.warn || ''),
        html: '<p class="fw-semibold mb-2">' + agsEsc(name) + '</p>'
            + '<p class="small text-body-secondary mb-0">' + agsEsc(btn.dataset.warn || '') + '</p>',
        yes: btn.dataset.yes,
        no: btn.dataset.no
    }).then(function (ok) {
        if (!ok) {
            return;
        }
        btn.disabled = true;
        agsIconText(btn, 'fa-solid fa-hourglass-half me-2', t('dt_deleting', 'Deleting...'));

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete.php';
        const fields = {
            id: btn.dataset.torrentId,
            reasontype: '5',              // "Other"
            'reason[3]': 'Deleted from the torrent page',
            my_post_key: dtPostKey()
        };
        Object.keys(fields).forEach(function (k) {
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = k;
            inp.value = fields[k];
            form.appendChild(inp);
        });
        document.body.appendChild(form);
        form.submit();
    });
});

/* ── Magnet link modal ──────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl     = document.getElementById('magnetModal');
    const magnetInput = document.getElementById('magnetInput');
    const copyBtn     = document.getElementById('copyMagnetBtn');
    const copySuccess = document.getElementById('copySuccess');
    const openBtn     = document.getElementById('openMagnetBtn');

    if (!modalEl || !magnetInput || !copyBtn || !openBtn || typeof bootstrap === 'undefined') {
        return;
    }
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    window.updateMagnetLink = function (url) {
        magnetInput.value = url;
    };

    openBtn.addEventListener('click', function () {
        const url = magnetInput.value;
        if (!url.startsWith('magnet:?')) {
            dtToast(t('dt_magnet_invalid', 'Invalid magnet link'), 'error');
            return;
        }
        window.location.href = url;
        dtToast(t('dt_magnet_opening', 'Opening torrent client...'), 'info');
        modal.hide();
    });

    copyBtn.addEventListener('click', function () {
        if (!navigator.clipboard) {
            magnetInput.select();
            dtToast(t('dt_clipboard_failed', 'Could not copy to clipboard'), 'error');
            return;
        }
        navigator.clipboard.writeText(magnetInput.value).then(function () {
            copySuccess?.classList.remove('d-none');
            const original = copyBtn.innerHTML;
            agsIconText(copyBtn, 'fa-solid fa-check me-1', t('dt_copied', 'Copied!'));
            copyBtn.classList.replace('btn-primary', 'btn-success');
            setTimeout(function () {
                copySuccess?.classList.add('d-none');
                copyBtn.innerHTML = original;
                copyBtn.classList.replace('btn-success', 'btn-primary');
            }, 2000);
        }).catch(function () {
            dtToast(t('dt_clipboard_failed', 'Could not copy to clipboard'), 'error');
        });
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.magnet-btn');
        if (!btn) {
            return;
        }
        e.preventDefault();
        btn.disabled = true;

        fetch('download.php?type=magnet&id=' + encodeURIComponent(btn.dataset.magnetId), { credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (magnet) {
                magnet = magnet.trim();
                if (!magnet.startsWith('magnet:?')) {
                    dtToast(t('dt_magnet_error', 'Magnet link error'), 'error');
                    return;
                }
                magnetInput.value = magnet;
                modal.show();
            })
            .catch(function () {
                dtToast(t('dt_magnet_load_failed', 'Failed to load magnet link'), 'error');
            })
            .finally(function () {
                btn.disabled = false;
            });
    });
});

/* ── IMDb refresh (staff) ───────────────────────────────────────────────── */
function TS_IMDB(torrentId) {
    const btn  = document.getElementById('imdbupdatebutton');
    const body = document.getElementById('imdbdetails');
    if (!btn || !body) {
        return;
    }

    const original = btn.innerHTML;
    btn.disabled = true;
    agsIconText(btn, 'fa-solid fa-circle-notch fa-spin me-1', t('dt_please_wait', 'Please Wait...'));

    fetch(baseurl + '/ajax_imdb.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'tid=' + encodeURIComponent(String(parseInt(torrentId, 10) || 0))
    })
        .then(function (r) {
            if (!r.ok) {
                throw new Error('HTTP ' + r.status);
            }
            return r.text();
        })
        .then(function (response) {
            const err = response.match(/<error>([\s\S]*?)<\/error>/);
            if (err) {
                dtToast(t('dt_update_error', 'Update error: {1}', err[1] || t('dt_error_generic', 'An error occurred')), 'error');
                btn.innerHTML = original;
                return;
            }
            body.innerHTML = response;
            body.classList.remove('dt-imdb__flash');
            void body.offsetWidth;
            body.classList.add('dt-imdb__flash');
            agsIconText(btn, 'fa-solid fa-check me-1', t('dt_updated', 'Updated'));
        })
        .catch(function () {
            dtToast(t('dt_ajax_error', 'AJAX error occurred'), 'error');
            btn.innerHTML = original;
        })
        .finally(function () {
            btn.disabled = false;
        });
}

document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-imdb-refresh]');
    if (btn) {
        TS_IMDB(btn.dataset.imdbRefresh);
    }
});

/* ── First AJAX comment: drop the "no comments yet" card ────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    const empty = document.getElementById('dt-comments-empty');
    const box   = document.getElementById('ajax_comment_preview');
    if (!empty || !box || typeof MutationObserver === 'undefined') {
        return;
    }
    const mo = new MutationObserver(function () {
        if (box.children.length) {
            empty.remove();
            mo.disconnect();
        }
    });
    mo.observe(box, { childList: true });
});
