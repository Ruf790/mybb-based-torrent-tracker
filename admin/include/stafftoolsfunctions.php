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
    echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/staffpanel.css?ver=2">' . "\n";
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
    global $thispath, $_this_script_, $_this_script_no_act, $db, $mybb, $lang;

    $L = $lang->staffpanel;
    $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

    // Группы с доступом к панели: gid => [title, namestyle]
    $groups = [];
    $gq = $db->sql_query_prepared("SELECT gid, title, namestyle FROM usergroups WHERE canstaffpanel = '1' ORDER BY disporder");
    while ($gq && ($g = $db->fetch_array($gq))) {
        $groups[(int) $g['gid']] = str_replace('{username}', htmlspecialchars_uni((string) $g['title']), (string) $g['namestyle']);
    }

    $post_key = $e($mybb->post_code ?? '');
    $rows     = '';
    $count    = 0;
    $missing  = 0;

    $query = $db->sql_query_prepared('SELECT * FROM staffpanel ORDER BY name');
    while ($query && ($tool = $db->fetch_array($query))) {
        ++$count;
        $id      = (int) $tool['id'];
        $exists  = @file_exists($thispath . $tool['filename']);
        if (!$exists) ++$missing;

        // Бейджи групп
        $badges = '';
        foreach (explode(',', (string) $tool['usergroups']) as $raw) {
            $gid = (int) trim($raw, '[] ');
            if ($gid <= 0) continue;
            $badges .= '<span class="sp-gbadge">' . ($groups[$gid] ?? ('#' . $gid)) . '</span>';
        }

        $status = $exists
            ? '<span class="st-badge st-soft-success"><span class="sp-dot"></span>' . $e($L['lbl_status_active']) . '</span>'
            : '<span class="st-badge st-soft-danger" title="admin/' . $e($tool['filename']) . '"><i class="fa-solid fa-file-circle-xmark"></i>' . $e($L['badge_file_missing'] ?? 'File missing') . '</span>';

        $name_html = $exists
            ? '<a href="' . $_this_script_no_act . '?act=' . rawurlencode((string) $tool['name']) . '" class="sp-mtool__name">' . $e($tool['name']) . '</a>'
            : '<span class="sp-mtool__name">' . $e($tool['name']) . '</span>';

        $rows .= '<div class="sp-mtool' . ($exists ? '' : ' sp-mtool--missing') . '">'
            . '<span class="st-ico st-soft-primary"><i class="fa-solid ' . get_tool_icon_admin((string) $tool['name']) . '"></i></span>'
            . '<div class="sp-mtool__main">'
            .   '<div class="sp-mtool__top">' . $name_html . $status . '</div>'
            .   '<div class="sp-mtool__desc">' . $tool['description'] . '</div>'   // хранится экранированным
            .   '<div class="sp-mtool__groups"><span class="sp-mtool__lbl"><i class="fa-solid fa-key"></i>' . $e($L['lbl_access']) . '</span>' . $badges . '</div>'
            . '</div>'
            . '<div class="sp-mtool__actions">'
            .   '<a class="btn btn-sm btn-outline-secondary st-pill" href="' . $_this_script_ . '&amp;do=edit&amp;id=' . $id . '" title="' . $e($L['tip_edit_tool']) . '">'
            .     '<i class="fa-solid fa-pen"></i><span class="sp-hide-sm">' . $e($L['tip_edit_tool']) . '</span></a>'
            .   '<form method="post" action="' . $_this_script_ . '&amp;do=delete&amp;id=' . $id . '" data-sp-confirm="' . $e($L['js_confirm_delete_tool'] ?? $L['del_question']) . '"'
            .     ' data-sp-confirm-detail="' . $e($tool['name']) . '" data-sp-yes="' . $e($L['btn_del_yes']) . '" data-sp-no="' . $e($L['btn_del_no']) . '">'
            .     '<input type="hidden" name="my_post_key" value="' . $post_key . '">'
            .     '<button type="submit" class="btn btn-sm btn-outline-danger st-pill" title="' . $e($L['tip_delete_tool']) . '" aria-label="' . $e($L['tip_delete_tool']) . '"><i class="fa-solid fa-trash-can"></i></button>'
            .   '</form>'
            . '</div>'
            . '</div>';
    }

    $tile = static fn(string $icon, string $soft, string $val, string $lbl): string =>
        '<div class="st-kpi st-kpi--muted">'
        . '<span class="st-ico ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>'
        . '<div class="min-w-0"><div class="st-kpi__val">' . $val . '</div><div class="st-kpi__lbl">' . $lbl . '</div></div>'
        . '<i class="fa-solid ' . $icon . ' st-kpi__bg" aria-hidden="true"></i>'
        . '</div>';

    echo '<div class="container-md st-page sp-page">'
       . '<header class="st-card st-head mb-3">'
       .   '<div class="st-head__icon st-soft-primary"><i class="fa-solid fa-wrench"></i></div>'
       .   '<div class="flex-grow-1 min-w-0">'
       .     '<h1 class="st-title">' . $e($L['thead_manage']) . '</h1>'
       .     '<div class="st-head__meta"><span>' . ags_fmt($e($L['tools_count']), (string) $count) . '</span></div>'
       .   '</div>'
       .   '<a href="' . $_this_script_no_act . '?act=managestafftools&amp;do=newtool" class="btn btn-primary st-pill"><i class="fa-solid fa-plus"></i> ' . $e($L['btn_add_tool']) . '</a>'
       . '</header>'

       . '<div class="sp-kpis sp-kpis--3 mb-3">'
       .   $tile('fa-screwdriver-wrench', 'st-soft-primary', (string) $count,          $e($L['list_title']))
       .   $tile('fa-user-shield',        'st-soft-success', (string) count($groups),  $e($L['lbl_kpi_staff_groups'] ?? 'Staff groups'))
       .   $tile('fa-file-circle-xmark',  $missing > 0 ? 'st-soft-danger' : 'st-soft-muted', (string) $missing, $e($L['lbl_kpi_missing'] ?? 'Missing files'))
       . '</div>';

    if ($count === 0) {
        echo '<div class="st-card"><div class="st-empty">'
           . '<div class="st-ico st-soft-muted"><i class="fa-solid fa-toolbox"></i></div>'
           . '<div class="sp-empty__title">' . ags_fmt($e($L['tools_count']), '0') . '</div>'
           . '</div></div>';
    } else {
        echo '<section class="st-card sp-mlist">'
           .   '<div class="st-card__head"><span class="st-ico st-soft-primary"><i class="fa-solid fa-list"></i></span>' . $e($L['list_title'])
           .     '<span class="ms-auto st-badge st-soft-muted">' . $count . '</span></div>'
           .   $rows
           . '</section>';
    }

    echo '</div>';
}

// Функция для получения иконок инструментов (остается без изменений)
function get_tool_icon_admin($tool_name) {
    // Возвращает имя иконки без префикса стиля (fa-solid ставит вызывающий код)
    $tool_lower = strtolower((string) $tool_name);

    $icon_map = [
        'user' => 'fa-users',
        'search' => 'fa-magnifying-glass',
        'stats' => 'fa-chart-column',
        'log' => 'fa-clipboard-list',
        'settings' => 'fa-gears',
        'security' => 'fa-shield-halved',
        'backup' => 'fa-database',
        'mail' => 'fa-envelope',
        'report' => 'fa-flag',
        'moderate' => 'fa-gavel',
        'torrent' => 'fa-download',
        'forum' => 'fa-comments',
        'system' => 'fa-server',
        'debug' => 'fa-bug',
        'test' => 'fa-vial',
        'money' => 'fa-coins',
        'news' => 'fa-newspaper',
        'announce' => 'fa-bullhorn'
    ];

    foreach ($icon_map as $key => $icon) {
        if (strpos($tool_lower, $key) !== false) {
            return $icon;
        }
    }

    return 'fa-toolbox';
}
