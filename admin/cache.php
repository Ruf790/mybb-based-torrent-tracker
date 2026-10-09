<?php

declare(strict_types=1);

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$lang->load('cache2');

/**
 * Подстановка {1}, {2}… (и %1$s, %2$s… — в них $lang->load() превращает {N}).
 * Объявлена ДО switch: условно объявленная функция не «всплывает».
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

$mybb->input['action'] ??= '';
$mybb->input['do']     ??= '';
$mybb->input['module'] ??= '';
$mybb->input['title']  ??= '';

$plugins->run_hooks("admin_tools_cache_begin");

switch ($mybb->input['action']) {
    case 'view':        handleCacheView();       break;
    case 'rebuild':
    case 'reload':      handleCacheRebuild();    break;
    case 'rebuild_all': handleCacheRebuildAll(); break;
    default:            handleCacheManager();    break;
}

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

/** Короткие описания известных кэшей — чтобы было понятно, что это */
function cacheDescription(string $title): string
{
    global $lang;

    static $map = [
        'settings'           => 'desc_settings',
        'usergroups'         => 'desc_usergroups',
        'forums'             => 'desc_forums',
        'forumpermissions'   => 'desc_forumpermissions',
        'moderators'         => 'desc_moderators',
        'attachtypes'        => 'desc_attachtypes',
        'smilies'            => 'desc_smilies',
        'badwords'           => 'desc_badwords',
        'bannedips'          => 'desc_bannedips',
        'bannedemails'       => 'desc_bannedemails',
        'birthdays'          => 'desc_birthdays',
        'stats'              => 'desc_stats',
        'statistics'         => 'desc_statistics',
        'plugins'            => 'desc_plugins',
        'mycode'             => 'desc_mycode',
        'posticons'          => 'desc_posticons',
        'profilefields'      => 'desc_profilefields',
        'reportedcontent'    => 'desc_reportedcontent',
        'awaitingactivation' => 'desc_awaitingactivation',
        'mostonline'         => 'desc_mostonline',
        'spiders'            => 'desc_spiders',
        'tasks'              => 'desc_tasks',
        'update_check'       => 'desc_update_check',
        'version'            => 'desc_version',
        'internal_settings'  => 'desc_internal_settings',
        'threadprefixes'     => 'desc_threadprefixes',
        'forumsdisplay'      => 'desc_forumsdisplay',
        'groupleaders'       => 'desc_groupleaders',
        'default_theme'      => 'desc_default_theme',
        'KPS'                => 'desc_kps',
    ];
    return isset($map[$title]) ? (string)$lang->cache2[$map[$title]] : '';
}

function cacheIcon(string $title): string
{
    static $map = [
        'settings' => 'fa-sliders', 'usergroups' => 'fa-users', 'forums' => 'fa-comments', 'forumpermissions' => 'fa-shield-halved',
        'moderators' => 'fa-user-shield', 'attachtypes' => 'fa-paperclip', 'smilies' => 'fa-face-smile', 'badwords' => 'fa-filter',
        'bannedips' => 'fa-ban', 'bannedemails' => 'fa-envelope-circle-check', 'birthdays' => 'fa-cake-candles', 'stats' => 'fa-chart-simple',
        'statistics' => 'fa-chart-line', 'plugins' => 'fa-plug', 'mycode' => 'fa-code', 'posticons' => 'fa-icons', 'profilefields' => 'fa-id-card',
        'reportedcontent' => 'fa-flag', 'awaitingactivation' => 'fa-user-clock', 'mostonline' => 'fa-trophy', 'spiders' => 'fa-spider',
        'tasks' => 'fa-clock', 'update_check' => 'fa-cloud-arrow-down', 'version' => 'fa-code-branch', 'threadprefixes' => 'fa-tag', 'KPS' => 'fa-coins',
    ];
    return $map[$title] ?? 'fa-database';
}

/** Подпись кнопки/подсказки для метода перестроения ('rebuild' | 'reload') */
function cacheMethodLabel(string $method): string
{
    global $lang;
    return $method === 'rebuild' ? $lang->cache2['btn_rebuild'] : $lang->cache2['btn_reload'];
}

/**
 * Какой метод перестроения доступен: ['rebuild'|'reload', callable-описание] или null.
 * Раньше имя кэша из URL подставлялось в update_{title}() / reload_{title}() без
 * проверки — можно было вызвать ЛЮБУЮ функцию с таким префиксом.
 */
function cacheRebuildMethod(string $title): ?array
{
    global $cache;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $title)) return null;
    if (method_exists($cache, "update_{$title}")) return ['rebuild', [$cache, "update_{$title}"]];
    if (method_exists($cache, "reload_{$title}")) return ['reload',  [$cache, "reload_{$title}"]];
    if (function_exists("update_{$title}"))       return ['rebuild', "update_{$title}"];
    if (function_exists("reload_{$title}"))       return ['reload',  "reload_{$title}"];
    return null;
}

function cacheExists(string $title): bool
{
    global $db;
    if ($title === 'settings') return true;
    $q = $db->sql_query_prepared("SELECT title FROM datacache WHERE title = ? LIMIT 1", [$title]);
    return $q && $db->num_rows($q) > 0;
}

function cacheAssets(): void
{
    
	global $BASEURL;
	
	echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/cache.css">';

}

/** JS-строки (js_* без префикса) + скрипт страницы */
function cacheScripts(): void
{
    global $BASEURL, $lang;

    $js = [];
    foreach ((array)$lang->cache2 as $key => $value) {
        if (str_starts_with((string)$key, 'js_')) {
            $js[substr((string)$key, 3)] = (string)$value;
        }
    }

    echo '<script>const AGS_LANG = '
       . json_encode($js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
       . ';</script>';
    echo '<script src="' . $BASEURL . '/admin/scripts/cache.js?ver=1"></script>';
}

/** Строка для JS внутри HTML-атрибута (onsubmit="return confirm(...)") */
function cacheJsAttr(string $text): string
{
    return htmlspecialchars(
        (string)json_encode($text, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ENT_QUOTES
    );
}

// ═══════════════════════════════════════════════════════════
// VIEW
// ═══════════════════════════════════════════════════════════

function handleCacheView(): void
{
    global $mybb, $plugins, $lang;

    $title = trim((string)($mybb->input['title'] ?? ''));
    if ($title === '') {
        flash_message($lang->cache2['flash_no_cache_specified'], 'error');
        admin_redirect("index.php?act=cache");
    }

    $plugins->run_hooks("admin_tools_cache_view");

    $cacheItem = getCacheItem($title);
    if (!$cacheItem) {
        flash_message($lang->cache2['flash_cache_not_found'], 'error');
        admin_redirect("index.php?act=cache");
    }

    displayCacheView($cacheItem, processCacheContents((string)$cacheItem['cache']));
}

function getCacheItem(string $title): ?array
{
    global $db, $mybb;

    if ($title === 'settings') {
        $cachedSettings = (array)$mybb->settings;
        unset($cachedSettings['internal']);
        return ['title' => 'settings', 'cache' => my_serialize($cachedSettings)];
    }

    $query = $db->sql_query_prepared("SELECT * FROM datacache WHERE title = ?", [$title]);
    return ($query ? $db->fetch_array($query) : null) ?: null;
}

function processCacheContents(string $cacheData): string
{
    $cacheContents = native_unserialize($cacheData);
    if (empty($cacheContents)) {
        return '';
    }
    return htmlspecialchars_uni(print_r($cacheContents, true));
}

function displayCacheView(array $cacheItem, string $cacheContents): void
{
    global $mybb, $lang;

    $title   = (string)$cacheItem['title'];
    $titleE  = htmlspecialchars_uni($title);
    $bytes   = strlen((string)$cacheItem['cache']);
    $lines   = $cacheContents === '' ? 0 : substr_count($cacheContents, "\n") + 1;
    $method  = cacheRebuildMethod($title) ?? ($title === 'settings' ? ['reload', null] : null);
    $desc    = cacheDescription($title);

    stdhead(ags_fmt($lang->cache2['title_view'], $title));
    cacheAssets();

    $rebuildBtn = $method
        ? '<a href="index.php?act=cache&amp;action=' . $method[0] . '&amp;title=' . urlencode($title) . '&amp;my_post_key=' . $mybb->post_code . '" class="btn btn-sm btn-outline-warning px-3"><i class="fa-solid ' . ($method[0] === 'rebuild' ? 'fa-hammer' : 'fa-rotate') . ' me-1"></i>' . htmlspecialchars_uni(cacheMethodLabel($method[0])) . '</a>'
        : '';

    echo '<div class="container mt-3 mb-4 cc">';
    echo '<div class="cc-card mb-3"><div class="cc-head">'
       . '<span class="cc-head-icon ic-blue"><i class="fa-solid ' . cacheIcon($title) . '"></i></span>'
       . '<div style="min-width:0"><h1 class="cc-title font-monospace">' . $titleE . '</h1>'
       . '<div class="cc-sub">' . ($desc !== '' ? htmlspecialchars_uni($desc) . ' · ' : '') . mksize($bytes) . ' · ' . htmlspecialchars_uni(ags_fmt($lang->cache2['sub_lines'], number_format($lines))) . '</div></div>'
       . '<div class="ms-auto d-flex flex-wrap gap-2">' . $rebuildBtn
       . '<a href="index.php?act=cache" class="btn btn-sm btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i>' . htmlspecialchars_uni($lang->cache2['btn_back']) . '</a></div>'
       . '</div></div>';

    echo '<div class="cc-card">';
    if ($cacheContents === '') {
        echo '<div class="cc-empty"><i class="fa-solid fa-box-open fa-2x mb-2 d-block opacity-50"></i>' . htmlspecialchars_uni($lang->cache2['empty_cache']) . '</div>';
    } else {
        echo '<div class="cc-toolbar">'
           . '<div class="position-relative cc-search flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i>'
           . '<input type="search" class="form-control form-control-sm" id="ccFind" placeholder="' . htmlspecialchars_uni($lang->cache2['ph_highlight']) . '" aria-label="' . htmlspecialchars_uni($lang->cache2['ph_highlight']) . '"></div>'
           . '<span class="cc-muted" id="ccHits"></span>'
           . '<div class="d-flex gap-2">'
           . '<button type="button" class="btn btn-sm btn-outline-secondary" id="ccWrap"><i class="fa-solid fa-text-width me-1"></i>' . htmlspecialchars_uni($lang->cache2['btn_wrap']) . '</button>'
           . '<button type="button" class="btn btn-sm btn-outline-secondary" id="ccCopy"><i class="fa-regular fa-copy me-1"></i>' . htmlspecialchars_uni($lang->cache2['btn_copy']) . '</button>'
           . '</div></div>';
        echo '<pre class="cc-pre" id="ccPre">' . $cacheContents . '</pre>';
    }
    echo '</div></div>';

    cacheScripts();

    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// REBUILD
// ═══════════════════════════════════════════════════════════

function handleCacheRebuild(): void
{
    global $mybb, $plugins, $lang;

    $title  = (string)($mybb->input['title'] ?? '');
    $action = $mybb->input['action'] === 'rebuild' ? 'rebuild' : 'reload';

    // Раньше ключ my_post_key передавался в ссылке, но НЕ проверялся — перестроение
    // запускалось любой GET-ссылкой (CSRF)
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        flash_message($lang->cache2['flash_invalid_token'], 'error');
        admin_redirect("index.php?act=cache");
    }
    if ($title === '' || !cacheExists($title)) {
        flash_message($lang->cache2['flash_no_such_cache'], 'error');
        admin_redirect("index.php?act=cache");
    }

    $plugins->run_hooks("admin_tools_cache_{$action}");

    if ($title === 'settings') {
        rebuild_settings();
        $plugins->run_hooks("admin_tools_cache_rebuild_commit");
        log_admin_action($title);
        flash_message($lang->cache2['flash_settings_reloaded'], 'success');
        admin_redirect("index.php?act=cache");
    }

    $method = cacheRebuildMethod($title);
    if (!$method) {
        flash_message($lang->cache2['flash_cannot_rebuild'], 'error');
        admin_redirect("index.php?act=cache");
    }
    call_user_func($method[1]);

    $plugins->run_hooks("admin_tools_cache_rebuild_commit");
    log_admin_action($title);
    flash_message(ags_fmt($method[0] === 'rebuild' ? $lang->cache2['flash_cache_rebuilt'] : $lang->cache2['flash_cache_reloaded'], $title), 'success');
    admin_redirect("index.php?act=cache");
}

function handleCacheRebuildAll(): void
{
    global $db, $plugins, $mybb, $lang;

    if ($mybb->request_method !== 'post' || !verify_post_check($mybb->get_input('my_post_key'), true)) {
        flash_message($lang->cache2['flash_invalid_token'], 'error');
        admin_redirect("index.php?act=cache");
    }

    $plugins->run_hooks("admin_tools_cache_rebuild_all");

    $done = 0;
    $query = $db->sql_query_prepared("SELECT title FROM datacache");
    while ($query && ($row = $db->fetch_array($query))) {
        if ($method = cacheRebuildMethod((string)$row['title'])) {
            call_user_func($method[1]);
            $done++;
        }
    }
    rebuild_settings();

    $plugins->run_hooks("admin_tools_cache_rebuild_all_commit");
    log_admin_action();
    flash_message(ags_fmt($lang->cache2['flash_all_rebuilt'], $done + 1), 'success');
    admin_redirect("index.php?act=cache");
}

// ═══════════════════════════════════════════════════════════
// LIST
// ═══════════════════════════════════════════════════════════

function handleCacheManager(): void
{
    global $db, $plugins, $mybb, $lang;

    $plugins->run_hooks("admin_tools_cache_start");

    $items = [];
    $query = $db->sql_query_prepared("SELECT title, LENGTH(cache) AS bytes FROM datacache ORDER BY title");
    while ($query && ($row = $db->fetch_array($query))) {
        $items[] = ['title' => (string)$row['title'], 'bytes' => (int)$row['bytes']];
    }
    $total   = array_sum(array_column($items, 'bytes'));
    $max     = $items ? max(1, ...array_column($items, 'bytes')) : 1;
    $largest = $items ? array_reduce($items, fn($c, $i) => ($c === null || $i['bytes'] > $c['bytes']) ? $i : $c) : null;
    $rebuildable = count(array_filter($items, fn($i) => cacheRebuildMethod($i['title']) !== null)) + 1; // + settings

    $L = $lang->cache2;
    $e = static fn(string $s): string => htmlspecialchars_uni($s);

    stdhead($L['title_manager']);
    cacheAssets();

    echo '<div class="container mt-3 mb-4 cc">';
    echo '<div class="cc-card mb-3"><div class="cc-head">'
       . '<span class="cc-head-icon ic-teal"><i class="fa-solid fa-database"></i></span>'
       . '<div><h1 class="cc-title">' . $e($L['title_manager']) . '</h1><div class="cc-sub">' . $e($L['sub_manager']) . '</div></div>'
       . '<form method="post" action="index.php?act=cache&amp;action=rebuild_all" class="ms-auto mb-0" onsubmit="return confirm(' . cacheJsAttr(ags_fmt($L['confirm_rebuild_all'], $rebuildable)) . ')">'
       . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">'
       . '<button type="submit" class="btn btn-warning px-3"><i class="fa-solid fa-arrows-rotate me-1"></i>' . $e($L['btn_rebuild_all']) . '</button></form>'
       . '</div></div>';

    echo '<div class="row g-3 mb-3">';
    foreach ([
        ['fa-layer-group',     'ic-blue',   $L['kpi_caches'],      number_format(count($items) + 1)],
        ['fa-hard-drive',      'ic-green',  $L['kpi_total_size'],  mksize($total)],
        ['fa-hammer',          'ic-amber',  $L['kpi_rebuildable'], number_format($rebuildable)],
        ['fa-weight-hanging',  'ic-purple', $L['kpi_largest'],     $largest ? htmlspecialchars_uni($largest['title']) : '—'],
    ] as [$ic, $cls, $label, $val]) {
        echo '<div class="col-6 col-lg-3"><div class="cc-card cc-stat"><span class="cc-stat-icon ' . $cls . '"><i class="fa-solid ' . $ic . '"></i></span>'
           . '<div style="min-width:0"><div class="cc-stat-label">' . $e($label) . '</div><div class="cc-stat-value">' . $val . '</div></div></div></div>';
    }
    echo '</div>';

    echo '<div class="cc-card overflow-hidden">'
       . '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-bottom">'
       . '<span class="fw-bold"><i class="fa-solid fa-list me-2 text-body-secondary"></i>' . $e($L['sec_all_caches']) . '</span>'
       . '<div class="position-relative cc-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" class="form-control form-control-sm" id="ccFilter" placeholder="' . $e($L['ph_filter']) . '" aria-label="' . $e($L['ph_filter']) . '"></div>'
       . '</div><div class="table-responsive"><table class="table cc-table"><thead><tr>'
       . '<th><i class="fa-solid fa-cube"></i>' . $e($L['th_cache']) . '</th>'
       . '<th><i class="fa-solid fa-weight-hanging"></i>' . $e($L['th_size']) . '</th>'
       . '<th class="text-center"><i class="fa-solid fa-gears"></i>' . $e($L['th_rebuild']) . '</th>'
       . '<th class="text-end"><i class="fa-solid fa-bolt"></i>' . $e($L['th_actions']) . '</th>'
       . '</tr></thead><tbody>';

    // settings — первым
    echo cacheRow('settings', null, 1, ['reload', null], true);
    foreach ($items as $it) {
        echo cacheRow($it['title'], $it['bytes'], $max, cacheRebuildMethod($it['title']), false);
    }
    echo '<tr id="ccNoMatch" hidden><td colspan="4"><div class="cc-empty"><i class="fa-solid fa-magnifying-glass fa-2x mb-2 d-block opacity-50"></i>' . $e($L['no_matches']) . '</div></td></tr>';
    echo '</tbody></table></div></div></div>';

    cacheScripts();

    stdfoot();
}

function cacheRow(string $title, ?int $bytes, int $max, ?array $method, bool $isSettings): string
{
    global $mybb, $lang;

    $t    = htmlspecialchars_uni($title);
    $u    = urlencode($title);
    $desc = cacheDescription($title);
    $cls  = $isSettings ? 'ic-green' : 'ic-blue';
    $pct  = $bytes !== null ? (int)round($bytes / $max * 100) : 0;

    $size = $bytes === null
        ? '<span class="cc-muted">' . htmlspecialchars_uni($lang->cache2['size_live']) . '</span>'
        : '<span class="cc-size">' . mksize($bytes) . '</span><div class="cc-bar' . ($pct >= 50 ? ' is-big' : '') . '" style="max-width:140px"><span style="width:' . max(2, $pct) . '%"></span></div>';

    $tag = $method === null
        ? '<span class="cc-tag t-static"><i class="fa-solid fa-lock"></i>' . htmlspecialchars_uni($lang->cache2['tag_static']) . '</span>'
        : ($method[0] === 'rebuild'
            ? '<span class="cc-tag t-rebuild"><i class="fa-solid fa-hammer"></i>' . htmlspecialchars_uni($lang->cache2['tag_rebuild']) . '</span>'
            : '<span class="cc-tag t-reload"><i class="fa-solid fa-rotate"></i>' . htmlspecialchars_uni($lang->cache2['tag_reload']) . '</span>');

    $viewTitle = htmlspecialchars_uni($lang->cache2['tip_view']);
    $actions = '<a href="index.php?act=cache&amp;action=view&amp;title=' . $u . '" class="cc-act" title="' . $viewTitle . '" aria-label="' . $viewTitle . '"><i class="fa-solid fa-eye"></i></a>';
    if ($method !== null) {
        $mLabel = htmlspecialchars_uni(cacheMethodLabel($method[0]));
        $actions .= '<a href="index.php?act=cache&amp;action=' . $method[0] . '&amp;title=' . $u . '&amp;my_post_key=' . $mybb->post_code . '" class="cc-act warn" title="' . $mLabel . '" aria-label="' . $mLabel . '"><i class="fa-solid ' . ($method[0] === 'rebuild' ? 'fa-hammer' : 'fa-rotate') . '"></i></a>';
    }

    // mb_strtolower: описание может быть на кириллице, а JS-фильтр сравнивает через toLowerCase()
    $search = $title . ' ' . $desc;
    $search = function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search);

    return '<tr data-search="' . htmlspecialchars_uni($search) . '">'
         . '<td><div class="d-flex align-items-center gap-3"><span class="cc-ico ' . $cls . '"><i class="fa-solid ' . cacheIcon($title) . '"></i></span>'
         . '<div style="min-width:0"><a href="index.php?act=cache&amp;action=view&amp;title=' . $u . '" class="cc-name">' . $t . '</a>'
         . ($desc !== '' ? '<div class="cc-muted">' . htmlspecialchars_uni($desc) . '</div>' : '') . '</div></div></td>'
         . '<td>' . $size . '</td>'
         . '<td class="text-center">' . $tag . '</td>'
         . '<td class="text-end text-nowrap">' . $actions . '</td>'
         . '</tr>';
}
