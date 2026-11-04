/* showteam.js — фильтр групп команды (showteam.php)
   Вкладки-«пилюли»: «Все» показывает все секции, вкладка группы — только её.
   Выбор хранится в адресе (#tm-group-<gid>), так что ссылку можно отправить. */
(function () {
    'use strict';

    function init() {
        const page = document.querySelector('.tm-page');
        if (!page) return;

        const tabs     = page.querySelectorAll('.tm-tab');
        const sections = page.querySelectorAll('.tm-group');
        if (!tabs.length) return;

        function show(group, updateHash) {
            let found = false;
            tabs.forEach(function (tab) {
                const on = tab.dataset.group === group;
                found = found || on;
                tab.classList.toggle('active', on);
                if (on) {
                    tab.setAttribute('aria-current', 'true');
                    tab.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                } else {
                    tab.removeAttribute('aria-current');
                }
            });
            if (!found) return show('all', updateHash);

            sections.forEach(function (sec) {
                sec.hidden = group !== 'all' && sec.dataset.group !== group;
            });

            if (updateHash) {
                const url = group === 'all'
                    ? location.pathname + location.search
                    : '#tm-group-' + group;
                history.replaceState(null, '', url);
            }
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (ev) {
                ev.preventDefault();
                show(tab.dataset.group, true);
            });
        });

        const m = /^#tm-group-(\d+)$/.exec(location.hash);
        show(m ? m[1] : 'all', false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
