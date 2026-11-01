/**
 * member.js — профиль пользователя (member.php?action=profile)
 *
 *  - вкладки-пилюли: открывают вкладку из #hash и запоминают выбранную в адресе;
 *  - постеры торрентов: при ошибке загрузки показываем иконку категории;
 *  - кнопка «Пожаловаться»: data-mr-report-uid / data-mr-report-name
 *    → openReportUserModal() из report_user.js (без inline onclick).
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    // Постеры: ошибка загрузки не всплывает, поэтому ловим на фазе захвата
    document.addEventListener('error', function (e) {
        const img = e.target;
        if (!(img instanceof HTMLImageElement) || !img.matches('.mr-page .mr-tor__poster img')) return;
        const box = img.parentElement;
        const fallback = box ? box.getAttribute('data-fallback-icon') : '';
        img.remove();
        if (box && fallback) {
            const i = document.createElement('i');
            i.className = fallback;
            i.setAttribute('aria-hidden', 'true');
            box.appendChild(i);
        }
    }, true);

    ready(function () {
        const page = document.querySelector('.mr-page');
        if (!page) return;

        // ── Вкладки + #hash ──────────────────────────────────────────────
        const tabs = page.querySelectorAll('.mr-tabs [data-bs-toggle="pill"]');
        if (tabs.length) {
            const byHash = function (hash) {
                if (!hash || hash.length < 2) return null;
                return page.querySelector('.mr-tabs [data-bs-target="#mr-tab-' + CSS.escape(hash.slice(1)) + '"]');
            };
            const show = function (btn) {
                if (!btn) return;
                if (window.bootstrap && window.bootstrap.Tab) {
                    window.bootstrap.Tab.getOrCreateInstance(btn).show();
                }
                btn.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            };

            show(byHash(window.location.hash));

            tabs.forEach(function (btn) {
                btn.addEventListener('shown.bs.tab', function () {
                    const id = (btn.getAttribute('data-bs-target') || '').replace('#mr-tab-', '');
                    if (id && window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', '#' + id);
                    }
                });
            });

            window.addEventListener('hashchange', function () { show(byHash(window.location.hash)); });
        }

        // ── Жалоба на пользователя ──────────────────────────────────────
        page.querySelectorAll('[data-mr-report-uid]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (typeof window.openReportUserModal !== 'function') return;
                window.openReportUserModal(
                    parseInt(btn.getAttribute('data-mr-report-uid'), 10) || 0,
                    btn.getAttribute('data-mr-report-name') || ''
                );
            });
        });

        // ── Аватар: доступность с клавиатуры ────────────────────────────
        const ava = document.getElementById('avatar-container');
        if (ava && ava.dataset.canChange === '1') {
            ava.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    ava.click();
                }
            });
        }
    });
})();
