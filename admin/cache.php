<?php

declare(strict_types=1);

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
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
    static $map = [
        'settings'        => 'Board configuration settings',
        'usergroups'      => 'User groups and their permissions',
        'forums'          => 'Forum list and structure',
        'forumpermissions'=> 'Per-forum group permissions',
        'moderators'      => 'Forum moderators',
        'attachtypes'     => 'Allowed attachment types',
        'smilies'         => 'Smilies list',
        'badwords'        => 'Word filters',
        'bannedips'       => 'Banned IP addresses',
        'bannedemails'    => 'Banned email addresses',
        'birthdays'       => 'Upcoming birthdays',
        'stats'           => 'Board statistics',
        'statistics'      => 'Extended statistics',
        'plugins'         => 'Active plugins',
        'mycode'          => 'Custom MyCodes',
        'posticons'       => 'Post icons',
        'profilefields'   => 'Custom profile fields',
        'reportedcontent' => 'Reported content counters',
        'awaitingactivation' => 'Accounts awaiting activation',
        'mostonline'      => 'Most users online record',
        'spiders'         => 'Search engine spiders',
        'tasks'           => 'Scheduled tasks',
        'update_check'    => 'Version check result',
        'version'         => 'Installed version',
        'internal_settings' => 'Internal settings',
        'threadprefixes'  => 'Thread prefixes',
        'forumsdisplay'   => 'Forum display options',
        'groupleaders'    => 'Group leaders',
        'default_theme'   => 'Default theme',
        'KPS'             => 'Karma / bonus points settings',
    ];
    return $map[$title] ?? '';
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

// ═══════════════════════════════════════════════════════════
// VIEW
// ═══════════════════════════════════════════════════════════

function handleCacheView(): void
{
    global $mybb, $plugins;

    $title = trim((string)($mybb->input['title'] ?? ''));
    if ($title === '') {
        flash_message('No cache specified', 'error');
        admin_redirect("index.php?act=cache");
    }

    $plugins->run_hooks("admin_tools_cache_view");

    $cacheItem = getCacheItem($title);
    if (!$cacheItem) {
        flash_message('Cache not found', 'error');
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
    global $mybb;

    $title   = (string)$cacheItem['title'];
    $titleE  = htmlspecialchars_uni($title);
    $bytes   = strlen((string)$cacheItem['cache']);
    $lines   = $cacheContents === '' ? 0 : substr_count($cacheContents, "\n") + 1;
    $method  = cacheRebuildMethod($title) ?? ($title === 'settings' ? ['reload', null] : null);
    $desc    = cacheDescription($title);

    stdhead('Cache: ' . $title);
    cacheAssets();

    $rebuildBtn = $method
        ? '<a href="index.php?act=cache&amp;action=' . $method[0] . '&amp;title=' . urlencode($title) . '&amp;my_post_key=' . $mybb->post_code . '" class="btn btn-sm btn-outline-warning px-3"><i class="fa-solid ' . ($method[0] === 'rebuild' ? 'fa-hammer' : 'fa-rotate') . ' me-1"></i>' . ucfirst($method[0]) . '</a>'
        : '';

    echo '<div class="container mt-3 mb-4 cc">';
    echo '<div class="cc-card mb-3"><div class="cc-head">'
       . '<span class="cc-head-icon ic-blue"><i class="fa-solid ' . cacheIcon($title) . '"></i></span>'
       . '<div style="min-width:0"><h1 class="cc-title font-monospace">' . $titleE . '</h1>'
       . '<div class="cc-sub">' . ($desc !== '' ? htmlspecialchars_uni($desc) . ' · ' : '') . mksize($bytes) . ' · ' . number_format($lines) . ' lines</div></div>'
       . '<div class="ms-auto d-flex flex-wrap gap-2">' . $rebuildBtn
       . '<a href="index.php?act=cache" class="btn btn-sm btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i>Back</a></div>'
       . '</div></div>';

    echo '<div class="cc-card">';
    if ($cacheContents === '') {
        echo '<div class="cc-empty"><i class="fa-solid fa-box-open fa-2x mb-2 d-block opacity-50"></i>This cache is empty</div>';
    } else {
        echo '<div class="cc-toolbar">'
           . '<div class="position-relative cc-search flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i>'
           . '<input type="search" class="form-control form-control-sm" id="ccFind" placeholder="Highlight text…"></div>'
           . '<span class="cc-muted" id="ccHits"></span>'
           . '<div class="d-flex gap-2">'
           . '<button type="button" class="btn btn-sm btn-outline-secondary" id="ccWrap"><i class="fa-solid fa-text-width me-1"></i>Wrap</button>'
           . '<button type="button" class="btn btn-sm btn-outline-secondary" id="ccCopy"><i class="fa-regular fa-copy me-1"></i>Copy</button>'
           . '</div></div>';
        echo '<pre class="cc-pre" id="ccPre">' . $cacheContents . '</pre>';
    }
    echo '</div></div>';

    echo <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pre = document.getElementById('ccPre');
    if (!pre) return;
    const original = pre.textContent;
    const find = document.getElementById('ccFind'), hits = document.getElementById('ccHits');
    const esc = s => s.replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
    let t;
    find.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(function () {
            const q = find.value;
            if (!q) { pre.textContent = original; hits.textContent = ''; return; }
            const parts = original.split(q);
            pre.innerHTML = parts.map(esc).join('<mark>' + esc(q) + '</mark>');
            hits.textContent = (parts.length - 1) + ' match(es)';
            pre.querySelector('mark')?.scrollIntoView({ block: 'center' });
        }, 200);
    });
    document.getElementById('ccWrap').addEventListener('click', function () {
        pre.classList.toggle('is-wrap'); this.classList.toggle('active');
    });
    document.getElementById('ccCopy').addEventListener('click', function () {
        navigator.clipboard?.writeText(original).then(() => {
            this.innerHTML = '<i class="fa-solid fa-check me-1"></i>Copied';
            setTimeout(() => { this.innerHTML = '<i class="fa-regular fa-copy me-1"></i>Copy'; }, 1500);
        });
    });
});
</script>
HTML;

    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// REBUILD
// ═══════════════════════════════════════════════════════════

function handleCacheRebuild(): void
{
    global $mybb, $plugins;

    $title  = (string)($mybb->input['title'] ?? '');
    $action = $mybb->input['action'] === 'rebuild' ? 'rebuild' : 'reload';

    // Раньше ключ my_post_key передавался в ссылке, но НЕ проверялся — перестроение
    // запускалось любой GET-ссылкой (CSRF)
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        flash_message('Invalid security token', 'error');
        admin_redirect("index.php?act=cache");
    }
    if ($title === '' || !cacheExists($title)) {
        flash_message('No such cache', 'error');
        admin_redirect("index.php?act=cache");
    }

    $plugins->run_hooks("admin_tools_cache_{$action}");

    if ($title === 'settings') {
        rebuild_settings();
        $plugins->run_hooks("admin_tools_cache_rebuild_commit");
        log_admin_action($title);
        flash_message('The settings cache has been reloaded', 'success');
        admin_redirect("index.php?act=cache");
    }

    $method = cacheRebuildMethod($title);
    if (!$method) {
        flash_message('This cache cannot be rebuilt', 'error');
        admin_redirect("index.php?act=cache");
    }
    call_user_func($method[1]);

    $plugins->run_hooks("admin_tools_cache_rebuild_commit");
    log_admin_action($title);
    flash_message('Cache “' . $title . '” has been ' . ($method[0] === 'rebuild' ? 'rebuilt' : 'reloaded'), 'success');
    admin_redirect("index.php?act=cache");
}

function handleCacheRebuildAll(): void
{
    global $db, $plugins, $mybb;

    if ($mybb->request_method !== 'post' || !verify_post_check($mybb->get_input('my_post_key'), true)) {
        flash_message('Invalid security token', 'error');
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
    flash_message(($done + 1) . ' caches have been rebuilt', 'success');
    admin_redirect("index.php?act=cache");
}

// ═══════════════════════════════════════════════════════════
// LIST
// ═══════════════════════════════════════════════════════════

function handleCacheManager(): void
{
    global $db, $plugins, $mybb;

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

    stdhead('Cache Manager');
    cacheAssets();

    echo '<div class="container mt-3 mb-4 cc">';
    echo '<div class="cc-card mb-3"><div class="cc-head">'
       . '<span class="cc-head-icon ic-teal"><i class="fa-solid fa-database"></i></span>'
       . '<div><h1 class="cc-title">Cache Manager</h1><div class="cc-sub">Stored data caches — view contents or rebuild them from the database</div></div>'
       . '<form method="post" action="index.php?act=cache&amp;action=rebuild_all" class="ms-auto mb-0" onsubmit="return confirm(\'Rebuild all ' . $rebuildable . ' caches now?\')">'
       . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">'
       . '<button type="submit" class="btn btn-warning px-3"><i class="fa-solid fa-arrows-rotate me-1"></i>Rebuild all</button></form>'
       . '</div></div>';

    echo '<div class="row g-3 mb-3">';
    foreach ([
        ['fa-layer-group',     'ic-blue',   'Caches',      number_format(count($items) + 1)],
        ['fa-hard-drive',      'ic-green',  'Total size',  mksize($total)],
        ['fa-hammer',          'ic-amber',  'Rebuildable', number_format($rebuildable)],
        ['fa-weight-hanging',  'ic-purple', 'Largest',     $largest ? htmlspecialchars_uni($largest['title']) : '—'],
    ] as [$ic, $cls, $label, $val]) {
        echo '<div class="col-6 col-lg-3"><div class="cc-card cc-stat"><span class="cc-stat-icon ' . $cls . '"><i class="fa-solid ' . $ic . '"></i></span>'
           . '<div style="min-width:0"><div class="cc-stat-label">' . $label . '</div><div class="cc-stat-value">' . $val . '</div></div></div></div>';
    }
    echo '</div>';

    echo '<div class="cc-card overflow-hidden">'
       . '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-bottom">'
       . '<span class="fw-bold"><i class="fa-solid fa-list me-2 text-body-secondary"></i>All caches</span>'
       . '<div class="position-relative cc-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" class="form-control form-control-sm" id="ccFilter" placeholder="Filter caches…"></div>'
       . '</div><div class="table-responsive"><table class="table cc-table"><thead><tr>'
       . '<th><i class="fa-solid fa-cube"></i>Cache</th>'
       . '<th><i class="fa-solid fa-weight-hanging"></i>Size</th>'
       . '<th class="text-center"><i class="fa-solid fa-gears"></i>Rebuild</th>'
       . '<th class="text-end"><i class="fa-solid fa-bolt"></i>Actions</th>'
       . '</tr></thead><tbody>';

    // settings — первым
    echo cacheRow('settings', null, 1, ['reload', null], true);
    foreach ($items as $it) {
        echo cacheRow($it['title'], $it['bytes'], $max, cacheRebuildMethod($it['title']), false);
    }
    echo '<tr id="ccNoMatch" hidden><td colspan="4"><div class="cc-empty"><i class="fa-solid fa-magnifying-glass fa-2x mb-2 d-block opacity-50"></i>No matches</div></td></tr>';
    echo '</tbody></table></div></div></div>';

    echo <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    const f = document.getElementById('ccFilter');
    f && f.addEventListener('input', function () {
        const q = f.value.trim().toLowerCase();
        let shown = 0;
        document.querySelectorAll('.cc .cc-table tbody tr[data-search]').forEach(tr => {
            const ok = !q || tr.dataset.search.includes(q);
            tr.hidden = !ok; if (ok) shown++;
        });
        document.getElementById('ccNoMatch').hidden = shown !== 0;
    });
});
</script>
HTML;

    stdfoot();
}

function cacheRow(string $title, ?int $bytes, int $max, ?array $method, bool $isSettings): string
{
    global $mybb;

    $t    = htmlspecialchars_uni($title);
    $u    = urlencode($title);
    $desc = cacheDescription($title);
    $cls  = $isSettings ? 'ic-green' : 'ic-blue';
    $pct  = $bytes !== null ? (int)round($bytes / $max * 100) : 0;

    $size = $bytes === null
        ? '<span class="cc-muted">live</span>'
        : '<span class="cc-size">' . mksize($bytes) . '</span><div class="cc-bar' . ($pct >= 50 ? ' is-big' : '') . '" style="max-width:140px"><span style="width:' . max(2, $pct) . '%"></span></div>';

    $tag = $method === null
        ? '<span class="cc-tag t-static"><i class="fa-solid fa-lock"></i>static</span>'
        : ($method[0] === 'rebuild'
            ? '<span class="cc-tag t-rebuild"><i class="fa-solid fa-hammer"></i>rebuild</span>'
            : '<span class="cc-tag t-reload"><i class="fa-solid fa-rotate"></i>reload</span>');

    $actions = '<a href="index.php?act=cache&amp;action=view&amp;title=' . $u . '" class="cc-act" title="View contents"><i class="fa-solid fa-eye"></i></a>';
    if ($method !== null) {
        $actions .= '<a href="index.php?act=cache&amp;action=' . $method[0] . '&amp;title=' . $u . '&amp;my_post_key=' . $mybb->post_code . '" class="cc-act warn" title="' . ucfirst($method[0]) . '"><i class="fa-solid ' . ($method[0] === 'rebuild' ? 'fa-hammer' : 'fa-rotate') . '"></i></a>';
    }

    return '<tr data-search="' . htmlspecialchars_uni(strtolower($title . ' ' . $desc)) . '">'
         . '<td><div class="d-flex align-items-center gap-3"><span class="cc-ico ' . $cls . '"><i class="fa-solid ' . cacheIcon($title) . '"></i></span>'
         . '<div style="min-width:0"><a href="index.php?act=cache&amp;action=view&amp;title=' . $u . '" class="cc-name">' . $t . '</a>'
         . ($desc !== '' ? '<div class="cc-muted">' . htmlspecialchars_uni($desc) . '</div>' : '') . '</div></div></td>'
         . '<td>' . $size . '</td>'
         . '<td class="text-center">' . $tag . '</td>'
         . '<td class="text-end text-nowrap">' . $actions . '</td>'
         . '</tr>';
}