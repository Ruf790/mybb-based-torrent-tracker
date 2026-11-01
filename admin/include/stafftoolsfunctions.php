<?php

function _access_check_ ()
{
    global $usergroups;
    if ($usergroups['cansettingspanel'] != 1)
    {
      print_no_permission (true);
      exit ();
      return null;
    }

}



/**
 * Стили панели в новом стиле: общий st-ui.css + admin/templates/staffpanel.css.
 * Один раз за запрос (menu() вызывается на всех экранах index.php).
 */
function sp_assets(): void
{
    global $BASEURL;
    static $done = false;
    if ($done) return;
    $done = true;
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=7">' . "\n";
    echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/staffpanel.css?ver=1">' . "\n";
}

function menu($selected = '') {
    global $usergroups, $_this_script_no_act, $lang;

    sp_assets();

    $L    = $lang->staffpanel;
    $base = $_this_script_no_act;
    $do   = $_GET['do'] ?? '';

    // [ключ, ссылка, иконка, ключ ланга, только для cansettingspanel]
    $items = [
        ['welcome',          $base,                                  'fa-house',         'menu_welcome',    false],
        ['stafftools',       $base . '?act=stafftools',              'fa-screwdriver-wrench', 'menu_stafftools', false],
        ['managestafftools', $base . '?act=managestafftools',        'fa-wrench',        'menu_manage',     true],
        ['newtool',          $base . '?act=managestafftools&amp;do=newtool', 'fa-square-plus', 'menu_newtool', true],
        ['securitycheck',    $base . '?act=securitycheck',           'fa-shield-halved', 'menu_security',   true],
        ['settings',         'managesettings.php',                   'fa-gears',         'menu_settings',   true],
    ];
    $is_admin = (int) ($usergroups['cansettingspanel'] ?? 0) === 1;

    $links = '';
    foreach ($items as [$key, $href, $icon, $lkey, $admin_only]) {
        if ($admin_only && !$is_admin) continue;
        $active = $key === 'newtool'
            ? $do === 'newtool'
            : ($selected === $key && !($key === 'managestafftools' && $do === 'newtool'));
        $links .= '<li><a class="sp-nav__link' . ($active ? ' active' : '') . '" href="' . $href . '"'
                . ($active ? ' aria-current="page"' : '') . '>'
                . '<i class="fa-solid ' . $icon . '"></i><span class="sp-nav__label">' . htmlspecialchars($L[$lkey]) . '</span></a></li>';
    }

    echo '<div class="container-md st-page sp-page sp-page--nav">'
       .   '<nav class="st-card sp-nav" aria-label="' . htmlspecialchars($L['menu_title']) . '">'
       .     '<div class="sp-nav__brand">'
       .       '<span class="st-ico st-soft-primary"><i class="fa-solid fa-gauge-high"></i></span>'
       .       '<span class="sp-nav__title">' . htmlspecialchars($L['menu_title']) . '</span>'
       .       '<span class="sp-nav__status"><span class="sp-nav__dot"></span>' . htmlspecialchars($L['menu_online']) . '</span>'
       .     '</div>'
       .     '<ul class="sp-nav__list">' . $links . '</ul>'
       .   '</nav>'
       . '</div>';
}




function get_list()
{
    global $thispath, $_this_script_no_act, $CURUSER, $db, $BASEURL, $lang;

    $L   = $lang->staffpanel;
    $e   = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $gid = (int) $CURUSER['usergroup'];

    $query = $db->sql_query_prepared(
        'SELECT * FROM staffpanel WHERE usergroups LIKE ? ORDER BY name',
        ['%[' . $gid . ']%']
    );

    // Внутренний ключ категории (data-category, фильтр) — английский, подпись — из ланга.
    $cat_labels = [
        'Users'         => $L['cat_users'],
        'Moderation'    => $L['cat_moderation'],
        'Torrents'      => $L['cat_torrents'],
        'Forums'        => $L['cat_forums'],
        'Content'       => $L['cat_content'],
        'System'        => $L['cat_system'],
        'Statistics'    => $L['cat_statistics'],
        'Communication' => $L['cat_communication'],
        'Maintenance'   => $L['cat_maintenance'],
        'Tools'         => $L['cat_tools'],
    ];

    // Цвет → soft-класс (st-ui.css + фиолетовый из staffpanel.css)
    $soft = [
        'blue' => 'st-soft-primary', 'green' => 'st-soft-success', 'red' => 'st-soft-danger',
        'orange' => 'st-soft-warning', 'cyan' => 'st-soft-info',
        'purple' => 'sp-soft-purple', 'indigo' => 'sp-soft-purple', 'pink' => 'sp-soft-purple',
    ];

    $tool_icons = [
        'user'      => ['fa-users',            'green',  'Users'],
        'users'     => ['fa-users',            'green',  'Users'],
        'profile'   => ['fa-circle-user',      'blue',   'Users'],
        'ban'       => ['fa-gavel',            'red',    'Moderation'],
        'block'     => ['fa-ban',              'red',    'Moderation'],
        'torrent'   => ['fa-magnet',           'purple', 'Torrents'],
        'upload'    => ['fa-cloud-arrow-up',   'purple', 'Torrents'],
        'download'  => ['fa-download',         'purple', 'Torrents'],
        'forum'     => ['fa-comments',         'blue',   'Forums'],
        'topic'     => ['fa-comment-dots',     'blue',   'Forums'],
        'post'      => ['fa-reply',            'blue',   'Forums'],
        'news'      => ['fa-newspaper',        'cyan',   'Content'],
        'announce'  => ['fa-bullhorn',         'orange', 'Content'],
        'backup'    => ['fa-database',         'indigo', 'System'],
        'log'       => ['fa-clock-rotate-left','purple', 'System'],
        'logs'      => ['fa-clock-rotate-left','purple', 'System'],
        'config'    => ['fa-gear',             'blue',   'System'],
        'setting'   => ['fa-sliders',          'blue',   'System'],
        'report'    => ['fa-flag',             'red',    'Moderation'],
        'reports'   => ['fa-flag',             'red',    'Moderation'],
        'statistic' => ['fa-chart-line',       'green',  'Statistics'],
        'stats'     => ['fa-chart-column',     'green',  'Statistics'],
        'mail'      => ['fa-envelope',         'purple', 'Communication'],
        'message'   => ['fa-envelope',         'purple', 'Communication'],
        'clean'     => ['fa-broom',            'orange', 'Maintenance'],
        'maintain'  => ['fa-wrench',           'orange', 'Maintenance'],
    ];
    $default_icon = ['fa-toolbox', 'blue', 'Tools'];

    $count      = 0;
    $categories = [];
    $tools_html = '';
    $needle     = '[' . $gid . ']';

    while ($query && ($tool = $db->fetch_array($query))) {
        if (!in_array($needle, explode(',', (string) $tool['usergroups']), true)
            || !@file_exists($thispath . $tool['filename'])) {
            continue;
        }

        [$icon, $color, $category] = $default_icon;
        foreach ($tool_icons as $key => $data) {
            if (stripos((string) $tool['name'], $key) !== false) {
                [$icon, $color, $category] = $data;
                break;
            }
        }
        $categories[$category] = ($categories[$category] ?? 0) + 1;

        $display_name = ucwords(str_replace('_', ' ', (string) $tool['name']));
        $desc         = (string) $tool['description'];
        $href         = $_this_script_no_act . '?act=' . rawurlencode((string) $tool['name']);

        $tools_html .= '<a class="st-card sp-tool" href="' . $href . '"'
            . ' data-category="' . $e($category) . '"'
            . ' data-search="' . $e(mb_strtolower($display_name . ' ' . $tool['name'] . ' ' . $desc)) . '">'
            .   '<span class="st-ico sp-tool__ico ' . ($soft[$color] ?? 'st-soft-primary') . '"><i class="fa-solid ' . $icon . '"></i></span>'
            .   '<span class="sp-tool__body">'
            .     '<span class="sp-tool__name">' . $e($display_name) . '</span>'
            .     '<span class="sp-tool__desc" title="' . $e($desc) . '">' . $e($desc) . '</span>'
            .     '<span class="sp-tool__cat"><i class="fa-solid fa-folder"></i>' . $e($cat_labels[$category] ?? $category) . '</span>'
            .   '</span>'
            .   '<i class="fa-solid fa-arrow-right sp-tool__go" aria-hidden="true"></i>'
            . '</a>';
        ++$count;
    }

    // Название группы для плитки «уровень доступа»
    $group      = usergroup_permissions($gid);
    $group_name = (string) ($group['title'] ?? $gid);

    echo '<div class="container-md st-page sp-page">';

    // ── Шапка ──
    echo '<header class="st-card st-head mb-3">'
       .   '<div class="st-head__icon st-soft-primary"><i class="fa-solid fa-toolbox"></i></div>'
       .   '<div class="flex-grow-1 min-w-0">'
       .     '<h1 class="st-title">' . $e($L['hero_title']) . '</h1>'
       .     '<div class="st-head__meta">'
       .       '<span>' . ags_fmt($e($L['hero_subtitle']), $e($CURUSER['username'])) . '</span>'
       .     '</div>'
       .   '</div>'
       .   '<span class="st-badge st-soft-primary d-none d-md-inline-flex"><i class="fa-solid fa-shield-halved"></i>' . $e($L['hero_badge']) . '</span>'
       . '</header>';

    // ── Плитки ──
    $tile = static fn(string $icon, string $soft_cls, string $val, string $lbl): string =>
        '<div class="st-kpi st-kpi--muted">'
        . '<span class="st-ico ' . $soft_cls . '"><i class="fa-solid ' . $icon . '"></i></span>'
        . '<div class="min-w-0"><div class="st-kpi__val">' . $val . '</div><div class="st-kpi__lbl">' . $lbl . '</div></div>'
        . '<i class="fa-solid ' . $icon . ' st-kpi__bg" aria-hidden="true"></i>'
        . '</div>';

    echo '<div class="sp-kpis mb-3">'
       . $tile('fa-screwdriver-wrench', 'st-soft-primary', (string) $count,              $e($L['stat_available_tools']))
       . $tile('fa-layer-group',        'st-soft-success', (string) count($categories),  $e($L['stat_categories']))
       . $tile('fa-user-shield',        'st-soft-warning', $e($group_name),              $e($L['lbl_access']))
       . $tile('fa-calendar-day',       'st-soft-info',    $e(date($L['fmt_time_short'])), $e(date($L['fmt_date_long'])))
       . '</div>';

    if ($count === 0) {
        echo '<div class="st-card"><div class="st-empty">'
           .   '<div class="st-ico st-soft-danger"><i class="fa-solid fa-lock"></i></div>'
           .   '<div class="sp-empty__title">' . $e($L['empty_title']) . '</div>'
           .   '<div>' . $e($L['empty_text']) . '</div>'
           . '</div></div>';
        echo '</div>';
        return;
    }

    // ── Поиск + категории ──
    $pills = '<button type="button" class="btn btn-sm btn-primary st-pill" data-sp-cat="all">'
           . '<i class="fa-solid fa-table-cells-large"></i> ' . $e($L['btn_filter_all'])
           . ' <span class="sp-pill-count">' . $count . '</span></button>';
    ksort($categories);
    foreach ($categories as $cat => $n) {
        $pills .= '<button type="button" class="btn btn-sm btn-outline-secondary st-pill" data-sp-cat="' . $e($cat) . '">'
                . $e($cat_labels[$cat] ?? $cat) . ' <span class="sp-pill-count">' . (int) $n . '</span></button>';
    }

    echo '<section class="st-card sp-filter mb-3">'
       .   '<div class="st-toolbar">'
       .     '<div class="input-group sp-filter__search">'
       .       '<span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>'
       .       '<input type="search" class="form-control" id="spToolSearch" placeholder="' . $e($L['ph_search_tools']) . '" autocomplete="off">'
       .     '</div>'
       .     '<div class="sp-filter__pills" id="spToolCats">' . $pills . '</div>'
       .   '</div>'
       . '</section>';

    echo '<div class="sp-tools" id="spTools">' . $tools_html . '</div>';
    echo '<div class="st-card sp-noresult" id="spNoResult" hidden><div class="st-empty">'
       .   '<div class="st-ico st-soft-muted"><i class="fa-solid fa-magnifying-glass"></i></div>'
       .   '<div class="sp-empty__title">' . $e($L['msg_no_tools_found'] ?? 'No tools match your search') . '</div>'
       . '</div></div>';

    echo '</div>';   // .sp-page
    echo '<script src="' . $BASEURL . '/admin/scripts/stafftools.js?ver=1" defer></script>';
}




function get_list2()
{
    global $thispath;
    global $_this_script_;
    global $_this_script_no_act;
    global $eol;
    global $db;
	global $BASEURL;
    global $lang;

    $query = $db->sql_query_prepared('SELECT * FROM staffpanel ORDER BY name');

    $str = '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/get-list2.css">' . $eol;

    $count = 0;
    $tools_html = '';

    $t_access  = htmlspecialchars($lang->staffpanel['lbl_access']);
    $t_active  = htmlspecialchars($lang->staffpanel['lbl_status_active']);
    $t_edit    = htmlspecialchars($lang->staffpanel['tip_edit_tool'], ENT_QUOTES);
    $t_delete  = htmlspecialchars($lang->staffpanel['tip_delete_tool'], ENT_QUOTES);

    while ($query && ($tools = $db->fetch_array($query)))
    {
        if (@file_exists($thispath . $tools['filename']))
        {
            $usergroups = str_replace(array('[', ']'), '', $tools['usergroups']);
            $icon = get_tool_icon_admin($tools['name']);

            $tools_html .= '
            <div class="tool-row">
                <div class="tool-item">
                    <div>
                        <div class="tool-header-info">
                            <div class="tool-icon">
                                <i class="' . $icon . '"></i>
                            </div>
                            <div class="tool-info">
                                <h3 class="tool-name" title="' . htmlspecialchars($tools['name']) . '">' . htmlspecialchars(strtoupper($tools['name'])) . '</h3>
                                <p class="tool-description" title="' . htmlspecialchars($tools['description']) . '">' . htmlspecialchars($tools['description']) . '</p>
                            </div>
                        </div>

                        <div class="tool-groups">
                            <div class="groups-label">' . $t_access . '</div>
                            <div class="groups-list" title="' . htmlspecialchars($usergroups) . '">' . htmlspecialchars($usergroups) . '</div>
                        </div>
                    </div>

                    <div class="tool-meta">
                        <div class="tool-status">
                            <span class="status-dot"></span>
                            <span>' . $t_active . '</span>
                        </div>
                        <div class="tool-actions">
                            <a class="tool-btn edit" href="' . $_this_script_ . '&do=edit&id=' . $tools['id'] . '" title="' . $t_edit . '" aria-label="' . $t_edit . '">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a class="tool-btn delete" href="' . $_this_script_ . '&do=delete&id=' . $tools['id'] . '" onclick="return confirm(window.staffT ? staffT(\'confirm_delete_tool\', \'Delete this tool?\') : \'Delete this tool?\')" title="' . $t_delete . '" aria-label="' . $t_delete . '">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>' . $eol;

            ++$count;
        }
    }

    $str .= '
    <div class="admin-tools-table">
        <div class="tools-header">
            <h2 class="tools-title">
                <i class="fas fa-cogs"></i>
                ' . htmlspecialchars($lang->staffpanel['list_title']) . '
            </h2>
        </div>

        <div class="tools-grid">' . $tools_html . '</div>

        <div class="tools-footer">
            <div class="tools-count">
                ' . ags_fmt(htmlspecialchars($lang->staffpanel['tools_count']), '<span class="count-number">' . $count . '</span>') . '
            </div>
        </div>
    </div>' . $eol;

    echo $str;
}

// Функция для получения иконок инструментов (остается без изменений)
function get_tool_icon_admin($tool_name) {
    $tool_lower = strtolower($tool_name);

    $icon_map = [
        'user' => 'fas fa-users',
        'search' => 'fas fa-search',
        'stats' => 'fas fa-chart-bar',
        'log' => 'fas fa-clipboard-list',
        'settings' => 'fas fa-cogs',
        'security' => 'fas fa-shield-alt',
        'backup' => 'fas fa-database',
        'mail' => 'fas fa-envelope',
        'report' => 'fas fa-flag',
        'moderate' => 'fas fa-gavel',
        'torrent' => 'fas fa-download',
        'forum' => 'fas fa-comments',
        'system' => 'fas fa-server',
        'debug' => 'fas fa-bug',
        'test' => 'fas fa-vial',
        'money' => 'fas fa-coins',
        'news' => 'fas fa-newspaper',
        'announce' => 'fas fa-bullhorn'
    ];

    foreach ($icon_map as $key => $icon) {
        if (strpos($tool_lower, $key) !== false) {
            return $icon;
        }
    }

    return 'fas fa-toolbox';
}
