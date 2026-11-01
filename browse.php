<?php

declare(strict_types=1);

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'browse.php');
define('B_VERSION', '6.7.0');
define("SCRIPTNAME", "browse.php");


require './global.php';

$lang->load('browse');

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_bookmark.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/functions_category.php';
require_once INC_PATH . '/functions_browse.php';

// Флаги для безопасного вывода строк в <script>
$agsJsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

maxsysop();

if (empty($CURUSER['id'])) {
    print_no_permission();
}

// Страницы с query-параметрами - дубли одного и того же browse.php,
// поисковики их индексировать не должны.
if (!empty($_SERVER['QUERY_STRING'])) {
    header('X-Robots-Tag: noindex, follow');
}

$is_mod = is_mod($usergroups);
$myId   = (int)$CURUSER['id'];


/* ═══════════════════════════════════════════════════════════════════════════
   Хелперы страницы
   ═══════════════════════════════════════════════════════════════════════════ */

/** {1}, {2}… и %1$s… в уже экранированной строке ланга */
if (!function_exists('ags_fmt_safe')) {
    function ags_fmt_safe(string $str, string|int ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $map['{' . ($i + 1) . '}']  = (string)$arg;
            $map['%' . ($i + 1) . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

/** Текущий $_GET с изменениями (null - убрать параметр). Номер страницы сбрасывается. */
function br_url(array $changes = []): string
{
    $q = $_GET;
    unset($q['page'], $q['mod_success'], $q['mod_error']);
    foreach ($changes as $k => $v) {
        if ($v === null) {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    $qs = http_build_query($q);
    return 'browse.php' . ($qs !== '' ? '?' . $qs : '');
}

/** Подходит ли текущий $_GET под пресет (null - параметра быть не должно) */
function br_preset_active(array $changes): bool
{
    foreach ($changes as $k => $v) {
        $cur = $_GET[$k] ?? null;
        if ($v === null ? $cur !== null : (string)$cur !== (string)$v) {
            return false;
        }
    }
    return true;
}

/**
 * Ссылка-заголовок колонки со стрелкой направления. Первый клик - по
 * возрастанию, повторный по той же колонке - переключает направление.
 */
function br_sort_link(string $label, string $col, string $baseUrl, ?string $curSort, string $curDir): string
{
    $active  = ($curSort === $col);
    $nextDir = ($active && $curDir === 'asc') ? 'desc' : 'asc';
    $arrow   = $active
        ? ' <i class="fa-solid fa-caret-' . ($curDir === 'asc' ? 'up' : 'down') . '" aria-hidden="true"></i>'
        : '';
    $sep = str_contains($baseUrl, '?') ? '&amp;' : '?';

    return '<a href="' . $baseUrl . $sep . 'sort=' . $col . '&amp;dir=' . $nextDir . '" class="br-sort'
        . ($active ? ' br-sort--active' : '') . '">' . $label . $arrow . '</a>';
}

/** Подсветка ключевых слов в названии (всё экранируется здесь) */
function br_highlight(string $name, string $keywords): string
{
    $safe = htmlspecialchars_uni($name);
    $kw   = trim($keywords);
    if ($kw === '') {
        return $safe;
    }
    $pattern = '/(' . preg_quote(htmlspecialchars_uni($kw), '/') . ')/iu';
    return preg_replace($pattern, '<mark class="br-hl">$1</mark>', $safe) ?? $safe;
}

/** Плашка акции (глобальная или ко дню рождения) */
function br_promo(string $tone, string $icon, string $title, string $text): string
{
    return '<div class="st-card br-promo br-promo--' . $tone . ' mb-3" role="status">'
        . '<div class="st-ico ' . ($tone === 'purple' ? 'br-soft-purple' : 'st-soft-' . $tone) . '"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i></div>'
        . '<div class="min-w-0"><div class="br-promo__title">' . $title . '</div>'
        . '<div class="br-promo__text">' . $text . '</div></div></div>';
}

/** Плашка бонуса ко дню рождения (до конца сегодняшнего дня по времени сервера) */
function browse_bday_notice(string $type, string $username): string
{
    global $lang;

    $types = [
        'freeleech'    => ['fa-gift',          'bday_fl_badge', 'Freeleech',     'bday_fl_text', 'Downloads today do not count toward your ratio — grab what you have been saving for.'],
        'silverleech'  => ['fa-coins',         'bday_sl_badge', 'Silverleech',   'bday_sl_text', 'Only half of what you download today counts toward your ratio.'],
        'doubleupload' => ['fa-angles-up',     'bday_du_badge', 'Double upload', 'bday_du_text', 'Everything you upload today counts twice.'],
    ];
    if (!isset($types[$type])) {
        return '';
    }
    [$icon, $badgeKey, $badgeFb, $textKey, $textFb] = $types[$type];

    $left  = max(0, (strtotime('tomorrow') - 1) - TIMENOW);
    $badge = htmlspecialchars_uni((string)($lang->browse[$badgeKey] ?? $badgeFb));
    $text  = htmlspecialchars_uni((string)($lang->browse[$textKey] ?? $textFb));
    $hnr   = htmlspecialchars_uni((string)($lang->browse['bday_hnr'] ?? "Please no hit'n'run — keep seeding after you finish!"));
    $until = htmlspecialchars_uni((string)($lang->browse['bday_until'] ?? 'Today until 23:59'));
    $lft   = ags_fmt_safe(htmlspecialchars_uni((string)($lang->browse['bday_left'] ?? 'Ends in {1} h {2} min')), intdiv($left, 3600), intdiv($left % 3600, 60));
    $title = ags_fmt_safe(htmlspecialchars_uni((string)($lang->browse['bday_title'] ?? 'Happy birthday, {1}!')), htmlspecialchars_uni($username));

    return br_promo(
        'purple',
        'fa-cake-candles',
        $title . ' 🎉',
        '<span class="st-badge st-soft-success me-1"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i>' . $badge . '</span>'
        . '<span class="br-promo__when">' . $until . ' · ' . $lft . '</span><br>' . $text . ' ' . $hnr
    );
}


/* ═══════════════════════════════════════════════════════════════════════════
   Входные параметры
   ═══════════════════════════════════════════════════════════════════════════ */

// Разбор и проверка фильтров - общий с xmlhttp.php (include/functions_browse.php)
$F = browse_filter_input($_GET + $_POST);

$category              = $F['category'];
$keywords              = $F['keywords'];
$search_type           = $F['search_type'];
$special_search        = $F['special_search'];
$include_dead_torrents = $F['include_dead'] ? 'yes' : '';
$health_filter         = $F['health'];
$freeleech_only        = $F['freeleech_only'];
$no_seeders_only       = $F['no_seeders'];
$seeders_gt_leechers   = $F['seeders_gt_leechers'];
$hide_downloaded       = $F['hide_downloaded'];
$imdb_min              = $F['imdb_min'];
$daysprune             = $F['daysprune'];
$smartBrowse           = $F['smart_browse'];
$size_min_val          = $F['size_min'];
$size_max_val          = $F['size_max'];
$min_seeders_val       = $F['min_seeders'];
$added_from_raw        = $F['added_from_raw'];
$added_from            = $F['added_from'];
$added_to_raw          = $F['added_to_raw'];
$added_to              = $F['added_to'];

// Сортировка (whitelist - $_GET['sort'] никогда не идёт в SQL напрямую)
$sortColumns = [
    'name'     => 't.name',
    'size'     => 't.size',
    'snatched' => 't.times_completed',
    'seeders'  => 't.seeders',
    'leechers' => 't.leechers',
    'added'    => 't.added',
];
$sortBy  = (isset($_GET['sort']) && is_string($_GET['sort']) && isset($sortColumns[$_GET['sort']])) ? $_GET['sort'] : null;
$sortDir = (($_GET['dir'] ?? '') === 'asc') ? 'asc' : 'desc';
$orderby = 't.sticky, ' . ($sortBy !== null ? $sortColumns[$sortBy] . ' ' . strtoupper($sortDir) : 't.added DESC');


/* ═══════════════════════════════════════════════════════════════════════════
   Глобальные акции / бонус ко дню рождения
   ═══════════════════════════════════════════════════════════════════════════ */

$_freelechmod = $_silverleechmod = $_x2mod = false;
$promoNotice  = '';
include TSDIR . '/cache/freeleech.php';

if ($__F_START < get_date_time() && $__F_END > get_date_time()) {
    $fStart = htmlspecialchars_uni((string)$__F_START);
    $fEnd   = htmlspecialchars_uni((string)$__F_END);

    switch ($__FLSTYPE) {
        case 'freeleech':
            $promoNotice  = br_promo('success', 'fa-gift', $lang->browse['pane_global_freeleech'], sprintf($lang->browse['f_leech'], $fStart, $fEnd));
            $_freelechmod = true;
            break;
        case 'silverleech':
            $promoNotice     = br_promo('info', 'fa-coins', $lang->browse['pane_global_silverleech'], sprintf($lang->browse['s_leech'], $fStart, $fEnd));
            $_silverleechmod = true;
            break;
        case 'doubleupload':
            $promoNotice = br_promo('warning', 'fa-angles-up', $lang->browse['pane_global_doubleupload'], sprintf($lang->browse['d_leech'], $fStart, $fEnd));
            $_x2mod      = true;
            break;
    }
} elseif ($bdayreward === 'yes' && $bdayrewardtype) {
    // birthday хранится как «день-месяц-год» (j-n-Y)
    $curuserbday = !empty($CURUSER['birthday']) ? explode('-', (string)$CURUSER['birthday']) : [];
    if (isset($curuserbday[0], $curuserbday[1]) && date('j-n') === (int)$curuserbday[0] . '-' . (int)$curuserbday[1]) {
        $promoNotice = browse_bday_notice((string)$bdayrewardtype, (string)($CURUSER['username'] ?? ''));
    }
}


/* ═══════════════════════════════════════════════════════════════════════════
   Категории
   ═══════════════════════════════════════════════════════════════════════════ */

require TSDIR . '/cache/categories.php';

$notifs     = (string)($CURUSER['notifs'] ?? '');
$catIconMap = [];
$catNameMap = [];
$subByPid   = [];

foreach ($_categoriesS ?? [] as $sc) {
    $sid = (int)$sc['id'];
    $catNameMap[$sid] = (string)$sc['name'];
    $subByPid[(int)$sc['pid']][] = $sc;
}

// Число живых раздач по категориям (подкатегории суммируются в родителя) - один запрос
$catCount = [];
$ccq = $db->sql_query_prepared("SELECT category, COUNT(*) AS cnt FROM torrents WHERE visible = ? AND banned = ? GROUP BY category", ['yes', 'no']);
while ($ccq && ($ccr = $db->fetch_array($ccq))) {
    $catCount[(int)$ccr['category']] = (int)$ccr['cnt'];
}
$subParent = [];
foreach ($_categoriesS ?? [] as $sc) {
    $subParent[(int)$sc['id']] = (int)$sc['pid'];
}
foreach ($subParent as $sid => $pid) {
    $catCount[$pid] = ($catCount[$pid] ?? 0) + ($catCount[$sid] ?? 0);
}

$catTones = ['primary', 'success', 'info', 'warning', 'danger', 'purple'];
$catTiles = '';
$catIndex = 0;
foreach ($_categoriesC ?? [] as $c) {
    $cid  = (int)$c['id'];
    $tone = $catTones[$catIndex++ % count($catTones)];
    $catIconMap[$cid] = (string)$c['icon'];
    $catNameMap[$cid] = (string)$c['name'];

    $isActive = ($category === $cid);
    $isNotif  = (!$category && str_contains($notifs, '[cat' . $cid . ']'));
    $cname    = htmlspecialchars_uni((string)$c['name']);

    $subs = '';
    foreach ($subByPid[$cid] ?? [] as $sc) {
        $sid   = (int)$sc['id'];
        $sname = htmlspecialchars_uni((string)$sc['name']);
        $subs .= '<a href="' . get_category_link($sid) . '" id="category' . $sid . '" class="br-cat__sub'
            . ($category === $sid ? ' br-cat__sub--active' : '') . '">' . $sname . '</a>';
        if ($category === $sid) {
            $isActive = true;   // подкатегория выбрана - подсвечиваем и родителя
        }
    }

    $catTiles .= '
        <div class="br-cat br-cat--' . $tone . ($isActive ? ' br-cat--active' : ($isNotif ? ' br-cat--notif' : '')) . '" data-category-id="' . $cid . '">
            <a href="' . get_category_link($cid) . '" class="br-cat__ico st-ico ' . ($tone === 'purple' ? 'br-soft-purple' : 'st-soft-' . $tone) . '" tabindex="-1" aria-hidden="true">
                <i class="' . htmlspecialchars_uni((string)$c['icon']) . '"></i>
            </a>
            <div class="min-w-0 flex-grow-1">
                <a href="' . get_category_link($cid) . '" id="category' . $cid . '" class="br-cat__name">' . $cname . '</a>'
                . ($subs !== '' ? '<div class="br-cat__subs">' . $subs . '</div>' : '') . '
            </div>
            <span class="br-cat__cnt">' . ts_nf($catCount[$cid] ?? 0) . '</span>
            <i class="' . htmlspecialchars_uni((string)$c['icon']) . ' br-cat__bg" aria-hidden="true"></i>
        </div>';
}

// Имя выбранной категории (для шапки и бейджа активного фильтра)
$catName = ($category && isset($catNameMap[$category])) ? $catNameMap[$category] : '';

// Выпадающие списки категорий: для поиска и для переноса (у каждого свой id)
$cleanCatSelect = static function (string $html, string $class): string {
    return str_replace(
        ['<label>', '</label>', 'form-select form-select-sm border pe-5 w-auto'],
        ['', '', $class],
        $html
    );
};
$catdropdown  = $cleanCatSelect(
    ts_category_list('category', $category, '<option value="0">' . $lang->browse['alltypes'] . '</option>', 'br_category'),
    'form-select'
);
$movedropdown = $is_mod
    ? $cleanCatSelect(ts_category_list('category', 0, '<option value="0">—</option>', 'br_move_category'), 'form-select form-select-sm mod-select')
    : '';
unset($_categoriesC, $_categoriesS);


/* ═══════════════════════════════════════════════════════════════════════════
   Опции фильтров
   ═══════════════════════════════════════════════════════════════════════════ */

$fmtGb = static fn(string $n): string =>
    sprintf($lang->browse['opt_size_gb'], str_replace('.', $lang->browse['lbl_num_dec_point'], $n));

$seeders_options = ['' => $lang->browse['opt_any_seeders'], '1' => '1+', '5' => '5+', '10' => '10+', '25' => '25+', '50' => '50+', '100' => '100+'];
$size_options_min = [
    ''            => $lang->browse['opt_min_size'],
    '536870912'   => $fmtGb('0.5'),
    '1073741824'  => $fmtGb('1'),
    '2147483648'  => $fmtGb('2'),
    '5368709120'  => $fmtGb('5'),
    '10737418240' => $fmtGb('10'),
    '21474836480' => $fmtGb('20'),
    '53687091200' => $fmtGb('50'),
];
$size_options_max = ['' => $lang->browse['opt_max_size']] + array_slice($size_options_min, 1, null, true) + ['107374182400' => $fmtGb('100')];
$daysprune_options = [
    0   => $lang->browse['opt_days_any'],
    1   => $lang->browse['opt_days_1'],
    7   => $lang->browse['opt_days_7'],
    30  => $lang->browse['opt_days_30'],
    90  => $lang->browse['opt_days_90'],
    180 => $lang->browse['opt_days_180'],
    365 => $lang->browse['opt_days_365'],
];
$searchTypeLabels = [
    't_both'        => $lang->browse['t_both'],
    't_name'        => $lang->browse['t_name'],
    't_description' => $lang->browse['t_description'],
    't_tags'        => $lang->browse['opt_tags'],
    't_uploader'    => $lang->browse['t_uploader'],
    't_genre'       => $lang->browse['t_genre'],
];
$specialLabels = [
    'mytorrents'   => $lang->browse['qf_my_uploads'],
    'mybookmarks'  => $lang->browse['qf_my_bookmarks'],
    'myreseeds'    => $lang->browse['qf_need_reseed'],
    'weaktorrents' => 'weaktorrents',
];

/** <select> из массива значение => подпись (подписи - текст, экранируются) */
$mkSelect = static function (string $name, array $opts, string $current, string $id = '', string $aria = ''): string {
    $h = '<select class="form-select" name="' . $name . '"' . ($id !== '' ? ' id="' . $id . '"' : '')
        . ($aria !== '' ? ' aria-label="' . htmlspecialchars_uni($aria) . '"' : '') . '>';
    foreach ($opts as $val => $label) {
        $h .= '<option value="' . htmlspecialchars_uni((string)$val) . '"' . ((string)$val === $current ? ' selected' : '') . '>'
            . htmlspecialchars_uni(html_entity_decode((string)$label, ENT_QUOTES, 'UTF-8')) . '</option>';
    }
    return $h . '</select>';
};


/* ═══════════════════════════════════════════════════════════════════════════
   Активные фильтры (чипы с ×)
   ═══════════════════════════════════════════════════════════════════════════ */

$activeFilters = [];
$addFilter = static function (string $param, string $icon, string $tone, string $label) use (&$activeFilters): void {
    $activeFilters[] = ['param' => $param, 'icon' => $icon, 'tone' => $tone, 'label' => $label];
};

if ($keywords !== '') {
    $addFilter('keywords', 'fa-magnifying-glass', 'primary', sprintf($lang->browse['flt_search'], htmlspecialchars_uni($keywords)));
}
if ($search_type !== '' && $search_type !== 't_both') {
    $addFilter('search_type', 'fa-magnifying-glass', 'primary', sprintf($lang->browse['flt_type'], $searchTypeLabels[$search_type]));
}
if ($category) {
    $addFilter('category', 'fa-folder', 'info', $catName !== '' ? htmlspecialchars_uni($catName) : sprintf($lang->browse['flt_category'], $category));
}
if ($size_min_val) {
    $addFilter('size_min', 'fa-hard-drive', 'warning', sprintf($lang->browse['flt_size_min'], mksize($size_min_val)));
}
if ($size_max_val) {
    $addFilter('size_max', 'fa-hard-drive', 'warning', sprintf($lang->browse['flt_size_max'], mksize($size_max_val)));
}
if ($min_seeders_val) {
    $addFilter('min_seeders', 'fa-heart-pulse', 'success', sprintf($lang->browse['flt_seeders_min'], $min_seeders_val));
}
if ($health_filter === 'seeded') {
    $addFilter('health', 'fa-heart-pulse', 'success', $lang->browse['qf_has_seeders']);
}
if ($no_seeders_only) {
    $addFilter('no_seeders', 'fa-heart-pulse', 'success', $lang->browse['flt_no_seeders']);
}
if ($seeders_gt_leechers) {
    $addFilter('seeders_gt_leechers', 'fa-heart-pulse', 'success', $lang->browse['flt_seeders_gt_leechers']);
}
if ($freeleech_only) {
    $addFilter('freeleech_only', 'fa-gift', 'danger', $lang->browse['flt_freeleech_only']);
}
if ($imdb_min > 0) {
    $addFilter('imdb_min', 'fa-star', 'warning', sprintf($lang->browse['flt_imdb_min'], $imdb_min));
}
if ($daysprune > 0) {
    $addFilter('daysprune', 'fa-calendar', 'muted', htmlspecialchars_uni($daysprune_options[$daysprune] ?? sprintf($lang->browse['flt_last_n_days'], $daysprune)));
}
if ($added_from) {
    $addFilter('added_from', 'fa-calendar', 'muted', sprintf($lang->browse['flt_added_from'], htmlspecialchars_uni($added_from_raw)));
}
if ($added_to) {
    $addFilter('added_to', 'fa-calendar', 'muted', sprintf($lang->browse['flt_added_to'], htmlspecialchars_uni($added_to_raw)));
}
if ($special_search !== '') {
    $addFilter('special_search', 'fa-user', 'muted', $specialLabels[$special_search]);
}
if ($hide_downloaded) {
    $addFilter('hide_downloaded', 'fa-eye-slash', 'muted', $lang->browse['flt_hide_downloaded']);
}
if ($include_dead_torrents === 'yes') {
    $addFilter('include_dead_torrents', 'fa-ghost', 'muted', $lang->browse['flt_include_dead']);
}


/* ═══════════════════════════════════════════════════════════════════════════
   WHERE + ссылки пагинации
   ═══════════════════════════════════════════════════════════════════════════ */

$flt       = browse_build_where($F, $myId, $is_mod);
$WHERE     = $flt['where'];
$innerjoin = $flt['join'];
$params    = $flt['params'];
$Links     = $flt['links'];

// base - без сортировки (для заголовков колонок), page_url - с ней (для пагинации)
$baseUrl  = $BASEURL . '/browse.php?' . implode('&amp;', $Links);
$sortPart = $sortBy !== null ? 'sort=' . $sortBy . '&dir=' . $sortDir : '';
$pageUrl  = $baseUrl . ($sortPart !== '' ? '&amp;' . str_replace('&', '&amp;', $sortPart) : '');


/* ═══════════════════════════════════════════════════════════════════════════
   Счётчики (один запрос: всего + цифры плиток и быстрых фильтров)
   ═══════════════════════════════════════════════════════════════════════════ */

$stats = ['total' => 0, 'active' => 0, 'dead' => 0, 'd1' => 0, 'd7' => 0, 'd30' => 0, 'big' => 0, 'fl' => 0];
$statsQuery = $db->sql_query_prepared(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(t.seeders > 0), 0)             AS active,
            COALESCE(SUM(t.seeders = 0), 0)             AS dead,
            COALESCE(SUM(t.added >= ?), 0)              AS d1,
            COALESCE(SUM(t.added >= ?), 0)              AS d7,
            COALESCE(SUM(t.added >= ?), 0)              AS d30,
            COALESCE(SUM(t.size >= 5368709120), 0)      AS big,
            COALESCE(SUM(t.free = 'yes'), 0)            AS fl
       FROM torrents t" . $innerjoin . $WHERE,
    array_merge([TIMENOW - 86400, TIMENOW - 7 * 86400, TIMENOW - 30 * 86400], $params)
);
if ($statsQuery && ($srow = $db->fetch_array($statsQuery))) {
    foreach ($stats as $k => $_) {
        $stats[$k] = (int)($srow[$k] ?? 0);
    }
}
$threadcount = $stats['total'];


/* ═══════════════════════════════════════════════════════════════════════════
   Пагинация + выборка страницы
   ═══════════════════════════════════════════════════════════════════════════ */

$perpage = (int)($CURUSER['torrentsperpage'] ?? 0);
if ($perpage < 1) {
    $perpage = (int)$ts_perpage > 0 ? (int)$ts_perpage : 20;
}
$pages = max(1, (int)ceil($threadcount / $perpage));
$page  = (int)($mybb->input['page'] ?? 1);
if ($page < 1 || $page > $pages) {
    $page = 1;
}
$start     = ($page - 1) * $perpage;
$multipage = multipage($threadcount, $perpage, $page, $pageUrl);

$TotalTorrents = [];
$Query = $db->sql_query_prepared(
    'SELECT t.*, c.name AS catname, u.username, u.usergroup
       FROM torrents t' . $innerjoin . '
       LEFT JOIN categories c ON (t.category = c.id)
       LEFT JOIN users u ON (t.owner = u.id)'
    . $WHERE . ' ORDER BY ' . $orderby . ' LIMIT ?, ?',
    [...$params, $start, $perpage]
);
while ($Query && ($t = $db->fetch_array($Query))) {
    $TotalTorrents[] = $t;
}

// «Уже скачивали» - одним запросом на все торренты страницы
$already_snatched_ids = [];
if ($TotalTorrents) {
    $ids = array_map(static fn(array $t): int => (int)$t['id'], $TotalTorrents);
    $sq  = $db->sql_query_prepared(
        "SELECT torrentid FROM snatched WHERE userid = ? AND finished = 'yes' AND torrentid IN (" . implode(',', array_fill(0, count($ids), '?')) . ')',
        array_merge([$myId], $ids)
    );
    while ($sq && ($sRow = $db->fetch_array($sq))) {
        $already_snatched_ids[(int)$sRow['torrentid']] = true;
    }
}

// Лента свежих постеров - только на первой странице без фильтров
$recentItems = [];
if ($page === 1 && !$activeFilters && $sortBy === null) {
    $rq = $db->sql_query_prepared(
        "SELECT id, name, t_image FROM torrents
          WHERE t_image != '' AND visible = 'yes' AND banned = 'no'
          ORDER BY added DESC LIMIT ?",
        [15]
    );
    while ($rq && ($r = $db->fetch_array($rq))) {
        $recentItems[] = $r;
    }
}


/* ═══════════════════════════════════════════════════════════════════════════
   Строки таблицы
   ═══════════════════════════════════════════════════════════════════════════ */

$hlKeywords = ($keywords !== '' && in_array($search_type, ['', 't_both', 't_name'], true)) ? $keywords : '';
$colCount   = $is_mod ? 8 : 7;
$rowsHtml   = '';

foreach ($TotalTorrents as $Torrent) {
    $tid      = (int)$Torrent['id'];
    $s        = (int)$Torrent['seeders'];
    $l        = (int)$Torrent['leechers'];
    $name     = (string)$Torrent['name'];
    $nameEsc  = htmlspecialchars_uni($name);
    $catname  = htmlspecialchars_uni((string)($Torrent['catname'] ?? ''));
    $catIcon  = htmlspecialchars_uni($catIconMap[(int)$Torrent['category']] ?? 'fa-solid fa-folder');
    $link     = get_torrent_link($tid);
    $peers    = $link . (str_contains($link, '?') ? '&amp;' : '?') . 'tab=peers';
    $dl       = get_download_link($tid);
    $size     = mksize($Torrent['size']);
    $snatched = isset($already_snatched_ids[$tid]);

    // Теги
    $tagsHtml = '';
    foreach (explode(',', (string)($Torrent['tags'] ?? '')) as $tag) {
        $tag = trim($tag);
        if ($tag === '') {
            continue;
        }
        $tagsHtml .= '<a href="' . $BASEURL . '/browse.php?search_type=t_tags&amp;keywords=' . urlencode($tag) . '" class="br-tag">#'
            . htmlspecialchars_uni($tag) . '</a>';
    }

    // Постер
    $image = (string)($Torrent['t_image'] ?? '');
    $poster = $image !== ''
        ? '<img src="' . htmlspecialchars_uni($image) . '" alt="" class="torrent-poster" loading="lazy" onerror="this.classList.add(\'is-broken\')">'
        : '<span class="torrent-poster-placeholder"><i class="' . $catIcon . '" aria-hidden="true"></i></span>';

    // Поповер скачивания (без кнопок - их вырезает санитайзер Bootstrap)
    $popHtml = '<div class="br-pop"><div class="br-pop__name">' . $nameEsc . '</div>'
        . '<div class="br-pop__line"><i class="fa-solid fa-hard-drive"></i>' . $size . ' · '
        . htmlspecialchars_uni(sprintf($lang->browse['pop_files'], ts_nf($Torrent['numfiles']))) . '</div>'
        . '<div class="br-pop__line"><span class="text-success"><i class="fa-solid fa-arrow-up"></i>' . ts_nf($s) . '</span>'
        . ' <span class="text-danger ms-2"><i class="fa-solid fa-arrow-down"></i>' . ts_nf($l) . '</span></div>'
        . '<div class="br-pop__hint">' . htmlspecialchars_uni($lang->browse['pop_download_hint']) . '</div></div>';

    // Автор
    if ($Torrent['anonymous'] === 'yes' && !$is_mod && (int)$Torrent['owner'] !== $myId) {
        $uploader = '<span class="br-anon">' . $lang->global['anonymous'] . '</span>';
    } elseif (empty($Torrent['username'])) {
        $uploader = '<span class="text-body-secondary">—</span>';
    } else {
        $uploader = '<a href="' . get_profile_link((int)$Torrent['owner']) . '">'
            . format_name(htmlspecialchars_uni((string)$Torrent['username']), (int)$Torrent['usergroup']) . '</a>'
            . ($Torrent['anonymous'] === 'yes' ? ' <i class="fa-solid fa-user-secret text-body-secondary" title="' . htmlspecialchars_uni(strip_tags($lang->global['anonymous'])) . '"></i>' : '');
    }

    $flags = GetTorrentTags($Torrent);

    $rowsHtml .= '
<tr class="torrent-row' . ($snatched ? ' torrent-row-snatched' : '') . ($s === 0 && $l === 0 ? ' is-dead' : '') . '"'
        . ($snatched ? ' title="' . htmlspecialchars_uni($lang->browse['tip_already_snatched']) . '"' : '')
        . ' data-id="' . $tid . '" data-seeders="' . $s . '" data-leechers="' . $l . '">
  <td class="torrent-poster-cell">
    <a href="' . $link . '" class="poster-link"' . ($image !== '' ? ' data-zoom="' . htmlspecialchars_uni($image) . '"' : '') . '>' . $poster . '</a>
  </td>
  <td class="br-name-cell">
    <div class="torrent-title">
      <a href="' . $link . '" class="torrent-name-link" title="' . $nameEsc . '">' . br_highlight($name, $hlKeywords) . '</a>
      <div class="torrent-actions-inline">
        <a href="' . $dl . '" class="br-dl download-popover" aria-label="' . htmlspecialchars_uni($lang->browse['pop_download_title']) . '"
           data-bs-toggle="popover" data-bs-placement="top" data-bs-html="true" data-bs-trigger="hover focus"
           data-bs-title="' . htmlspecialchars_uni($lang->browse['pop_download_title']) . '"
           data-bs-content="' . htmlspecialchars($popHtml, ENT_QUOTES) . '"><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i></a>
        <span id="bookmark' . $tid . '" class="br-bm">' . get_torrent_bookmark_state($myId, $tid) . '</span>
      </div>
    </div>
    <div class="torrent-meta">
      <a href="' . get_category_link((int)$Torrent['category']) . '" class="br-catchip"><i class="' . $catIcon . '" aria-hidden="true"></i>' . $catname . '</a>
      <span class="torrent-date"><i class="fa-regular fa-calendar" aria-hidden="true"></i>' . my_datee('relative', $Torrent['added']) . '</span>'
      . ($flags ? '<span class="torrent-flags">' . $flags . '</span>' : '') . '
    </div>
    <div class="br-mstats">
      <span><i class="fa-solid fa-hard-drive"></i>' . $size . '</span>
      <span class="text-success"><i class="fa-solid fa-arrow-up"></i>' . ts_nf($s) . '</span>
      <span class="text-danger"><i class="fa-solid fa-arrow-down"></i>' . ts_nf($l) . '</span>
    </div>'
    . ($tagsHtml !== '' ? '<div class="br-tags">' . $tagsHtml . '</div>' : '') . '
  </td>
  <td class="torrent-stat-cell br-col-md"><span class="br-size">' . $size . '</span></td>
  <td class="torrent-stat-cell br-col-md">
    <a href="' . $BASEURL . '/viewsnatches.php?id=' . $tid . '" class="br-stat" title="' . htmlspecialchars_uni($lang->browse['tip_snatched_count']) . '">
      <i class="fa-solid fa-circle-check" aria-hidden="true"></i>' . ts_nf($Torrent['times_completed']) . '</a>
  </td>
  <td class="torrent-stat-cell br-col-sm">
    <span id="seeders_' . $tid . '"><a href="' . $peers . '#seeders" class="br-stat br-stat--seed' . ($s === 0 ? ' br-stat--zero' : '') . '" title="' . htmlspecialchars_uni($lang->browse['tip_current_seeders']) . '">
      <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>' . ts_nf($s) . '</a></span>
  </td>
  <td class="torrent-stat-cell br-col-sm">
    <span id="leechers_' . $tid . '"><a href="' . $peers . '#leechers" class="br-stat br-stat--leech' . ($l === 0 ? ' br-stat--zero' : '') . '" title="' . htmlspecialchars_uni($lang->browse['tip_current_leechers']) . '">
      <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>' . ts_nf($l) . '</a></span>
  </td>
  <td class="torrent-uploader-cell br-col-lg">' . $uploader . '</td>'
  . ($is_mod ? '
  <td class="br-check-cell">
    <div class="form-check form-switch m-0">
      <input class="form-check-input" type="checkbox" role="switch" id="torrentid_' . $tid . '" name="torrentid[]" value="' . $tid . '"
             aria-label="' . $nameEsc . '"
             data-title="' . $nameEsc . '" data-image="' . htmlspecialchars_uni($image) . '"
             data-size="' . (int)$Torrent['size'] . '" data-seeders="' . $s . '" data-leechers="' . $l . '"
             data-category="' . (int)$Torrent['category'] . '" data-catname="' . $catname . '">
    </div>
  </td>' : '') . '
</tr>';
}

if ($rowsHtml === '') {
    $rowsHtml = '
<tr><td colspan="' . $colCount . '">
  <div class="st-empty">
    <div class="st-ico st-soft-muted"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></div>
    <div class="fw-semibold">' . $lang->browse['empty_title'] . '</div>
    <div class="small mb-3">' . sprintf($lang->browse['empty_hint'], '<a href="' . $BASEURL . '/browse.php">' . $lang->browse['empty_reset_link'] . '</a>') . '</div>
    <a href="' . $BASEURL . '/browse.php" class="btn btn-sm btn-primary st-pill"><i class="fa-solid fa-rotate-left me-1"></i>' . $lang->browse['btn_reset_filters'] . '</a>
  </div>
</td></tr>';
}


/* ═══════════════════════════════════════════════════════════════════════════
   Вывод
   ═══════════════════════════════════════════════════════════════════════════ */

$toastOk  = is_string($_GET['mod_success'] ?? null) ? (string)$_GET['mod_success'] : '';
$toastErr = is_string($_GET['mod_error'] ?? null) ? (string)$_GET['mod_error'] : '';

stdhead($lang->browse['btitle']);

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=7">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/browse.css?ver=11">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/airbnb.css">';
if ($is_mod) {
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">';
}

$e = static fn($v): string => htmlspecialchars_uni((string)$v);
?>
<div class="container-md st-page br-page" id="brPage"
     data-total="<?= $threadcount ?>"
     data-has-filters="<?= $activeFilters ? '1' : '0' ?>"
     data-toast-ok="<?= $e($toastOk) ?>"
     data-toast-err="<?= $e($toastErr) ?>">

    <!-- Шапка -->
    <header class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-primary"><i class="fa-solid fa-magnet" aria-hidden="true"></i></div>
        <div class="min-w-0 flex-grow-1">
            <h1 class="st-title"><?= $lang->browse['btitle'] ?></h1>
            <div class="st-head__meta">
                <span><i class="fa-solid fa-folder"></i><?= $catName !== '' ? $e($catName) : $lang->browse['alltypes'] ?></span>
                <?php if ($keywords !== ''): ?>
                    <span><i class="fa-solid fa-magnifying-glass"></i><?= $e($keywords) ?></span>
                <?php endif; ?>
                <span><i class="fa-solid fa-layer-group"></i><?= sprintf($lang->browse['res_found'], '<b>' . ts_nf($threadcount) . '</b>') ?></span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 ms-auto">
            <a href="<?= $BASEURL ?>/upload.php" class="btn btn-primary st-pill"><i class="fa-solid fa-plus me-1"></i><?= $lang->browse['newtorrent'] ?></a>
            <a href="<?= $BASEURL ?>/getrss.php?<?= $e(http_build_query(array_diff_key($_GET, ['page' => 1, 'mod_success' => 1, 'mod_error' => 1]))) ?>" class="btn btn-outline-warning st-pill" title="<?= $e($lang->browse['btn_rss']) ?>">
                <i class="fa-solid fa-rss"></i><span class="d-none d-sm-inline ms-1"><?= $lang->browse['btn_rss'] ?></span>
            </a>
        </div>
    </header>

    <?= $promoNotice ?>

    <div id="brRestoreSlot"></div>

    <!-- Плитки -->
    <?php
    $pct = static fn(int $n): int => $threadcount > 0 ? (int)round($n * 100 / $threadcount) : 0;
    $kpis = [
        ['tone' => 'primary', 'icon' => 'fa-layer-group',     'bg' => 'fa-magnet',       'href' => null,
         'val' => $threadcount,     'lbl' => $e($lang->browse['lbl_kpi_found'] ?? 'Found'),
         'sub' => $e($lang->browse['opt_days_30']) . ' · <b>' . ts_nf($stats['d30']) . '</b>', 'bar' => null],
        ['tone' => 'success', 'icon' => 'fa-heart-pulse',     'bg' => 'fa-seedling',     'href' => br_url(['health' => 'seeded', 'no_seeders' => null]),
         'val' => $stats['active'], 'lbl' => $lang->browse['qf_active'],
         'sub' => '<b>' . $pct($stats['active']) . '%</b>', 'bar' => $pct($stats['active'])],
        ['tone' => 'danger',  'icon' => 'fa-skull-crossbones', 'bg' => 'fa-ghost',       'href' => br_url(['no_seeders' => '1', 'health' => null]),
         'val' => $stats['dead'],   'lbl' => $lang->browse['flt_no_seeders'],
         'sub' => '<b>' . $pct($stats['dead']) . '%</b>', 'bar' => $pct($stats['dead'])],
        ['tone' => 'info',    'icon' => 'fa-clock',           'bg' => 'fa-calendar-day', 'href' => br_url(['daysprune' => '1']),
         'val' => $stats['d1'],     'lbl' => $e($lang->browse['opt_days_1']),
         'sub' => $e($lang->browse['opt_days_7']) . ' · <b>' . ts_nf($stats['d7']) . '</b>', 'bar' => null],
    ];
    ?>
    <div class="br-kpis mb-3">
        <?php foreach ($kpis as $k):
            $tag = $k['href'] !== null ? 'a' : 'div'; ?>
        <<?= $tag ?> class="st-kpi st-kpi--muted br-kpi br-kpi--<?= $k['tone'] ?>"<?= $k['href'] !== null ? ' href="' . $e($k['href']) . '"' : '' ?>>
            <div class="st-ico st-soft-<?= $k['tone'] ?>"><i class="fa-solid <?= $k['icon'] ?>" aria-hidden="true"></i></div>
            <div class="min-w-0 flex-grow-1">
                <div class="st-kpi__val"><?= ts_nf($k['val']) ?></div>
                <div class="st-kpi__lbl"><?= $k['lbl'] ?></div>
                <div class="br-kpi__sub"><?= $k['sub'] ?></div>
                <?php if ($k['bar'] !== null): ?>
                    <div class="br-kpi__bar" role="presentation"><span style="width: <?= $k['bar'] ?>%"></span></div>
                <?php endif; ?>
            </div>
            <i class="fa-solid <?= $k['bg'] ?> st-kpi__bg" aria-hidden="true"></i>
        </<?= $tag ?>>
        <?php endforeach; ?>
    </div>

    <?php if ($recentItems): ?>
    <!-- Свежие постеры -->
    <section class="st-card br-recent mb-3">
        <div class="st-card__head">
            <span class="st-ico st-soft-warning"><i class="fa-solid fa-fire" aria-hidden="true"></i></span>
            <?= $lang->browse['recent_torrents'] ?>
        </div>
        <div class="br-recent__strip">
            <?php foreach ($recentItems as $r): ?>
                <a href="<?= get_torrent_link((int)$r['id']) ?>" class="br-recent__item" title="<?= $e($r['name']) ?>">
                    <img src="<?= $e($r['t_image']) ?>" alt="" loading="lazy" onerror="this.parentNode.hidden=true">
                    <span class="br-recent__name"><?= $e($r['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($catTiles !== ''): ?>
    <!-- Категории -->
    <section class="st-card br-cats mb-3">
        <div class="st-card__head">
            <span class="st-ico st-soft-info"><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i></span>
            <?= $lang->browse['tcategory'] ?>
            <button type="button" class="ms-auto st-icon-btn border-0 br-collapse-btn" data-bs-toggle="collapse" data-bs-target="#brCatsBody"
                    aria-expanded="true" aria-controls="brCatsBody" aria-label="<?= $e(strip_tags($lang->browse['tcategory'])) ?>">
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
        </div>
        <div class="collapse show" id="brCatsBody" data-br-remember="brCats">
            <div class="br-cats__grid"><?= $catTiles ?></div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Поиск -->
    <form method="get" action="<?= $BASEURL ?>/browse.php" name="searchtorrent" id="searchtorrent" class="st-card br-search mb-3">
        <div class="st-card__head">
            <span class="st-ico st-soft-primary"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
            <?= $lang->browse['tsearch'] ?>
        </div>
        <div class="st-card__body">
            <div class="row g-2">
                <div class="col-12 col-lg-8">
                    <div class="position-relative">
                        <input type="search" class="form-control br-q" id="torrent-search" name="keywords"
                               placeholder="<?= $e($lang->browse['ph_search']) ?>" aria-label="<?= $e($lang->browse['ph_search']) ?>"
                               autocomplete="off" value="<?= $e($keywords) ?>">
                        <div id="autocomplete-results" class="dropdown-menu"></div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <?= $mkSelect('search_type', $searchTypeLabels, $search_type !== '' ? $search_type : 't_both', 'search_type', strip_tags($lang->browse['type'])) ?>
                </div>

                <div class="col-12 col-sm-6 col-lg-3"><?= $catdropdown ?></div>
                <div class="col-6 col-lg-3">
                    <?= $mkSelect('include_dead_torrents', ['no' => $lang->browse['incdead2'], 'yes' => $lang->browse['incdead1']], $include_dead_torrents === 'yes' ? 'yes' : 'no') ?>
                </div>
                <div class="col-6 col-lg-3"><?= $mkSelect('size_min', $size_options_min, $size_min_val ? (string)$size_min_val : '') ?></div>
                <div class="col-6 col-lg-3"><?= $mkSelect('size_max', $size_options_max, $size_max_val ? (string)$size_max_val : '') ?></div>

                <div class="col-6 col-lg-3"><?= $mkSelect('min_seeders', $seeders_options, $min_seeders_val ? (string)$min_seeders_val : '') ?></div>
                <div class="col-6 col-lg-3"><?= $mkSelect('daysprune', $daysprune_options, (string)$daysprune, 'daysprune') ?></div>
                <div class="col-6 col-lg-3">
                    <input type="text" id="added_from_input" class="form-control" name="added_from" autocomplete="off"
                           placeholder="<?= $e($lang->browse['ph_added_from']) ?>" aria-label="<?= $e($lang->browse['ph_added_from']) ?>" value="<?= $e($added_from ? $added_from_raw : '') ?>">
                </div>
                <div class="col-6 col-lg-3">
                    <input type="text" id="added_to_input" class="form-control" name="added_to" autocomplete="off"
                           placeholder="<?= $e($lang->browse['ph_added_to']) ?>" aria-label="<?= $e($lang->browse['ph_added_to']) ?>" value="<?= $e($added_to ? $added_to_raw : '') ?>">
                </div>
            </div>
            <?php if (!$smartBrowse): ?><input type="hidden" name="smart_browse" value="0"><?php endif; ?>
        </div>

        <?php if ($activeFilters): ?>
        <div class="br-active">
            <?php foreach ($activeFilters as $f): ?>
                <a href="<?= $e(br_url([$f['param'] => null])) ?>" class="st-badge st-soft-<?= $f['tone'] ?> br-active__chip">
                    <i class="fa-solid <?= $f['icon'] ?>" aria-hidden="true"></i><?= $f['label'] ?><i class="fa-solid fa-xmark br-active__x" aria-hidden="true"></i>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="st-toolbar st-toolbar--bottom">
            <a href="<?= $BASEURL ?>/browse.php" class="btn btn-outline-secondary st-pill"><i class="fa-solid fa-rotate-left me-1"></i><?= $lang->browse['btn_reset_filters'] ?></a>
            <button type="submit" class="btn btn-primary st-pill"><i class="fa-solid fa-magnifying-glass me-1"></i><?= $lang->browse['btn_search'] ?></button>
        </div>
    </form>

    <?php if ($smartBrowse):
        $qf = static function (array $changes, string $icon, string $label, ?int $count = null): string {
            $active = br_preset_active($changes);
            return '<a class="btn btn-sm st-pill ' . ($active ? 'btn-primary' : 'btn-outline-secondary') . '"'
                . ($active ? ' aria-current="true"' : '') . ' href="' . htmlspecialchars_uni(br_url($changes + ['smart_browse' => '1'])) . '">'
                . '<i class="' . $icon . ' me-1" aria-hidden="true"></i>' . $label
                . ($count !== null ? ' <span class="br-qf__cnt">' . ts_nf($count) . '</span>' : '') . '</a>';
        };
        $clearAll = array_fill_keys(['min_seeders', 'daysprune', 'size_min', 'size_max', 'health', 'freeleech_only', 'no_seeders',
            'seeders_gt_leechers', 'hide_downloaded', 'special_search', 'include_dead_torrents', 'imdb_min', 'added_from', 'added_to', 'sort', 'dir'], null);
        $groups = [
            $lang->browse['sec_qf_quick'] => [
                $qf(['health' => 'seeded'], 'fa-solid fa-heart-pulse', $lang->browse['qf_has_seeders'], $stats['active']),
                $qf(['sort' => 'seeders', 'dir' => 'desc'], 'fa-solid fa-arrow-down-wide-short', $lang->browse['qf_most_seeders']),
                $qf(['daysprune' => '7'], 'fa-regular fa-clock', $e($lang->browse['opt_days_7']), $stats['d7']),
                $qf(['daysprune' => '30'], 'fa-regular fa-calendar', $e($lang->browse['opt_days_30']), $stats['d30']),
                $qf(['size_min' => '5368709120'], 'fa-solid fa-database', sprintf($lang->browse['qf_size_plus'], 5), $stats['big']),
                $qf(['freeleech_only' => '1'], 'fa-solid fa-gift', $lang->browse['flt_freeleech_only'], $stats['fl']),
            ],
            $lang->browse['sec_qf_popular'] => [
                $qf(['sort' => 'snatched', 'dir' => 'desc'], 'fa-solid fa-fire', $lang->browse['qf_most_downloaded']),
                $qf(['sort' => 'leechers', 'dir' => 'desc'], 'fa-solid fa-users', $lang->browse['qf_most_leeched']),
            ],
            $lang->browse['sec_qf_health'] => [
                $qf(['no_seeders' => '1'], 'fa-solid fa-skull-crossbones', $lang->browse['flt_no_seeders']),
                $qf(['seeders_gt_leechers' => '1'], 'fa-solid fa-scale-balanced', $lang->browse['flt_seeders_gt_leechers']),
            ],
            $lang->browse['sec_qf_fresh'] => [
                $qf(['daysprune' => '1'], 'fa-solid fa-star', $lang->browse['qf_added_today']),
                $qf(['daysprune' => '3'], 'fa-regular fa-clock', $lang->browse['qf_last_3_days']),
                $qf(['size_max' => '1073741824'], 'fa-solid fa-mobile-screen', sprintf($lang->browse['qf_small_torrents'], 1)),
                $qf(['size_min' => '10737418240'], 'fa-solid fa-database', sprintf($lang->browse['qf_size_plus'], 10)),
                $qf(['size_min' => '53687091200'], 'fa-solid fa-database', sprintf($lang->browse['qf_size_plus'], 50)),
            ],
            $lang->browse['sec_qf_authorship'] => [
                $qf(['special_search' => 'mytorrents'], 'fa-solid fa-user', $lang->browse['qf_my_uploads']),
                $qf(['special_search' => 'mybookmarks'], 'fa-solid fa-bookmark', $lang->browse['qf_my_bookmarks']),
                $qf(['special_search' => 'myreseeds'], 'fa-solid fa-rotate', $lang->browse['qf_need_reseed']),
                $qf(['hide_downloaded' => '1'], 'fa-solid fa-eye-slash', $lang->browse['flt_hide_downloaded']),
                $qf(['include_dead_torrents' => 'yes'], 'fa-solid fa-ghost', $lang->browse['flt_include_dead']),
            ],
            $lang->browse['sec_qf_rating'] => [
                $qf(['imdb_min' => '7'], 'fa-solid fa-star', sprintf($lang->browse['flt_imdb_min'], 7)),
                $qf(['imdb_min' => '8'], 'fa-solid fa-star', sprintf($lang->browse['flt_imdb_min'], 8)),
                $qf(['imdb_min' => '9'], 'fa-solid fa-star', sprintf($lang->browse['flt_imdb_min'], 9)),
            ],
        ];
    ?>
    <!-- Быстрые фильтры -->
    <section class="st-card br-qf mb-3">
        <div class="st-card__head">
            <span class="st-ico br-soft-purple"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span>
            <div class="min-w-0">
                <?= $lang->browse['sec_quick_filters'] ?>
                <div class="br-qf__hint"><?= $lang->browse['hint_quick_filters'] ?></div>
            </div>
            <a href="<?= $e(br_url($clearAll)) ?>" class="ms-auto btn btn-sm btn-outline-secondary st-pill"><i class="fa-solid fa-xmark me-1"></i><span class="d-none d-sm-inline"><?= $lang->browse['qf_clear'] ?></span></a>
            <button type="button" class="st-icon-btn border-0 br-collapse-btn" data-bs-toggle="collapse" data-bs-target="#brQfBody"
                    aria-expanded="true" aria-controls="brQfBody" aria-label="<?= $e(strip_tags($lang->browse['sec_quick_filters'])) ?>">
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
        </div>
        <div class="collapse show" id="brQfBody" data-br-remember="brQf">
            <div class="st-card__body br-qf__body">
                <?php
                $groupIcons = [['fa-bolt', 'primary'], ['fa-fire', 'warning'], ['fa-heart-pulse', 'success'],
                               ['fa-calendar-plus', 'info'], ['fa-user', 'purple'], ['fa-star', 'warning']];
                $gi = 0;
                foreach ($groups as $label => $buttons):
                    [$gIcon, $gTone] = $groupIcons[$gi++] ?? ['fa-filter', 'muted']; ?>
                    <div class="br-qf__group">
                        <div class="br-qf__label"><span class="br-qf__gico <?= $gTone === 'purple' ? 'br-soft-purple' : 'st-soft-' . $gTone ?>"><i class="fa-solid <?= $gIcon ?>" aria-hidden="true"></i></span><?= $label ?></div>
                        <div class="br-qf__btns"><?= implode('', $buttons) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($is_mod): ?>
    <form method="post" action="<?= $BASEURL ?>/admin/index.php?act=manage_torrents" name="manage_torrents" id="manage_torrents">
        <input type="hidden" name="do" value="update">
        <input type="hidden" name="my_post_key" value="<?= $e($mybb->post_code) ?>">
        <input type="hidden" name="return" value="yes">
        <input type="hidden" name="return_address" value="<?= $e($BASEURL . '/browse.php?page=' . $page . '&' . implode('&', $Links) . ($sortPart !== '' ? '&' . $sortPart : '')) ?>">
    <?php endif; ?>

    <!-- Результаты -->
    <section class="st-card br-results mb-3">
        <div class="st-card__head br-results__head">
            <span class="st-ico st-soft-success"><i class="fa-solid fa-list" aria-hidden="true"></i></span>
            <span><?= sprintf($lang->browse['res_found'], '<b>' . ts_nf($threadcount) . '</b>') ?></span>
            <?php if ($search_type !== '' && $search_type !== 't_both' && $keywords !== ''): ?>
                <span class="br-results__sub">· <?= sprintf($lang->browse['res_by'], $searchTypeLabels[$search_type]) ?></span>
            <?php endif; ?>

            <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
                <?php if ($is_mod): ?>
                    <span class="st-badge st-soft-primary" id="totalSelectedBadge">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i><?= sprintf($lang->browse['mod_selected'], '<span id="selectedCountDisplay" aria-live="polite">0</span>') ?>
                    </span>
                    <span class="st-badge st-soft-muted d-none d-md-inline-flex">
                        <i class="fa-solid fa-list" aria-hidden="true"></i><?= sprintf($lang->browse['mod_total'], '<span id="totalTorrentsCount">' . $threadcount . '</span>') ?>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-primary st-pill" data-br-select-all>
                        <i class="fa-solid fa-check-double me-1"></i><span class="d-none d-lg-inline"><?= $lang->browse['btn_select_all_filtered'] ?></span>
                    </button>
                    <button type="button" class="btn btn-sm btn-primary st-pill" data-bs-toggle="modal" data-bs-target="#moderationModal">
                        <i class="fa-solid fa-shield-halved me-1"></i><span class="d-none d-sm-inline"><?= $lang->browse['pane_moderation'] ?></span>
                    </button>
                <?php endif; ?>
                <div class="btn-group btn-group-sm br-view" role="group">
                    <button type="button" class="btn btn-outline-secondary" id="smartViewTable" aria-pressed="true" title="<?= $e($lang->browse['btn_view_table'] ?? 'Table') ?>">
                        <i class="fa-solid fa-list" aria-hidden="true"></i><span class="visually-hidden"><?= $e($lang->browse['btn_view_table'] ?? 'Table') ?></span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="smartViewCards" aria-pressed="false" title="<?= $e($lang->browse['btn_view_cards'] ?? 'Cards') ?>">
                        <i class="fa-solid fa-grip" aria-hidden="true"></i><span class="visually-hidden"><?= $e($lang->browse['btn_view_cards'] ?? 'Cards') ?></span>
                    </button>
                </div>
            </div>
        </div>

        <?php if ($threadcount > $perpage): ?>
            <div class="st-toolbar st-pager br-pager-top"><?= $multipage ?></div>
        <?php endif; ?>

        <div id="smartTorrentCards" class="br-cards" hidden aria-live="polite"></div>

        <div id="smartTorrentTableWrap" class="table-responsive">
            <div id="listtorrents">
                <table class="table br-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="br-th-poster"><span class="visually-hidden"><?= $lang->browse['type'] ?></span></th>
                            <th scope="col"><?= br_sort_link($lang->browse['hdr_name'], 'name', $baseUrl, $sortBy, $sortDir) ?>
                                <span class="br-th-sep">·</span><?= br_sort_link($lang->browse['added'], 'added', $baseUrl, $sortBy, $sortDir) ?></th>
                            <th scope="col" class="text-center br-col-md"><?= br_sort_link($lang->browse['sortby6'], 'size', $baseUrl, $sortBy, $sortDir) ?></th>
                            <th scope="col" class="text-center br-col-md"><?= br_sort_link($lang->browse['sortby7'], 'snatched', $baseUrl, $sortBy, $sortDir) ?></th>
                            <th scope="col" class="text-center br-col-sm"><?= br_sort_link($lang->browse['sortby4'], 'seeders', $baseUrl, $sortBy, $sortDir) ?></th>
                            <th scope="col" class="text-center br-col-sm"><?= br_sort_link($lang->browse['sortby5'], 'leechers', $baseUrl, $sortBy, $sortDir) ?></th>
                            <th scope="col" class="br-col-lg"><?= $lang->browse['sortby8'] ?></th>
                            <?php if ($is_mod): ?>
                            <th scope="col" class="br-check-cell">
                                <div class="form-check form-switch m-0" title="<?= $e($lang->browse['tip_select_all_page']) ?>">
                                    <input class="form-check-input" type="checkbox" role="switch" id="checkAllSwitchVisible" aria-label="<?= $e($lang->browse['tip_select_all_page']) ?>">
                                </div>
                            </th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?= $rowsHtml ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($threadcount > $perpage): ?>
            <div class="st-toolbar st-toolbar--bottom st-pager"><?= $multipage ?></div>
        <?php endif; ?>
    </section>

    <?php if ($is_mod): ?>
    <!-- Модерация -->
    <div class="modal fade br-modal" id="moderationModal" tabindex="-1" aria-labelledby="moderationModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content mod-modal-content">
          <div class="modal-header mod-modal-header">
            <span class="st-ico st-soft-primary me-2"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
            <h5 class="modal-title" id="moderationModalLabel"><?= $lang->browse['pane_moderation'] ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $e($lang->browse['btn_close']) ?>"></button>
          </div>
          <div class="modal-body mod-modal-body">
            <div class="row g-3">
              <div class="col-md-3 mod-poster-column">
                <div class="mod-poster-container" id="modPosterContainer">
                  <div class="mod-poster-placeholder" id="modPosterPlaceholder">
                    <i class="fa-solid fa-images fa-3x" aria-hidden="true"></i>
                    <p class="mt-2 small mb-0"><?= $lang->browse['hint_select_to_preview'] ?></p>
                  </div>
                  <div class="mod-poster-wrapper" id="modPosterWrapper" style="display:none;">
                    <img id="modPosterPreview" src="" alt="<?= $e($lang->browse['alt_torrent_poster']) ?>" class="mod-poster-img">
                    <div class="mod-poster-count" id="modPosterCount"></div>
                  </div>
                </div>
              </div>

              <div class="col-md-9">
                <div class="mod-selection-info alert alert-info alert-dismissible fade show" role="alert">
                  <i class="fa-solid fa-circle-info me-2" aria-hidden="true"></i><?= sprintf($lang->browse['mod_torrents_selected'], '<strong id="modalSelectedCount" aria-live="polite">0</strong>') ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= $e($lang->browse['btn_close']) ?>"></button>
                </div>

                <div class="mod-progress-container mb-2" id="modProgressContainer" style="display:none;">
                  <div class="d-flex justify-content-between small">
                    <span><?= sprintf($lang->browse['mod_progress'], '<strong id="selectedCount2">0</strong>', '<span id="totalCount2">0</span>') ?></span>
                    <span id="progressPercentage">0%</span>
                  </div>
                  <div class="progress"><div id="selectionProgress" class="progress-bar" style="width:0%;"></div></div>
                </div>

                <div class="mod-restore-info" id="modRestoreInfo" style="display:none;">
                  <i class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></i>
                  <span id="modRestoreMessage"><?= $lang->browse['mod_restored_default'] ?></span>
                  <button type="button" class="btn-close btn-close-sm float-end" id="modRestoreCloseBtn" aria-label="<?= $e($lang->browse['btn_close']) ?>"></button>
                </div>

                <div class="mod-summary-stats" id="modSummaryStats" style="display:none;">
                  <div class="mod-summary-stat"><i class="fa-solid fa-hard-drive" aria-hidden="true"></i><span id="modSummarySize">0 B</span></div>
                  <div class="mod-summary-stat"><i class="fa-solid fa-arrow-up text-success" aria-hidden="true"></i><span id="modSummarySeeders">0</span></div>
                  <div class="mod-summary-stat"><i class="fa-solid fa-arrow-down text-danger" aria-hidden="true"></i><span id="modSummaryLeechers">0</span></div>
                  <div class="mod-summary-stat"><i class="fa-solid fa-folder" aria-hidden="true"></i><span id="modSummaryCategories">0</span></div>
                </div>

                <div id="selectedTorrentsList" class="mod-selected-list" style="display:none;"></div>

                <label class="mod-label" for="actiontype"><i class="fa-solid fa-bolt me-1" aria-hidden="true"></i><?= $lang->browse['lbl_choose_action'] ?></label>
                <select class="form-select mod-select" name="actiontype" id="actiontype">
                  <option value="0"><?= $e($lang->browse['opt_select_action']) ?></option>
                  <optgroup label="<?= $e($lang->browse['opt_grp_torrent']) ?>">
                    <option value="move">📁 <?= $e($lang->browse['opt_act_move']) ?></option>
                    <option value="delete">🗑️ <?= $e($lang->browse['opt_act_delete']) ?></option>
                    <option value="sticky">📌 <?= $e($lang->browse['opt_act_sticky']) ?></option>
                    <option value="visible">👁️ <?= $e($lang->browse['opt_act_visible']) ?></option>
                    <option value="banned">🚫 <?= $e($lang->browse['opt_act_banned']) ?></option>
                    <option value="nuke">☢️ <?= $e($lang->browse['opt_act_nuke']) ?></option>
                    <option value="openclose">💬 <?= $e($lang->browse['opt_act_openclose']) ?></option>
                  </optgroup>
                  <optgroup label="<?= $e($lang->browse['opt_grp_promo']) ?>">
                    <option value="free">🎁 <?= $e($lang->browse['opt_act_free']) ?></option>
                    <option value="silver">🥈 <?= $e($lang->browse['opt_act_silver']) ?></option>
                    <option value="doubleupload">⏫ <?= $e($lang->browse['opt_act_doubleupload']) ?></option>
                    <option value="thirtypercent">🟣 <?= $e($lang->browse['opt_act_thirtypercent']) ?></option>
                  </optgroup>
                  <optgroup label="<?= $e($lang->browse['opt_grp_other']) ?>">
                    <option value="anonymous">🎭 <?= $e($lang->browse['opt_act_anonymous']) ?></option>
                    <option value="request">📩 <?= $e($lang->browse['opt_act_request']) ?></option>
                    <option value="resetrating">⭐ <?= $e($lang->browse['opt_act_resetrating']) ?></option>
                  </optgroup>
                </select>

                <div id="movetorrent" class="mod-move-block" style="display:none;">
                  <div class="mod-move-container">
                    <label class="mod-move-label" for="br_move_category"><i class="fa-solid fa-folder-open me-1" aria-hidden="true"></i><?= $lang->browse['lbl_move_to'] ?></label>
                    <?= $movedropdown ?>
                  </div>
                  <div id="modMoveCategoryWarning" class="mod-move-mismatch-warning" style="display:none;">
                    <i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i>
                    <span id="modMoveCategoryWarningText"><?= $lang->browse['mod_mixed_categories'] ?></span>
                  </div>
                </div>

                <div id="actionInfo" class="mod-action-info" style="display:none;">
                  <div class="alert alert-warning mt-3 mb-0">
                    <i class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></i><span id="actionDescription"><?= $lang->browse['mod_action_default'] ?></span>
                  </div>
                </div>

                <div class="mod-hint"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span><?= $lang->browse['hint_mod'] ?></span></div>
              </div>
            </div>
          </div>
          <div class="modal-footer mod-modal-footer">
            <button type="button" class="btn btn-sm btn-outline-danger st-pill me-auto" id="clearAllSelectionsBtn">
              <i class="fa-solid fa-trash-can me-1" aria-hidden="true"></i><?= $lang->browse['btn_clear_all'] ?>
            </button>
            <button type="button" class="btn btn-outline-secondary st-pill" data-bs-dismiss="modal">
              <i class="fa-solid fa-xmark me-1" aria-hidden="true"></i><?= $lang->browse['btn_cancel'] ?>
            </button>
            <button type="button" class="btn btn-primary st-pill" id="applyActionBtn">
              <i class="fa-solid fa-play me-1" aria-hidden="true"></i><?= $lang->browse['btn_apply'] ?>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Подтверждение (запасной вариант, если SweetAlert2 не загрузился) -->
    <div class="modal fade br-modal" id="modConfirmModal" tabindex="-1" aria-labelledby="modConfirmModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content mod-modal-content">
          <div class="modal-header mod-modal-header">
            <span class="st-ico st-soft-danger me-2"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
            <h5 class="modal-title" id="modConfirmModalLabel"><?= $lang->browse['pane_confirm'] ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $e($lang->browse['btn_close']) ?>"></button>
          </div>
          <div class="modal-body mod-modal-body">
            <p id="modConfirmMessage" class="mb-0"></p>
            <div id="modConfirmTorrentList" class="mod-selected-list mt-3" style="display:none;"></div>
          </div>
          <div class="modal-footer mod-modal-footer">
            <button type="button" class="btn btn-outline-secondary st-pill" data-bs-dismiss="modal" id="modConfirmCancelBtn">
              <i class="fa-solid fa-xmark me-1" aria-hidden="true"></i><?= $lang->browse['btn_cancel'] ?>
            </button>
            <button type="button" class="btn btn-danger st-pill" id="modConfirmAcceptBtn">
              <i class="fa-solid fa-check me-1" aria-hidden="true"></i><?= $lang->browse['btn_confirm'] ?>
            </button>
          </div>
        </div>
      </div>
    </div>
    </form>
    <?php endif; ?>

    <!-- Увеличенный постер при наведении -->
    <div class="poster-zoom-overlay" id="posterZoomOverlay" aria-hidden="true">
        <img src="" alt="<?= $e($lang->browse['alt_poster_preview']) ?>" class="poster-zoom-img" id="posterZoomImg">
    </div>
</div>
<?php

// JS-строки: ключи js_* из ланга browse + js_bm_* из details (bookmark.js) -> AGS_LANG без префикса
$agsJsLang = [];
ags_bookmark_lang();
foreach ([$lang->browse ?? [], $lang->details ?? []] as $i => $set) {
    foreach ($set as $agsKey => $agsVal) {
        $agsKey = (string)$agsKey;
        if ($i === 0 ? str_starts_with($agsKey, 'js_') : str_starts_with($agsKey, 'js_bm_')) {
            $agsJsLang[substr($agsKey, 3)] = preg_replace('/%(\d+)\$s/', '{$1}', (string)$agsVal);
        }
    }
}
echo '<script>const AGS_LANG = ' . json_encode($agsJsLang, $agsJsonFlags) . ';</script>';

echo '<script src="' . $BASEURL . '/scripts/toast.js?ver=11"></script>';
echo '<script src="' . $BASEURL . '/scripts/bookmark.js?ver=7"></script>';
echo '<script src="' . $BASEURL . '/scripts/popover.js"></script>';
echo '<script src="' . $BASEURL . '/admin/scripts/flatpickr.js"></script>';
if ($is_mod) {
    echo '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>';
}
echo '<script src="' . $BASEURL . '/scripts/browse.js?ver=3"></script>';
if ($is_mod) {
    echo '<script src="' . $BASEURL . '/scripts/browse-moderation.js?ver=3"></script>';
}

stdfoot();
