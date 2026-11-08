<?php

declare(strict_types=1);

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'browse.php');
define('B_VERSION', '6.6.3');
define("SCRIPTNAME", "browse.php");


require './global.php';

$lang->load('browse');


require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_bookmark.php';

// Флаги для безопасного вывода строк в <script>
$agsJsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

maxsysop();


if (empty($CURUSER['id'])) {
    print_no_permission();
}


// Страницы с query-параметрами (?search_type=...&keywords=...&page=...) -
// это дубли одного и того же browse.php, поисковики их индексировать не должны.
if (!empty($_SERVER['QUERY_STRING'])) {
    header('X-Robots-Tag: noindex, follow');
}

$is_mod = is_mod($usergroups);


$category = (int)($_POST['category'] ?? $_GET['category'] ?? 0);
$keywords = $_POST['keywords'] ?? $_GET['keywords'] ?? '';

if (!is_string($keywords)) {
    $keywords = '';
}


$search_type = trim((string)($_POST['search_type'] ?? $_GET['search_type'] ?? ''));

$special_search        = trim((string)($_GET['special_search']        ?? $_POST['special_search']        ?? ''));
$sort      = trim((string)($_GET['sort']      ?? $_POST['sort']      ?? ''));
$order     = trim((string)($_GET['order']     ?? $_POST['order']     ?? ''));
$include_dead_torrents = trim((string)($_GET['include_dead_torrents'] ?? $_POST['include_dead_torrents'] ?? ''));


$Links = [];
require_once INC_PATH . '/functions_mkprettytime.php';

/** HTML-экранирование для вывода */
$e = static fn($v): string => htmlspecialchars_uni((string)$v);


// ── Акции: глобальный фрилич / сильверлич / x2, бонус ко дню рождения ─────
// Раньше тут до stdhead() печатался <link> на messagess.css - вывод до
// шапки страницы. Теперь это обычная st-card внутри .br-page.

/** Плашка акции в стиле st-ui */
function browse_promo_card(string $icon, string $tone, string $title, string $text): string
{
    return '<section class="st-card br-promo br-promo--' . $tone . ' mb-3" role="status">'
        . '<div class="st-ico st-soft-' . $tone . ' br-promo__ico"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i></div>'
        . '<div class="min-w-0"><div class="br-promo__title">' . $title . '</div>'
        . '<div class="br-promo__text">' . $text . '</div></div>'
        . '</section>';
}

/**
 * Плашка бонуса ко дню рождения (фрилич / сильверлич / x2 аплоад).
 * Бонус действует до конца сегодняшнего дня по времени сервера.
 */
function browse_bday_notice(string $type, string $username): string
{
    global $lang;

    $L = static fn(string $key, string $fallback): string =>
        htmlspecialchars_uni((string)($lang->browse[$key] ?? $fallback));

    $types = [
        'freeleech'    => ['fa-gift',                 $L('bday_fl_badge', 'Freeleech'),     $L('bday_fl_text', 'Downloads today do not count toward your ratio — grab what you have been saving for.')],
        'silverleech'  => ['fa-coins',                $L('bday_sl_badge', 'Silverleech'),   $L('bday_sl_text', 'Only half of what you download today counts toward your ratio.')],
        'doubleupload' => ['fa-arrow-up-wide-short',  $L('bday_du_badge', 'Double upload'), $L('bday_du_text', 'Everything you upload today counts twice.')],
    ];
    if (!isset($types[$type])) {
        return '';
    }
    [$icon, $badge, $text] = $types[$type];

    $end      = strtotime('tomorrow') - 1;
    $left     = max(0, $end - TIMENOW);
    $lefttext = ags_fmt_safe($L('bday_left', 'Ends in {1} h {2} min'), intdiv($left, 3600), intdiv($left % 3600, 60));

    $title = ags_fmt_safe($L('bday_title', 'Happy birthday, {1}!'), htmlspecialchars_uni($username)) . ' 🎉';
    $until = ags_fmt_safe($L('bday_until', 'Today until 23:59'));
    $hnr   = $L('bday_hnr', 'Please no hit\'n\'run — keep seeding after you finish!');

    $body = '<span class="st-badge st-soft-primary me-1"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i>' . $badge . '</span>'
          . '<span class="br-promo__when"><i class="fa-regular fa-clock" aria-hidden="true"></i>' . $until . ' · ' . $lefttext . '</span>'
          . '<div class="mt-1">' . $text . ' ' . $hnr . '</div>';

    return browse_promo_card('fa-cake-candles', 'primary', $title, $body);
}

/** {1}, {2}… и %1$s… в уже экранированной строке ланга */
function ags_fmt_safe(string $str, string|int ...$args): string
{
    $map = [];
    foreach ($args as $i => $arg) {
        $map['{' . ($i + 1) . '}']   = (string)$arg;
        $map['%' . ($i + 1) . '$s'] = (string)$arg;
    }
    return strtr($str, $map);
}


$___notice = '';
include TSDIR . '/cache/freeleech.php';

if ($__F_START < get_date_time() && $__F_END > get_date_time()) {
    $promoTypes = [
        'freeleech'    => ['fa-gift',                'success', 'pane_global_freeleech',    'f_leech'],
        'silverleech'  => ['fa-coins',               'info',    'pane_global_silverleech',  's_leech'],
        'doubleupload' => ['fa-arrow-up-wide-short', 'warning', 'pane_global_doubleupload', 'd_leech'],
    ];
    if (isset($promoTypes[$__FLSTYPE])) {
        [$pIcon, $pTone, $pTitle, $pText] = $promoTypes[$__FLSTYPE];
        $___notice = browse_promo_card(
            $pIcon,
            $pTone,
            $lang->browse[$pTitle],
            sprintf($lang->browse[$pText], $__F_START, $__F_END)
        );
    }
} elseif ($bdayreward === 'yes' && $bdayrewardtype) {
    // birthday хранится как «день-месяц-год» (j-n-Y)
    $curuserbday = !empty($CURUSER['birthday']) ? explode('-', (string)$CURUSER['birthday']) : [];
    if (isset($curuserbday[0], $curuserbday[1]) && date('j-n') === (int)$curuserbday[0] . '-' . (int)$curuserbday[1]) {
        $___notice = browse_bday_notice((string)$bdayrewardtype, (string)($CURUSER['username'] ?? ''));
    }
}


// ── Категории ──────────────────────────────────────────────────────────────
// В кэше id - строки ('1'), поэтому раньше `$category === $sc['id']` не
// срабатывал никогда и выбранная категория не подсвечивалась.
require TSDIR . '/cache/categories.php';

$userNotifs  = (string)($CURUSER['notifs'] ?? '');
$catIconMap  = [];
$catNameMap  = [];
$subcategories = [];

foreach ($_categoriesS ?? [] as $sc) {
    $scid = (int)$sc['id'];
    $catNameMap[$scid] = (string)$sc['name'];
    $subcategories[(int)$sc['pid']][] = [
        'id'     => $scid,
        'name'   => (string)$sc['name'],
        'active' => $category === $scid || (!$category && str_contains($userNotifs, '[cat' . $scid . ']')),
    ];
}

$catTiles = '';
foreach ($_categoriesC ?? [] as $c) {
    $cid = (int)$c['id'];
    $catIconMap[$cid] = (string)$c['icon'];
    $catNameMap[$cid] = (string)$c['name'];

    $isActive = $category === $cid;
    $isSubscr = !$category && str_contains($userNotifs, '[cat' . $cid . ']');
    $link     = get_category_link($cid);
    $cname    = $e($c['name']);

    $chips = '';
    foreach ($subcategories[$cid] ?? [] as $sub) {
        $chips .= '<a href="' . get_category_link($sub['id']) . '" class="st-fchip' . ($sub['active'] ? ' st-fchip--new' : '') . '">'
                . '<i class="fa-solid fa-turn-up fa-rotate-90" aria-hidden="true"></i>' . $e($sub['name']) . '</a>';
    }

    $catTiles .= '<div class="br-cat category-container' . ($isActive ? ' br-cat--active' : '') . ($isSubscr ? ' br-cat--subscr' : '') . '" data-category-id="' . $cid . '">'
        . '<a href="' . $link . '" class="br-cat__main category-link" data-cat-id="' . $cid . '" title="' . $cname . '"' . ($isActive ? ' aria-current="true"' : '') . '>'
        .   '<span class="st-ico br-cat__ico"><i class="' . $e($c['icon']) . '" aria-hidden="true"></i></span>'
        .   '<span class="br-cat__name">' . $cname . '</span>'
        . '</a>'
        . ($chips !== '' ? '<div class="st-fchips br-cat__subs">' . $chips . '</div>' : '')
        . '</div>';
}

$catName = $category ? ($catNameMap[$category] ?? ('#' . $category)) : $lang->browse['lbl_crumb_all'];

require_once INC_PATH . '/functions_category.php';
$catdropdown  = ts_category_list('category', ($category ?: ''), '<option value="0">' . $lang->browse['alltypes'] . '</option>', 'categories');
// Отдельный id для выпадающего списка «Переместить в» в модалке модерации
// (раньше оба списка имели id="categories").
$movedropdown = $is_mod ? ts_category_list('category', ($category ?: ''), '<option value="0">' . $lang->browse['alltypes'] . '</option>', 'br_move_category') : '';

unset($_categoriesC, $_categoriesS);


// Подписи типов поиска и спец-поиска - для бейджей фильтров и шапки
// (раньше в бейдже показывалось сырое значение вроде «t_name»).
$searchTypeLabels = [
    't_name'        => $lang->browse['t_name'],
    't_description' => $lang->browse['t_description'],
    't_tags'        => $lang->browse['opt_tags'],
    't_both'        => $lang->browse['t_both'],
    't_uploader'    => $lang->browse['t_uploader'],
    't_genre'       => $lang->browse['t_genre'],
];
$specialLabels = [
    'mytorrents'  => $lang->browse['qf_my_uploads'],
    'mybookmarks' => $lang->browse['qf_my_bookmarks'],
    'myreseeds'   => $lang->browse['qf_need_reseed'],
];


$size_min = $_GET['size_min'] ?? '';
$size_max = $_GET['size_max'] ?? '';
$min_seeders = $_GET['min_seeders'] ?? '';
$health_filter = (($_GET['health'] ?? '') === 'seeded') ? 'seeded' : ''; 
$freeleech_only = (($_GET['freeleech_only'] ?? '') === '1');
$no_seeders_only = (($_GET['no_seeders'] ?? '') === '1');
$seeders_gt_leechers = (($_GET['seeders_gt_leechers'] ?? '') === '1');
$hide_downloaded = (($_GET['hide_downloaded'] ?? '') === '1');
$imdb_min = in_array($_GET['imdb_min'] ?? '', ['7', '8', '9'], true) ? (int)$_GET['imdb_min'] : 0;

$seeders_options = [
    ''    => $lang->browse['opt_any_seeders'],
    '1'   => '1+',
    '5'   => '5+',
    '10'  => '10+',
    '25'  => '25+',
    '50'  => '50+',
    '100' => '100+',
];

$seeders_select = '<select class="form-select" name="min_seeders">';
foreach ($seeders_options as $val => $label) {
    $selected = ($min_seeders == $val) ? ' selected' : '';
    $seeders_select .= '<option value="' . $val . '"' . $selected . '>' . htmlspecialchars_uni((string)$label) . '</option>';
}
$seeders_select .= '</select>';

// "0.5 GB" -> "0,5 ГБ" и т.п. по языку
$fmtGb = static function (string $n): string {
    global $lang;
    return sprintf($lang->browse['opt_size_gb'], str_replace('.', $lang->browse['lbl_num_dec_point'], $n));
};

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

$size_options_max = [
    ''             => $lang->browse['opt_max_size'],
    '536870912'    => $fmtGb('0.5'),
    '1073741824'   => $fmtGb('1'),
    '2147483648'   => $fmtGb('2'),
    '5368709120'   => $fmtGb('5'),
    '10737418240'  => $fmtGb('10'),
    '21474836480'  => $fmtGb('20'),
    '53687091200'  => $fmtGb('50'),
    '107374182400' => $fmtGb('100'),
];

$size_min_select = '<select class="form-select" name="size_min">';
foreach ($size_options_min as $val => $label) {
    $selected = ($size_min == $val) ? ' selected' : '';
    $size_min_select .= '<option value="' . $val . '"' . $selected . '>' . htmlspecialchars_uni((string)$label) . '</option>';
}
$size_min_select .= '</select>';

$size_max_select = '<select class="form-select" name="size_max">';
foreach ($size_options_max as $val => $label) {
    $selected = ($size_max == $val) ? ' selected' : '';
    $size_max_select .= '<option value="' . $val . '"' . $selected . '>' . htmlspecialchars_uni((string)$label) . '</option>';
}
$size_max_select .= '</select>';






$daysprune = isset($_GET['daysprune']) ? (int)$_GET['daysprune'] : (int)($_POST['daysprune'] ?? 0);

// Диапазон дат добавления (от/до конкретной даты) - отдельно от daysprune
// ("последние N дней"). Та же строгая проверка формата, что в usersearch.php.
$to_ts_browse = function (?string $d, bool $end = false): int {
    if (!$d) return 0;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return 0;
    return (int)strtotime($d . ($end ? ' 23:59:59' : ' 00:00:00'));
};
// is_string(): ?added_from[]=x раньше давал TypeError (strict_types)
$added_from = $to_ts_browse(is_string($_GET['added_from'] ?? null) ? $_GET['added_from'] : null, false);
$added_to   = $to_ts_browse(is_string($_GET['added_to'] ?? null) ? $_GET['added_to'] : null, true);

$daysprune_options = [
    0   => $lang->browse['opt_days_any'],
    1   => $lang->browse['opt_days_1'],
    7   => $lang->browse['opt_days_7'],
    30  => $lang->browse['opt_days_30'],
    90  => $lang->browse['opt_days_90'],
    180 => $lang->browse['opt_days_180'],
    365 => $lang->browse['opt_days_365'],
];

$daysprune_select = '<select class="form-select" name="daysprune" id="daysprune">';
foreach ($daysprune_options as $val => $label) {
    $selected = ((int)$daysprune === $val) ? ' selected' : '';
    $daysprune_select .= '<option value="' . $val . '"' . $selected . '>' . htmlspecialchars_uni((string)$label) . '</option>';
}
$daysprune_select .= '</select>';





// Хелпер: собрать URL без одного параметра из текущего $_GET
if (!function_exists('browse_url_without')) {
    function browse_url_without(string $script, array $get, string $param): string {
        $q = $get;
        unset($q[$param]);
        $qs = http_build_query($q);
        return $script . ($qs !== '' ? '?' . $qs : '');
    }
}

// Активные фильтры (для бейджей с ×)
// $size_min_val/$size_max_val/$min_seeders_val нужны уже здесь - раньше
// вычислялись значительно позже (перед построением WHERE), из-за чего тут
// использовались как неопределённые переменные (PHP warning на каждый
// запрос). Считаем один раз здесь, дальше просто переиспользуем.
$size_min_val    = ($size_min !== '') ? (int)$size_min : null;
$size_max_val    = ($size_max !== '') ? (int)$size_max : null;
$min_seeders_val = ($min_seeders !== '') ? (int)$min_seeders : null;

$activeFilters = [];
if ($category) {
    $activeFilters[] = ['label' => $e($catName), 'param' => 'category'];
}
if ($size_min_val) {
    $activeFilters[] = ['label' => sprintf($lang->browse['flt_size_min'], mksize($size_min_val)), 'param' => 'size_min'];
}
if ($size_max_val) {
    $activeFilters[] = ['label' => sprintf($lang->browse['flt_size_max'], mksize($size_max_val)), 'param' => 'size_max'];
}
if ($min_seeders_val) {
    $activeFilters[] = ['label' => sprintf($lang->browse['flt_seeders_min'], $min_seeders_val), 'param' => 'min_seeders'];
}
if ($keywords !== '') {
    $activeFilters[] = ['label' => sprintf($lang->browse['flt_search'], htmlspecialchars_uni($keywords)), 'param' => 'keywords'];
}
if ($search_type !== '' && $search_type !== 't_both') {
    $activeFilters[] = ['label' => sprintf($lang->browse['flt_type'], $searchTypeLabels[$search_type] ?? $e($search_type)), 'param' => 'search_type'];
}
if ($special_search !== '') {
    $activeFilters[] = ['label' => $specialLabels[$special_search] ?? sprintf($lang->browse['flt_special'], $e($special_search)), 'param' => 'special_search'];
}
if ($include_dead_torrents === 'yes') {
    $activeFilters[] = ['label' => $lang->browse['flt_include_dead'], 'param' => 'include_dead_torrents'];
}
if ($freeleech_only) {
    $activeFilters[] = ['label' => $lang->browse['flt_freeleech_only'], 'param' => 'freeleech_only'];
}
if ($no_seeders_only) {
    $activeFilters[] = ['label' => $lang->browse['flt_no_seeders'], 'param' => 'no_seeders'];
}
if ($seeders_gt_leechers) {
    $activeFilters[] = ['label' => $lang->browse['flt_seeders_gt_leechers'], 'param' => 'seeders_gt_leechers'];
}
if ($hide_downloaded) {
    $activeFilters[] = ['label' => $lang->browse['flt_hide_downloaded'], 'param' => 'hide_downloaded'];
}
if ($health_filter === 'seeded') {
    $activeFilters[] = ['label' => $lang->browse['qf_has_seeders'], 'param' => 'health'];
}
if ($imdb_min > 0) {
    $activeFilters[] = ['label' => sprintf($lang->browse['flt_imdb_min'], $imdb_min), 'param' => 'imdb_min'];
}

if ($daysprune > 0) {
    $daysLabel = $daysprune_options[$daysprune] ?? sprintf($lang->browse['flt_last_n_days'], $daysprune);
    $activeFilters[] = ['label' => $daysLabel, 'param' => 'daysprune'];
}
if ($added_from) {
    $activeFilters[] = ['label' => sprintf($lang->browse['flt_added_from'], htmlspecialchars($_GET['added_from'])), 'param' => 'added_from'];
}
if ($added_to) {
    $activeFilters[] = ['label' => sprintf($lang->browse['flt_added_to'], htmlspecialchars($_GET['added_to'])), 'param' => 'added_to'];
}


$WHERE = " WHERE" . ($include_dead_torrents === 'yes' ? '' : " t.visible = 'yes' AND") . " t.banned = 'no'";
$Links[] = 'include_dead_torrents=' . ($include_dead_torrents === 'yes' ? 'yes' : 'no');

$innerjoin = '';
$params = [];

if ($special_search === 'myreseeds') {
    $Links[] = 'special_search=myreseeds';
    $WHERE .= ' AND t.seeders = 0 AND t.leechers > 0 AND t.owner = ?';
    $params[] = $CURUSER['id'];
} elseif ($special_search === 'mybookmarks') {
    $Links[] = 'special_search=mybookmarks';
    $innerjoin = ' INNER JOIN bookmarks b ON (b.torrentid = t.id)';
    $WHERE .= ' AND b.userid = ?';
    $params[] = $CURUSER['id'];
} elseif ($special_search === 'mytorrents') {
    $Links[] = 'special_search=mytorrents';
    $WHERE .= ' AND t.owner = ?';
    $params[] = $CURUSER['id'];
} elseif ($special_search === 'weaktorrents') {
    $Links[] = 'special_search=weaktorrents';
	$WHERE .= " AND (t.visible = 'no' OR (t.leechers > 0 AND t.seeders = 0) OR (t.leechers = 0 AND t.seeders = 0))";
}

$extraquery = [];
$extra_params = [];

if ($keywords && $search_type) {
    $OrjKeywords = $keywords;
    $Links[] = 'keywords=' . urlencode($keywords);
    $Links[] = 'search_type=' . urlencode($search_type);
    
    $fulltextsearch = 'no';
	
	if ($fulltextsearch === 'yes') {
        require INC_PATH . '/function_search_clean.php';
        $keywords = clean_keywords_ft($keywords);
    }

    if ($keywords) {
        switch ($search_type) {
            case 't_name':
                if ($fulltextsearch === 'yes') {
                    $extraquery[] = "(MATCH(t.name) AGAINST(? IN BOOLEAN MODE))";
                    $extra_params[] = $keywords;
                } else {
                    $extraquery[] = "(t.name LIKE ?)";
                    $extra_params[] = "%" . $keywords . "%";
                }
                break;
                
            case 't_description':
                if ($fulltextsearch === 'yes') {
                    $extraquery[] = "(MATCH(t.descr) AGAINST(? IN BOOLEAN MODE))";
                    $extra_params[] = $keywords;
                } else {
                    $extraquery[] = "(t.descr LIKE ?)";
                    $extra_params[] = "%" . $keywords . "%";
                }
                break;
                
            case 't_tags':
                if ($fulltextsearch === 'yes') {
                    $extraquery[] = "(MATCH(t.tags) AGAINST(? IN BOOLEAN MODE))";
                    $extra_params[] = $keywords;
                } else {
                    $extraquery[] = "(t.tags LIKE ?)";
                    $extra_params[] = "%" . $keywords . "%";
                }
                break;
                
            case 't_both':
                if ($fulltextsearch === 'yes') {
                    $extraquery[] = "(MATCH(t.name) AGAINST(? IN BOOLEAN MODE) OR MATCH(t.descr) AGAINST(? IN BOOLEAN MODE))";
                    $extra_params[] = $keywords;
                    $extra_params[] = $keywords;
                } else {
                    $extraquery[] = "(t.name LIKE ? OR t.descr LIKE ?)";
                    $extra_params[] = "%" . $keywords . "%";
                    $extra_params[] = "%" . $keywords . "%";
                }
                break;
                
            case 't_uploader':
                $user_query = $db->sql_query_prepared(
                    "SELECT id FROM users WHERE UPPER(username) = ? LIMIT 1", 
                    [strtoupper($OrjKeywords)]
                );
                
                if ($db->num_rows($user_query) > 0) {
                    $user = $db->fetch_array($user_query);
                    $extraquery[] = "t.owner = ?";
                    $extra_params[] = $user['id'];
                    if (!$is_mod) {
                        $extraquery[] = "t.anonymous != 'yes'";
                    }
                } else {
                    $extraquery[] = "t.owner = ?";
                    $extra_params[] = $OrjKeywords;
                }
                break;
                
            case 't_genre':
                if ($fulltextsearch === 'yes') {
                    $extraquery[] = "(MATCH(t.t_link) AGAINST(? IN BOOLEAN MODE))";
                    $extra_params[] = $keywords;
                } else {
                    $extraquery[] = "(t.t_link LIKE ?)";
                    $extra_params[] = "%" . $keywords . "%";
                }
                break;
        }
        $keywords = $OrjKeywords;
    }
}

if ($category) {
    $cat_query = $db->sql_query_prepared(
        "SELECT id FROM categories WHERE type='s' AND pid = ?", 
        [$category]
    );
    
    if ($db->num_rows($cat_query) > 0) {
        $squerycats = [];
        
        while ($squery = $db->fetch_array($cat_query)) {
            $squerycats[] = (int)$squery['id'];
        }
        $catIds = array_merge([$category], $squerycats);
        $extraquery[] = 't.category IN (' . implode(',', array_fill(0, count($catIds), '?')) . ')';
        $extra_params = array_merge($extra_params, $catIds);
		
    } else {
        $extraquery[] = "t.category = ?";
        $extra_params[] = $category;
    }
    $Links[] = 'category=' . $category;
}




if ($special_search) {
    $Links[] = 'special_search=' . urlencode($special_search);
}



// Фильтр по размеру — ДО применения extraquery
// (сами $size_min_val/$size_max_val/$min_seeders_val уже вычислены выше,
// перед блоком активных фильтров - здесь просто используем их)
if ($size_min_val !== null && $size_min_val > 0) {
    $extraquery[] = 't.size >= ?';
    $extra_params[] = $size_min_val;
    $Links[] = 'size_min=' . $size_min_val;
}

if ($size_max_val !== null && $size_max_val > 0) {
    $extraquery[] = 't.size <= ?';
    $extra_params[] = $size_max_val;
    $Links[] = 'size_max=' . $size_max_val;
}

if ($health_filter === 'seeded') {
    $extraquery[] = 't.seeders > 0';
    $Links[] = 'health=seeded';
}

if ($freeleech_only) {
    $extraquery[] = "t.free = 'yes'";
    $Links[] = 'freeleech_only=1';
}

if ($no_seeders_only) {
    $extraquery[] = 't.seeders = 0';
    $Links[] = 'no_seeders=1';
}

if ($seeders_gt_leechers) {
    $extraquery[] = 't.seeders > t.leechers';
    $Links[] = 'seeders_gt_leechers=1';
}

if ($hide_downloaded && $CURUSER['id']) {
    $extraquery[] = "t.id NOT IN (SELECT torrentid FROM snatched WHERE userid = ? AND finished = 'yes')";
    $extra_params[] = $CURUSER['id'];
    $Links[] = 'hide_downloaded=1';
}

if ($imdb_min > 0) {
    // t_link хранит весь HTML-блок IMDB.php целиком, не чистую колонку
    // рейтинга - быстро, но хрупко: если формат вывода парсера изменится,
    // фильтр молча перестанет находить рейтинг (пустой список, без ошибки).
    // $imdb_min ограничен whitelist'ом ['7','8','9'] выше - строится сам
    // паттерн на сервере, не из пользовательского ввода напрямую.
    $imdbDigits = range($imdb_min, 9);
    $imdbAlternatives = array_map(fn($d) => $d . '\\.[0-9]', $imdbDigits);
    if ($imdb_min <= 10) {
        $imdbAlternatives[] = '10\\.0';
    }
    $imdbPattern = "IMDb Rating:</strong>\\s*<span[^>]*>(" . implode('|', $imdbAlternatives) . ")/10";
    $extraquery[] = 't.t_link REGEXP ?';
    $extra_params[] = $imdbPattern;
    $Links[] = 'imdb_min=' . $imdb_min;
}

if ($min_seeders_val !== null && $min_seeders_val > 0) {
    $extraquery[] = 't.seeders >= ?';
    $extra_params[] = $min_seeders_val;
    $Links[] = 'min_seeders=' . $min_seeders_val;
}

// Фильтр по дате добавления (daysprune)
if ($daysprune > 0) {
    $extraquery[]   = 't.added >= ?';
    $extra_params[] = TIMENOW - ($daysprune * 86400);
    $Links[]        = 'daysprune=' . $daysprune;
}

// Диапазон дат добавления (от/до конкретной даты)
if ($added_from) {
    $extraquery[]   = 't.added >= ?';
    $extra_params[] = $added_from;
    $Links[]        = 'added_from=' . urlencode($_GET['added_from']);
}
if ($added_to) {
    $extraquery[]   = 't.added <= ?';
    $extra_params[] = $added_to;
    $Links[]        = 'added_to=' . urlencode($_GET['added_to']);
}


if (count($extraquery) > 0) {
    $WHERE .= ' AND ' . implode(' AND ', $extraquery);
    $params = array_merge($params, $extra_params);
    $Links[] = 'do=search';
    $Links[] = 'keywords=' . urlencode($keywords);
    $Links[] = 'search_type=' . urlencode($search_type);
}



// Сортировка по клику на заголовок колонки - раньше $orderby была жёстко
// зашита, пользователь не мог поменять порядок вообще. Whitelist колонок -
// $_GET['sort'] никогда не идёт в SQL напрямую.
$sortColumns = [
    'name'     => 't.name',
    'size'     => 't.size',
    'snatched' => 't.times_completed',
    'seeders'  => 't.seeders',
    'leechers' => 't.leechers',
    'added'    => 't.added',
];
$sortBy  = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sortColumns) ? $_GET['sort'] : null;
$sortDir = (isset($_GET['dir']) && $_GET['dir'] === 'asc') ? 'asc' : 'desc';

if ($sortBy !== null) {
    $orderby = 't.sticky, ' . $sortColumns[$sortBy] . ' ' . strtoupper($sortDir);
} else {
    $orderby = 't.sticky, t.added DESC';
}



$torrentsperpage = ($CURUSER['torrentsperpage'] <> 0 ? (int)$CURUSER['torrentsperpage'] : $ts_perpage);
$threadcount = 0;

// Было: SELECT 4 колонок + ORDER BY только чтобы посчитать num_rows() -
// MySQL приходилось сортировать (возможно filesort) и передавать в PHP
// весь подходящий набор строк ради счётчика. COUNT(*) без ORDER BY даёт
// тот же результат без лишней работы и передачи данных.
$count_sql = 'SELECT COUNT(*) AS cnt
              FROM torrents t' . $innerjoin . ' 
              LEFT JOIN users u ON (t.owner=u.id) 
              LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
              LEFT JOIN categories c ON (t.category=c.id)' . $WHERE;

$countquery = $db->sql_query_prepared($count_sql, $params);
$threadcount = $countquery ? (int)$db->fetch_field($countquery, 'cnt') : 0;

// Smart Browse overview uses the same active WHERE conditions as the result list.
$smartStats = ['total' => $threadcount, 'active' => 0, 'dead' => 0, 'new_today' => 0,
    'last_7d' => 0, 'last_30d' => 0, 'big_size' => 0, 'freeleech' => 0];
$smartStatsSql = 'SELECT
    COALESCE(SUM(CASE WHEN t.seeders > 0 THEN 1 ELSE 0 END), 0) AS active_count,
    COALESCE(SUM(CASE WHEN t.seeders = 0 THEN 1 ELSE 0 END), 0) AS dead_count,
    COALESCE(SUM(CASE WHEN t.added >= ? THEN 1 ELSE 0 END), 0) AS new_today_count,
    COALESCE(SUM(CASE WHEN t.added >= ? THEN 1 ELSE 0 END), 0) AS last_7d_count,
    COALESCE(SUM(CASE WHEN t.added >= ? THEN 1 ELSE 0 END), 0) AS last_30d_count,
    COALESCE(SUM(CASE WHEN t.size >= 5368709120 THEN 1 ELSE 0 END), 0) AS big_size_count,
    COALESCE(SUM(CASE WHEN t.free = \'yes\' THEN 1 ELSE 0 END), 0) AS freeleech_count
    FROM torrents t' . $innerjoin . '
    LEFT JOIN users u ON (t.owner=u.id)
    LEFT JOIN usergroups g ON (u.usergroup=g.gid)
    LEFT JOIN categories c ON (t.category=c.id)' . $WHERE;
$smartStatsParams = array_merge(
    [TIMENOW - 86400, TIMENOW - 7 * 86400, TIMENOW - 30 * 86400],
    $params
);
$smartStatsQuery = $db->sql_query_prepared($smartStatsSql, $smartStatsParams);
if ($smartStatsQuery) {
    $smartStatsRow = $db->fetch_array($smartStatsQuery);
    if ($smartStatsRow) {
        $smartStats['active'] = (int)$smartStatsRow['active_count'];
        $smartStats['dead'] = (int)$smartStatsRow['dead_count'];
        $smartStats['new_today'] = (int)$smartStatsRow['new_today_count'];
        $smartStats['last_7d'] = (int)$smartStatsRow['last_7d_count'];
        $smartStats['last_30d'] = (int)$smartStatsRow['last_30d_count'];
        $smartStats['big_size'] = (int)$smartStatsRow['big_size_count'];
        $smartStats['freeleech'] = (int)$smartStatsRow['freeleech_count'];
    }
}



// ── Пресеты быстрых фильтров ───────────────────────────────────────────────
// Ссылка = текущий $_GET + изменения (null - убрать параметр). Номер
// страницы сбрасываем: после смены фильтра старая страница может не существовать.
$smartPreset = static function (array $changes): string {
    $params = $_GET;
    unset($params['page'], $params['smart_browse'], $params['mod_success'], $params['mod_error']);
    foreach ($changes as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $qs = http_build_query($params);
    return htmlspecialchars($_SERVER['SCRIPT_NAME'] . ($qs !== '' ? '?' . $qs : ''), ENT_QUOTES);
};

// Пресет активен, если все его параметры уже стоят в URL
$smartPresetActive = static function (array $changes): bool {
    foreach ($changes as $key => $value) {
        $current = $_GET[$key] ?? null;
        if ($value === null) {
            if ($current !== null) {
                return false;
            }
        } elseif (!is_scalar($current) || (string)$current !== (string)$value) {
            return false;
        }
    }
    return true;
};

/** Кнопка-пресет: ссылка-«пилюля» с иконкой и необязательным счётчиком */
$qfBtn = static function (array $changes, string $icon, string $label, ?int $cnt = null) use ($smartPreset, $smartPresetActive): string {
    $on = $smartPresetActive($changes);
    return '<a class="br-qf' . ($on ? ' is-active' : '') . '" href="' . $smartPreset($changes) . '"' . ($on ? ' aria-current="true"' : '') . '>'
        . ($icon !== '' ? '<i class="' . $icon . '" aria-hidden="true"></i>' : '')
        . '<span>' . $label . '</span>'
        . ($cnt !== null ? '<span class="br-qf__cnt">' . ts_nf($cnt) . '</span>' : '')
        . '</a>';
};

$qfGroup = static fn(string $title, string $buttons): string =>
    '<div class="br-qgroup"><div class="br-qgroup__lbl">' . $title . '</div><div class="br-qgroup__btns">' . $buttons . '</div></div>';

$quickFiltersHtml =
    $qfGroup($lang->browse['sec_qf_quick'],
        $qfBtn(['health' => 'seeded'], 'fa-solid fa-heart-pulse', $lang->browse['qf_has_seeders'], $smartStats['active'])
      . $qfBtn(['sort' => 'seeders', 'dir' => 'desc'], 'fa-solid fa-arrow-down-wide-short', $lang->browse['qf_most_seeders'])
      . $qfBtn(['min_seeders' => '1'], 'fa-solid fa-signal', $lang->browse['qf_active'], $smartStats['active'])
      . $qfBtn(['daysprune' => '7'], 'fa-regular fa-clock', $lang->browse['opt_days_7'], $smartStats['last_7d'])
      . $qfBtn(['daysprune' => '30'], 'fa-regular fa-calendar', $lang->browse['opt_days_30'], $smartStats['last_30d'])
      . $qfBtn(['size_min' => '5368709120'], 'fa-solid fa-database', sprintf($lang->browse['qf_size_plus'], 5), $smartStats['big_size'])
      . $qfBtn(['freeleech_only' => '1'], 'fa-solid fa-gift', $lang->browse['flt_freeleech_only'], $smartStats['freeleech']))
  . $qfGroup($lang->browse['sec_qf_popular'],
        $qfBtn(['sort' => 'snatched', 'dir' => 'desc'], 'fa-solid fa-fire', $lang->browse['qf_most_downloaded'])
      . $qfBtn(['sort' => 'leechers', 'dir' => 'desc'], 'fa-solid fa-arrow-down-wide-short', $lang->browse['qf_most_leeched']))
  . $qfGroup($lang->browse['sec_qf_health'],
        $qfBtn(['no_seeders' => '1'], 'fa-solid fa-skull-crossbones', $lang->browse['flt_no_seeders'], $smartStats['dead'])
      . $qfBtn(['seeders_gt_leechers' => '1'], 'fa-solid fa-scale-balanced', $lang->browse['flt_seeders_gt_leechers']))
  . $qfGroup($lang->browse['sec_qf_fresh'],
        $qfBtn(['daysprune' => '1'], 'fa-solid fa-star', $lang->browse['qf_added_today'], $smartStats['new_today'])
      . $qfBtn(['daysprune' => '3'], 'fa-regular fa-clock', $lang->browse['qf_last_3_days'])
      . $qfBtn(['size_max' => '536870912'], 'fa-solid fa-mobile-screen', sprintf($lang->browse['qf_small'], str_replace('.', $lang->browse['lbl_num_dec_point'], '0.5')))
      . $qfBtn(['size_max' => '1073741824'], 'fa-solid fa-mobile-screen', sprintf($lang->browse['qf_small_torrents'], 1))
      . $qfBtn(['size_min' => '10737418240'], 'fa-solid fa-database', sprintf($lang->browse['qf_size_plus'], 10))
      . $qfBtn(['size_min' => '53687091200'], 'fa-solid fa-database', sprintf($lang->browse['qf_size_plus'], 50))
      . $qfBtn(['hide_downloaded' => '1'], 'fa-solid fa-eye-slash', $lang->browse['flt_hide_downloaded']))
  . $qfGroup($lang->browse['sec_qf_authorship'],
        $qfBtn(['special_search' => 'mytorrents'], 'fa-solid fa-user', $lang->browse['qf_my_uploads'])
      . $qfBtn(['special_search' => 'mybookmarks'], 'fa-solid fa-bookmark', $lang->browse['qf_my_bookmarks'])
      . $qfBtn(['special_search' => 'myreseeds'], 'fa-solid fa-rotate', $lang->browse['qf_need_reseed'])
      . $qfBtn(['include_dead_torrents' => 'yes'], 'fa-solid fa-ghost', $lang->browse['flt_include_dead']))
  . $qfGroup($lang->browse['sec_qf_rating'],
        $qfBtn(['imdb_min' => '7'], 'fa-solid fa-star-half-stroke', sprintf($lang->browse['flt_imdb_min'], 7))
      . $qfBtn(['imdb_min' => '8'], 'fa-solid fa-star-half-stroke', sprintf($lang->browse['flt_imdb_min'], 8))
      . $qfBtn(['imdb_min' => '9'], 'fa-solid fa-star', sprintf($lang->browse['flt_imdb_min'], 9)));

$qfClearUrl = $smartPreset(['min_seeders' => null, 'daysprune' => null, 'size_min' => null, 'size_max' => null, 'health' => null,
    'freeleech_only' => null, 'no_seeders' => null, 'seeders_gt_leechers' => null, 'hide_downloaded' => null, 'special_search' => null,
    'include_dead_torrents' => null, 'imdb_min' => null, 'added_from' => null, 'added_to' => null, 'sort' => null, 'dir' => null]);


// ── Чипы активных фильтров (клик = убрать фильтр) ──────────────────────────
$filterTone = static fn(string $param): array => match (true) {
    in_array($param, ['keywords', 'search_type'], true)                      => ['fa-magnifying-glass', 'primary'],
    $param === 'category'                                                    => ['fa-folder',           'info'],
    str_contains($param, 'size')                                             => ['fa-hard-drive',       'warning'],
    str_contains($param, 'seeders'), $param === 'health'                     => ['fa-heart-pulse',      'success'],
    in_array($param, ['daysprune', 'added_from', 'added_to'], true)          => ['fa-calendar',         'muted'],
    str_contains($param, 'freeleech')                                        => ['fa-gift',             'danger'],
    $param === 'imdb_min'                                                    => ['fa-star',             'warning'],
    default                                                                  => ['fa-user',             'muted'],
};

$activeFiltersHtml = '';
foreach ($activeFilters as $f) {
    [$fIcon, $fTone] = $filterTone($f['param']);
    $rmUrl = browse_url_without($_SERVER['SCRIPT_NAME'], array_diff_key($_GET, ['page' => 1, 'mod_success' => 1, 'mod_error' => 1]), $f['param']);
    $activeFiltersHtml .= '<a href="' . htmlspecialchars($rmUrl, ENT_QUOTES) . '" class="st-badge st-soft-' . $fTone . ' br-chip">'
        . '<i class="fa-solid ' . $fIcon . '" aria-hidden="true"></i>' . $f['label']
        . '<i class="fa-solid fa-xmark br-chip__x" aria-hidden="true"></i></a>';
}

$rssUrl   = $BASEURL . '/getrss.php?' . htmlspecialchars(http_build_query(array_diff_key($_GET, ['page' => 1, 'mod_success' => 1, 'mod_error' => 1])), ENT_QUOTES);
$resetUrl = htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES);


// ── Форма поиска ───────────────────────────────────────────────────────────
$searchTypeOptions = '';
foreach ($searchTypeLabels as $stVal => $stLabel) {
    $sel = ($search_type === $stVal || ($stVal === 't_both' && $search_type === '')) ? ' selected' : '';
    $searchTypeOptions .= '<option value="' . $stVal . '"' . $sel . '>' . $stLabel . '</option>';
}

$addedFromVal = is_string($_GET['added_from'] ?? null) ? htmlspecialchars($_GET['added_from'], ENT_QUOTES) : '';
$addedToVal   = is_string($_GET['added_to'] ?? null)   ? htmlspecialchars($_GET['added_to'], ENT_QUOTES)   : '';

$SearchTorrent = '
<section class="st-card br-search mb-3">
  <form method="get" action="' . $BASEURL . '/browse.php" name="searchtorrent" id="searchtorrent" class="st-card__body">
    <input type="hidden" name="do" value="search" />

    <div class="br-search__main">
      <div class="input-group br-search__q position-relative">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
        <input type="text" class="form-control" id="torrent-search" name="keywords"
               placeholder="' . $e($lang->browse['ph_search']) . '" aria-label="' . $e($lang->browse['ph_search']) . '" autocomplete="off"
               value="' . ($keywords !== '' ? $e($keywords) : '') . '">
        <div id="autocomplete-results" class="dropdown-menu br-ac"></div>
      </div>
      <select class="form-select br-search__type" id="search_type" name="search_type" aria-label="' . $e($lang->browse['sortby1']) . '">' . $searchTypeOptions . '</select>
      <button type="submit" class="btn btn-primary st-pill br-search__go">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>' . $lang->browse['btn_search'] . '</span>
      </button>
    </div>

    <div class="br-filters">
      <div class="br-filters__cell">' . $catdropdown . '</div>
      <div class="br-filters__cell">
        <select class="form-select" name="include_dead_torrents" aria-label="' . $e(strip_tags($lang->browse['flt_include_dead'])) . '">
          <option value="no"'  . ($include_dead_torrents !== 'yes' ? ' selected' : '') . '>' . $lang->browse['incdead2'] . '</option>
          <option value="yes"' . ($include_dead_torrents === 'yes' ? ' selected' : '') . '>' . $lang->browse['incdead1'] . '</option>
        </select>
      </div>
      <div class="br-filters__cell">' . $size_min_select . '</div>
      <div class="br-filters__cell">' . $size_max_select . '</div>
      <div class="br-filters__cell">' . $seeders_select . '</div>
      <div class="br-filters__cell">' . $daysprune_select . '</div>
      <div class="br-filters__cell">
        <div class="input-group">
          <span class="input-group-text"><i class="fa-regular fa-calendar" aria-hidden="true"></i></span>
          <input type="text" id="added_from_input" class="form-control" name="added_from" placeholder="' . $e($lang->browse['ph_added_from']) . '" aria-label="' . $e($lang->browse['ph_added_from']) . '" autocomplete="off" value="' . $addedFromVal . '">
        </div>
      </div>
      <div class="br-filters__cell">
        <div class="input-group">
          <span class="input-group-text"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span>
          <input type="text" id="added_to_input" class="form-control" name="added_to" placeholder="' . $e($lang->browse['ph_added_to']) . '" aria-label="' . $e($lang->browse['ph_added_to']) . '" autocomplete="off" value="' . $addedToVal . '">
        </div>
      </div>
    </div>
  </form>

  ' . ($activeFiltersHtml !== '' ? '<div class="br-active">' . $activeFiltersHtml . '</div>' : '') . '

  <div class="br-qf-wrap">
    <button type="button" class="br-fold" data-br-fold="brQuick" aria-expanded="true" aria-controls="brQuick">
      <span class="st-ico st-soft-primary"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span>
      <span class="min-w-0 text-start">
        <span class="br-fold__title">' . $lang->browse['sec_quick_filters'] . '</span>
        <span class="br-fold__hint">' . $lang->browse['hint_quick_filters'] . '</span>
      </span>
      <i class="fa-solid fa-chevron-down br-fold__chev" aria-hidden="true"></i>
    </button>
    <div id="brQuick" class="br-quick">
      ' . $quickFiltersHtml . '
      <div class="br-quick__foot">
        <a class="btn btn-sm btn-outline-secondary st-pill" href="' . $qfClearUrl . '"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>' . $lang->browse['qf_clear'] . '</a>
      </div>
    </div>
  </div>
</section>';


// ── Пагинация ──────────────────────────────────────────────────────────────
if (!$torrentsperpage || $torrentsperpage < 1) {
    $torrentsperpage = 20;
}

$perpage = (int)$torrentsperpage;

if (isset($mybb->input['page']) && (int)$mybb->input['page'] > 0) {
    $page  = (int)$mybb->input['page'];
    $start = ($page - 1) * $perpage;
    $pages = (int)ceil($threadcount / $perpage);

    if ($page > $pages || $page <= 0) {
        $start = 0;
        $page  = 1;
    }
} else {
    $start = 0;
    $page  = 1;
}

// Сортировка тоже должна жить в ссылках пагинатора - раньше со 2-й страницы
// порядок молча сбрасывался на «по дате». render_sort_header() добавляет свой
// sort/dir в конец, а PHP берёт последнее значение параметра - конфликта нет.
if ($sortBy !== null) {
    $Links[] = 'sort=' . $sortBy;
    $Links[] = 'dir=' . $sortDir;
}
// keywords / search_type / special_search раньше добавлялись в $Links дважды
$Links = array_values(array_unique($Links));

$page_url  = $BASEURL . '/browse.php?' . implode('&amp;', $Links);
$multipage = multipage($threadcount, $perpage, $page, $page_url);

/**
 * Заголовок колонки со ссылкой сортировки. Первый клик - по возрастанию
 * (для дат/чисел - по убыванию удобнее, поэтому они начинают с desc),
 * повторный - меняет направление. Все активные фильтры сохраняются.
 */
function render_sort_header(string $label, string $col, string $pageUrl, ?string $currentSort, string $currentDir): string
{
    $isActive = ($currentSort === $col);
    $firstDir = ($col === 'name') ? 'asc' : 'desc';
    $nextDir  = $isActive ? ($currentDir === 'asc' ? 'desc' : 'asc') : $firstDir;
    $icon     = $isActive ? ($currentDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
    $sep      = str_ends_with($pageUrl, '?') ? '' : '&amp;';
    $href     = $pageUrl . $sep . 'sort=' . $col . '&amp;dir=' . $nextDir;

    return '<a href="' . $href . '" class="br-sort' . ($isActive ? ' is-active' : '') . '"'
        . ($isActive ? ' aria-sort="' . ($currentDir === 'asc' ? 'ascending' : 'descending') . '"' : '') . '>'
        . '<span>' . $label . '</span><i class="fa-solid ' . $icon . '" aria-hidden="true"></i></a>';
}


// ── Список торрентов ───────────────────────────────────────────────────────
$sql = 'SELECT t.*, c.name as catname, u.username, u.usergroup
        FROM torrents t ' . $innerjoin . '
        LEFT JOIN categories c ON (t.category=c.id)
        LEFT JOIN users u ON (t.owner=u.id) '
        . $WHERE . ' ORDER BY ' . $orderby . ' LIMIT ?, ?';

$data_params = [...$params, (int)$start, (int)$perpage];
$Query = $db->sql_query_prepared($sql, $data_params);

$TotalTorrents = [];
while ($Query && ($t = $db->fetch_array($Query))) {
    $TotalTorrents[] = $t;
}

// «Уже скачивали» - одним запросом на всю страницу (без N+1)
$already_snatched_ids = [];
if ($CURUSER['id'] && !empty($TotalTorrents)) {
    $pageTorrentIds = array_map(fn($t) => (int)$t['id'], $TotalTorrents);
    $inPlaceholders = implode(',', array_fill(0, count($pageTorrentIds), '?'));
    $snatchParams   = array_merge([$CURUSER['id']], $pageTorrentIds);

    $snatchQuery = $db->sql_query_prepared(
        "SELECT torrentid FROM snatched WHERE userid = ? AND torrentid IN ({$inPlaceholders}) AND finished = 'yes'",
        $snatchParams
    );
    while ($snatchQuery && ($sRow = $db->fetch_array($snatchQuery))) {
        $already_snatched_ids[(int)$sRow['torrentid']] = true;
    }
}

$hlKeywords = ($keywords !== '' && in_array($search_type, ['t_name', 't_both', ''], true)) ? $e($keywords) : '';
$count = 0;
$rowsHtml = '';

foreach ($TotalTorrents as $Torrent) {
    $tid      = (int)$Torrent['id'];
    $s        = (int)$Torrent['seeders'];
    $l        = (int)$Torrent['leechers'];
    $SEOLink  = get_torrent_link($tid);
    $SEOLinkC = get_category_link((int)$Torrent['category']);
    $peersUrl = $SEOLink . (str_contains($SEOLink, '?') ? '&amp;' : '?') . 'tab=peers';
    $dlUrl    = get_download_link($tid);
    $catIcon  = $catIconMap[(int)$Torrent['category']] ?? 'fa-solid fa-question';
    $catLabel = $e($Torrent['catname'] ?? '');
    $fullName = $e($Torrent['name']);
    $sizeTxt  = mksize($Torrent['size']);
    $isSnatched = isset($already_snatched_ids[$tid]);

    // Теги
    $tagsHtml = '';
    if (!empty($Torrent['tags'])) {
        foreach (array_filter(array_map('trim', explode(',', (string)$Torrent['tags']))) as $tag) {
            $tagsHtml .= '<a href="' . $BASEURL . '/browse.php?do=search&amp;keywords=' . urlencode($tag) . '&amp;search_type=t_tags" class="br-tag">#' . $e($tag) . '</a>';
        }
    }

    // Постер
    $poster = !empty($Torrent['t_image'])
        ? '<img src="' . $e($Torrent['t_image']) . '" alt="" class="torrent-poster" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement(\'span\'),{className:\'torrent-poster-placeholder\'}))">'
        : '<span class="torrent-poster-placeholder"><i class="' . $e($catIcon) . '" aria-hidden="true"></i></span>';
    $zoom = !empty($Torrent['t_image']) ? ' data-zoom="' . $e($Torrent['t_image']) . '"' : '';

    // Поповер скачивания (как раньше; fa-sharp в сборке FA Pro нет - иконка была пустой)
    $popBody = '<div class="download-popover-content"><div class="torrent-info mb-3"><strong>' . $fullName . '</strong><div class="torrent-details mt-2">'
        . '<div class="detail-item"><i class="fa-solid fa-hard-drive me-2"></i><span>' . $sizeTxt . '</span></div>'
        . '<div class="detail-item"><i class="fa-regular fa-file me-2"></i><span>' . $e(sprintf($lang->browse['pop_files'], ts_nf($Torrent['numfiles']))) . '</span></div>'
        . ($s > 0 ? '<div class="detail-item text-success"><i class="fa-solid fa-arrow-up me-2"></i><span>' . $e(sprintf($lang->browse['pop_seeders'], ts_nf($s))) . '</span></div>' : '')
        . ($l > 0 ? '<div class="detail-item text-danger"><i class="fa-solid fa-arrow-down me-2"></i><span>' . $e(sprintf($lang->browse['pop_leechers'], ts_nf($l))) . '</span></div>' : '')
        . '</div></div><div class="text-center"><small class="text-muted">' . $e($lang->browse['pop_download_hint']) . '</small></div></div>';

    $d_link = '<a href="' . $dlUrl . '" class="st-icon-btn br-dl download-popover" aria-label="' . $e($lang->browse['pop_download_btn']) . '"'
        . ' data-bs-toggle="popover" data-bs-placement="top" data-bs-html="true" data-bs-trigger="hover focus"'
        . ' data-bs-title="' . $e($lang->browse['pop_download_title']) . '"'
        . ' data-bs-content="' . htmlspecialchars($popBody, ENT_QUOTES) . '">'
        . '<i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i></a>';

    // Аплоадер (имя экранируем до format_name(); у удалённого юзера username = NULL)
    $hideOwner = !$is_mod && (int)$Torrent['owner'] !== (int)$CURUSER['id'] && $Torrent['anonymous'] === 'yes';
    $anonIcon  = '<i class="fa-solid fa-user-secret br-anon" aria-hidden="true"></i>';
    if ($hideOwner) {
        $uploader = $anonIcon;
    } elseif (!empty($Torrent['username'])) {
        $uploader = '<a href="' . get_profile_link((int)$Torrent['owner']) . '">'
            . format_name(htmlspecialchars_uni((string)$Torrent['username']), (int)$Torrent['usergroup']) . '</a>'
            . ($Torrent['anonymous'] === 'yes' ? $anonIcon : '');
    } else {
        $uploader = '<span class="text-body-secondary">—</span>';
    }

    $nameHtml = $hlKeywords !== '' ? highlight($hlKeywords, $fullName, '<mark class="br-hl">', '</mark>') : $fullName;
    $flags    = GetTorrentTags($Torrent);

    $moderation = $is_mod ? '
    <td class="br-col-mod">
        <div class="form-check form-switch m-0">
            <input class="form-check-input" type="checkbox" role="switch"
                id="torrentid_' . $tid . '" name="torrentid[]" value="' . $tid . '"
                aria-label="' . $fullName . '"
                data-title="' . $fullName . '"
                data-image="' . $e($Torrent['t_image'] ?? '') . '"
                data-size="' . (int)$Torrent['size'] . '"
                data-seeders="' . $s . '"
                data-leechers="' . $l . '"
                data-category="' . (int)$Torrent['category'] . '"
                data-catname="' . $catLabel . '" />
        </div>
    </td>' : '';

    $rowsHtml .= '
<tr class="torrent-row' . ($isSnatched ? ' torrent-row-snatched' : '') . '" data-id="' . $tid . '" data-seeders="' . $s . '" data-leechers="' . $l . '">
  <td class="br-col-poster">
    <a href="' . $SEOLink . '" class="poster-link" tabindex="-1" aria-hidden="true"' . $zoom . '>' . $poster . '</a>
    <a href="' . $SEOLinkC . '" class="category-icon-link br-cat-badge" title="' . $catLabel . '"><i class="' . $e($catIcon) . '" aria-hidden="true"></i></a>
  </td>
  <td class="br-col-name">
    <div class="br-name">
      <a href="' . $SEOLink . '" class="torrent-name-link" title="' . $fullName . '">' . $nameHtml . '</a>
      <div class="torrent-actions-inline">
        ' . $d_link . '
        <span id="bookmark' . $count . '" class="br-bm">' . get_torrent_bookmark_state((int)$CURUSER['id'], $tid) . '</span>
      </div>
    </div>
    <div class="torrent-meta">
      <a href="' . $SEOLinkC . '" class="br-meta-cat"><i class="fa-solid fa-folder" aria-hidden="true"></i>' . $catLabel . '</a>
      <span><i class="fa-regular fa-clock" aria-hidden="true"></i>' . my_datee('relative', $Torrent['added']) . '</span>
      ' . ($isSnatched ? '<span class="st-badge st-soft-primary" title="' . $e($lang->browse['tip_already_snatched']) . '"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>' . $e($lang->browse['js_card_already']) . '</span>' : '') . '
      ' . ($flags ? '<span class="torrent-flags">' . $flags . '</span>' : '') . '
    </div>
    <div class="br-mstats">
      <span class="br-ms-num"><i class="fa-solid fa-hard-drive" aria-hidden="true"></i>' . $sizeTxt . '</span>
      <span class="br-ms-num"><i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i>' . ts_nf($Torrent['times_completed']) . '</span>
      <span class="br-mstats__up">' . $uploader . '</span>
    </div>
    ' . ($tagsHtml !== '' ? '<div class="br-tags">' . $tagsHtml . '</div>' : '') . '
  </td>
  <td class="br-col-num br-col-size">' . $sizeTxt . '</td>
  <td class="br-col-num br-col-snatch">
    <a href="' . $BASEURL . '/viewsnatches.php?id=' . $tid . '" title="' . $e($lang->browse['tip_snatched_count']) . '">' . ts_nf($Torrent['times_completed']) . '</a>
  </td>
  <td class="br-col-num">
    <span id="seeders_' . $tid . '"><a href="' . $peersUrl . '#seeders" class="br-peer br-peer--s' . ($s === 0 ? ' is-zero' : '') . '" title="' . $e($lang->browse['tip_current_seeders']) . '"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>' . ts_nf($s) . '</a></span>
  </td>
  <td class="br-col-num">
    <span id="leechers_' . $tid . '"><a href="' . $peersUrl . '#leechers" class="br-peer br-peer--l' . ($l === 0 ? ' is-zero' : '') . '" title="' . $e($lang->browse['tip_current_leechers']) . '"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i>' . ts_nf($l) . '</a></span>
  </td>
  <td class="br-col-up">' . $uploader . '</td>
  ' . $moderation . '
</tr>';

    $count++;
}


// ── Таблица ────────────────────────────────────────────────────────────────
$resultsLine = sprintf($lang->browse['res_found'], '<strong>' . ts_nf($threadcount) . '</strong>');

if ($rowsHtml !== '') {
    $tableHtml = '
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 br-table">
        <thead>
          <tr>
            <th class="br-col-poster"><span class="visually-hidden">' . $lang->browse['type'] . '</span></th>
            <th class="br-col-name">' . render_sort_header($lang->browse['hdr_name'], 'name', $page_url, $sortBy, $sortDir) . '</th>
            <th class="br-col-num br-col-size">' . render_sort_header($lang->browse['sortby6'], 'size', $page_url, $sortBy, $sortDir) . '</th>
            <th class="br-col-num br-col-snatch">' . render_sort_header($lang->browse['sortby7'], 'snatched', $page_url, $sortBy, $sortDir) . '</th>
            <th class="br-col-num">' . render_sort_header('<i class="fa-solid fa-arrow-up" title="' . $e($lang->browse['sortby4']) . '"></i><span class="br-th-txt">' . $lang->browse['sortby4'] . '</span>', 'seeders', $page_url, $sortBy, $sortDir) . '</th>
            <th class="br-col-num">' . render_sort_header('<i class="fa-solid fa-arrow-down" title="' . $e($lang->browse['sortby5']) . '"></i><span class="br-th-txt">' . $lang->browse['sortby5'] . '</span>', 'leechers', $page_url, $sortBy, $sortDir) . '</th>
            <th class="br-col-up">' . $lang->browse['sortby8'] . '</th>
            ' . ($is_mod ? '
            <th class="br-col-mod">
              <div class="form-check form-switch m-0" title="' . $e($lang->browse['tip_select_all_page']) . '">
                <input class="form-check-input" type="checkbox" id="checkAllSwitchVisible" role="switch" aria-label="' . $e($lang->browse['tip_select_all_page']) . '" />
              </div>
            </th>' : '') . '
          </tr>
        </thead>
        <tbody>' . $rowsHtml . '
        </tbody>
      </table>
    </div>';
} else {
    $tableHtml = '
    <div class="st-empty">
      <div class="st-ico st-soft-muted"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></div>
      <div class="br-empty__title">' . $lang->browse['empty_title'] . '</div>
      <div class="br-empty__hint">' . sprintf($lang->browse['empty_hint'], '<a href="' . $BASEURL . '/browse.php">' . $lang->browse['empty_reset_link'] . '</a>') . '</div>
    </div>';
}

$pagerTop    = $multipage !== '' ? '<div class="st-pager">' . $multipage . '</div>' : '';
$ListTorrents = '
<section class="st-card br-list mb-3" id="listtorrents">
  <div class="st-toolbar br-list__bar">
    <span class="br-list__found"><i class="fa-solid fa-filter" aria-hidden="true"></i>' . $resultsLine . '</span>
    ' . $pagerTop . '
  </div>
  ' . $tableHtml . '
  ' . ($multipage !== '' ? '<div class="st-toolbar st-toolbar--bottom">' . $pagerTop . '</div>' : '') . '
</section>';


// ── Модерация (только staff) ──────────────────────────────────────────────
$modBar = $modModals = $modFormOpen = $modFormClose = '';
if ($is_mod) {
    $modFormOpen = '
<form method="post" action="' . $BASEURL . '/admin/index.php?act=manage_torrents" name="manage_torrents" id="manage_torrents">
<input type="hidden" name="do" value="update" />
<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
<input type="hidden" name="return" value="yes" />
<input type="hidden" name="return_address" value="' . $BASEURL . '/browse.php?page=' . $page . '&amp;' . implode('&amp;', $Links) . '" />';
    $modFormClose = '</form>';

    $modBar = '
<div class="st-card st-toolbar br-modbar mb-3">
  <div class="d-flex flex-wrap align-items-center gap-2">
    <span class="st-ico st-soft-danger"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
    <span class="st-badge st-soft-primary" id="totalSelectedBadge">
      <i class="fas fa-check-circle me-1"></i>' . sprintf($lang->browse['mod_selected'], '<span id="selectedCountDisplay" aria-live="polite">0</span>') . '
    </span>
    <span class="st-badge st-soft-muted">
      <i class="fa-solid fa-list" aria-hidden="true"></i>' . sprintf($lang->browse['mod_total'], '<span id="totalTorrentsCount">' . $threadcount . '</span>') . '
    </span>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <button type="button" class="btn btn-sm btn-outline-primary st-pill" onclick="ModerationModal.selectAllFiltered(this)">
      <i class="fas fa-check-double me-1"></i>' . $lang->browse['btn_select_all_filtered'] . '
    </button>
    <button type="button" class="btn btn-sm btn-danger st-pill" data-bs-toggle="modal" data-bs-target="#moderationModal">
      <i class="fas fa-shield-alt me-1"></i>' . $lang->browse['pane_moderation'] . '
    </button>
  </div>
</div>';

    $modModals = '
<!-- Moderation Actions Modal -->
<div class="modal fade" id="moderationModal" tabindex="-1" aria-labelledby="moderationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
    <div class="modal-content mod-modal-content shadow-lg">

      <div class="modal-header mod-modal-header">
        <h5 class="modal-title" id="moderationModalLabel">
            <i class="fas fa-shield-alt me-2"></i>' . $lang->browse['pane_moderation'] . '
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="' . $e($lang->browse['btn_close']) . '"></button>
      </div>

      <div class="modal-body mod-modal-body">
        <div class="row">
            <div class="col-md-3 mod-poster-column">
                <div class="mod-poster-container" id="modPosterContainer">
                    <div class="mod-poster-placeholder" id="modPosterPlaceholder">
                        <i class="fas fa-images fa-3x text-muted"></i>
                        <p class="text-muted mt-2 small">' . $lang->browse['hint_select_to_preview'] . '</p>
                    </div>
                    <div class="mod-poster-wrapper" id="modPosterWrapper" style="display:none;">
                        <img id="modPosterPreview" src="" alt="' . $e($lang->browse['alt_torrent_poster']) . '" class="mod-poster-img">
                        <div class="mod-poster-count" id="modPosterCount"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="mod-selection-info alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    ' . sprintf($lang->browse['mod_torrents_selected'], '<strong id="modalSelectedCount" aria-live="polite">0</strong>') . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="' . $e($lang->browse['btn_close']) . '"></button>
                </div>

                <div class="mod-progress-container mb-2" id="modProgressContainer" style="display:none;">
                    <div class="d-flex justify-content-between small">
                        <span>' . sprintf($lang->browse['mod_progress'], '<strong id="selectedCount2">0</strong>', '<span id="totalCount2">0</span>') . '</span>
                        <span id="progressPercentage">0%</span>
                    </div>
                    <div class="progress" style="height:6px;border-radius:4px;">
                        <div id="selectionProgress" class="progress-bar" style="width:0%;"></div>
                    </div>
                </div>

                <div class="mod-restore-info" id="modRestoreInfo" style="display:none;">
                    <i class="fas fa-history me-1"></i>
                    <span id="modRestoreMessage">' . $lang->browse['mod_restored_default'] . '</span>
                    <button type="button" class="btn-close btn-close-sm float-end" id="modRestoreCloseBtn" aria-label="' . $e($lang->browse['btn_close']) . '"></button>
                </div>

                <div class="mod-summary-stats" id="modSummaryStats" style="display:none;">
                    <div class="mod-summary-stat"><i class="fas fa-hdd"></i><span id="modSummarySize">0 B</span></div>
                    <div class="mod-summary-stat"><i class="fas fa-arrow-up text-success"></i><span id="modSummarySeeders">0</span></div>
                    <div class="mod-summary-stat"><i class="fas fa-arrow-down text-danger"></i><span id="modSummaryLeechers">0</span></div>
                    <div class="mod-summary-stat"><i class="fas fa-folder"></i><span id="modSummaryCategories">0</span></div>
                </div>

                <div id="selectedTorrentsList" class="mod-selected-list" style="display:none;"></div>

                <label class="mod-label" for="actiontype">
                    <i class="fas fa-bolt me-1"></i>' . $lang->browse['lbl_choose_action'] . '
                </label>
                <select class="form-select mod-select" name="actiontype" id="actiontype">
                    <option value="0">⚙️ ' . $lang->browse['opt_select_action'] . '</option>
                    <optgroup label="── ' . $e($lang->browse['opt_grp_torrent']) . ' ──">
                        <option value="move">📁 ' . $lang->browse['opt_act_move'] . '</option>
                        <option value="delete">🗑️ ' . $lang->browse['opt_act_delete'] . '</option>
                        <option value="sticky">📌 ' . $lang->browse['opt_act_sticky'] . '</option>
                        <option value="visible">👁️ ' . $lang->browse['opt_act_visible'] . '</option>
                        <option value="banned">🚫 ' . $lang->browse['opt_act_banned'] . '</option>
                        <option value="nuke">☢️ ' . $lang->browse['opt_act_nuke'] . '</option>
                        <option value="openclose">💬 ' . $lang->browse['opt_act_openclose'] . '</option>
                    </optgroup>
                    <optgroup label="── ' . $e($lang->browse['opt_grp_promo']) . ' ──">
                        <option value="free">🎁 ' . $lang->browse['opt_act_free'] . '</option>
                        <option value="silver">🥈 ' . $lang->browse['opt_act_silver'] . '</option>
                        <option value="doubleupload">⏫ ' . $lang->browse['opt_act_doubleupload'] . '</option>
                        <option value="thirtypercent">🟣 ' . $lang->browse['opt_act_thirtypercent'] . '</option>
                    </optgroup>
                    <optgroup label="── ' . $e($lang->browse['opt_grp_other']) . ' ──">
                        <option value="anonymous">🎭 ' . $lang->browse['opt_act_anonymous'] . '</option>
                        <option value="request">📩 ' . $lang->browse['opt_act_request'] . '</option>
                        <option value="resetrating">⭐ ' . $lang->browse['opt_act_resetrating'] . '</option>
                    </optgroup>
                </select>

                <div id="movetorrent" class="mod-move-block" style="display:none;">
                    <div class="mod-move-container">
                        <span class="mod-move-label">
                            <i class="fas fa-folder-open me-1"></i>' . $lang->browse['lbl_move_to'] . '
                        </span>
                        ' . $movedropdown . '
                    </div>
                    <div id="modMoveCategoryWarning" class="mod-move-mismatch-warning" style="display:none;">
                        <i class="fas fa-triangle-exclamation me-1"></i>
                        <span id="modMoveCategoryWarningText">' . $lang->browse['mod_mixed_categories'] . '</span>
                    </div>
                </div>

                <div id="actionInfo" class="mod-action-info" style="display:none;">
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="actionDescription">' . $lang->browse['mod_action_default'] . '</span>
                    </div>
                </div>

                <div class="mod-hint">
                    <i class="fas fa-circle-info me-1"></i>
                    <span>' . $lang->browse['hint_mod'] . '</span>
                </div>
            </div>
        </div>
      </div>

      <div class="modal-footer mod-modal-footer">
        <button type="button" class="btn btn-outline-danger btn-sm me-auto" id="clearAllSelectionsBtn">
            <i class="fas fa-trash-alt me-1"></i>' . $lang->browse['btn_clear_all'] . '
        </button>
        <button type="button" class="btn btn-mod-cancel" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>' . $lang->browse['btn_cancel'] . '
        </button>
        <button type="button" class="btn btn-mod-apply" id="applyActionBtn">
            <i class="fas fa-play me-1"></i> ' . $lang->browse['btn_apply'] . '
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Confirmation modal -->
<div class="modal fade" id="modConfirmModal" tabindex="-1" aria-labelledby="modConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content mod-modal-content shadow-lg">
      <div class="modal-header mod-modal-header mod-modal-header-danger">
        <h5 class="modal-title" id="modConfirmModalLabel">
            <i class="fas fa-exclamation-triangle me-2"></i>' . $lang->browse['pane_confirm'] . '
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="' . $e($lang->browse['btn_close']) . '"></button>
      </div>
      <div class="modal-body mod-modal-body">
        <p id="modConfirmMessage" class="mb-0"></p>
        <div id="modConfirmTorrentList" class="mod-selected-list mt-3" style="display:none;"></div>
      </div>
      <div class="modal-footer mod-modal-footer">
        <button type="button" class="btn btn-mod-cancel" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>' . $lang->browse['btn_cancel'] . '
        </button>
        <button type="button" class="btn btn-mod-danger" id="modConfirmAcceptBtn">
            <i class="fas fa-check me-1"></i>' . $lang->browse['btn_confirm'] . '
        </button>
      </div>
    </div>
  </div>
</div>

<script>window.browseModTotalTorrents = ' . $threadcount . ';</script>';
}


// ── Новые раздачи (карусель постеров) ─────────────────────────────────────
$carouselItems = [];
$carouselQuery = $db->sql_query_prepared(
    "SELECT id, name, t_image FROM torrents
     WHERE t_image != '' AND visible = 'yes' AND banned = 'no'
     ORDER BY added DESC
     LIMIT ?",
    [15]
);
while ($carouselQuery && ($row2 = $db->fetch_array($carouselQuery))) {
    $carouselItems[] = $row2;
}

$carouselHtml = '';
if ($carouselItems) {
    $slides = $thumbs = '';
    foreach ($carouselItems as $i => $row2) {
        $cTitle = $e($row2['name']);
        $cImg   = $e($row2['t_image']);
        $cLink  = get_torrent_link((int)$row2['id']);
        $slides .= '<div class="carousel-item' . ($i === 0 ? ' active' : '') . '">'
            . '<a href="' . $cLink . '" class="br-slide"><img src="' . $cImg . '" alt="' . $cTitle . '" loading="' . ($i === 0 ? 'eager' : 'lazy') . '">'
            . '<span class="br-slide__cap">' . $cTitle . '</span></a></div>';
        $thumbs .= '<button type="button" class="br-thumb' . ($i === 0 ? ' active' : '') . '" data-bs-target="#cachedTorrentCarousel" data-bs-slide-to="' . $i . '"'
            . ($i === 0 ? ' aria-current="true"' : '') . ' aria-label="' . $e(sprintf($lang->browse['lbl_slide'], $i + 1)) . '">'
            . '<img src="' . $cImg . '" alt="" loading="lazy"></button>';
    }

    $carouselHtml = '
<section class="st-card br-new mb-3">
  <button type="button" class="br-fold st-card__head" data-br-fold="brNew" aria-expanded="true" aria-controls="brNew">
    <span class="st-ico st-soft-info"><i class="fa-solid fa-images" aria-hidden="true"></i></span>
    <span class="br-fold__title">' . $lang->browse['recent_torrents'] . '</span>
    <i class="fa-solid fa-chevron-down br-fold__chev" aria-hidden="true"></i>
  </button>
  <div id="brNew" class="st-card__body">
    <div id="cachedTorrentCarousel" class="carousel slide br-carousel" data-bs-ride="carousel" data-bs-interval="4000">
      <div class="carousel-inner">' . $slides . '</div>
      <button class="carousel-control-prev" type="button" data-bs-target="#cachedTorrentCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">' . $e($lang->browse['lbl_prev']) . '</span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#cachedTorrentCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">' . $e($lang->browse['lbl_next']) . '</span>
      </button>
    </div>
    <div class="br-thumbs" data-br-thumbs="#cachedTorrentCarousel">' . $thumbs . '</div>
  </div>
</section>';
}


// ── Шапка страницы + KPI ───────────────────────────────────────────────────
$headMeta = '<span><i class="fa-solid fa-filter"></i>' . $resultsLine . '</span>';
if ($category) {
    $headMeta .= '<span><i class="fa-solid fa-folder"></i>' . $e($catName) . '</span>';
}
if ($search_type !== '' && $search_type !== 't_both' && isset($searchTypeLabels[$search_type])) {
    $headMeta .= '<span><i class="fa-solid fa-magnifying-glass"></i>' . sprintf($lang->browse['res_by'], $searchTypeLabels[$search_type]) . '</span>';
}
if ($daysprune > 0) {
    $headMeta .= '<span><i class="fa-regular fa-clock"></i>' . $e($daysprune_options[$daysprune] ?? sprintf($lang->browse['flt_last_n_days'], $daysprune)) . '</span>';
}

$headerHtml = '
<header class="st-card st-head mb-3">
  <div class="st-head__icon st-soft-primary"><i class="fa-solid fa-compass" aria-hidden="true"></i></div>
  <div class="min-w-0 flex-grow-1">
    <h1 class="st-title">' . $lang->browse['btitle'] . '</h1>
    <div class="st-head__meta">' . $headMeta . '</div>
  </div>
  <div class="br-head__actions">
    ' . (!empty($activeFilters) ? '<a href="' . $resetUrl . '" class="btn btn-sm btn-outline-secondary st-pill"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>' . $lang->browse['btn_reset_filters'] . '</a>' : '') . '
    <a href="' . $rssUrl . '" class="btn btn-sm btn-outline-warning st-pill"><i class="fa-solid fa-rss me-1" aria-hidden="true"></i>' . $lang->browse['btn_rss'] . '</a>
  </div>
</header>';

// 4 плитки из того же запроса $smartStatsSql (учитывают текущие фильтры);
// клик = применить соответствующий быстрый фильтр.
$kpis = [
    [['health' => 'seeded'],        'fa-heart-pulse',       $smartStats['active'],   $lang->browse['qf_active']],
    [['no_seeders' => '1'],         'fa-skull-crossbones',  $smartStats['dead'],     $lang->browse['flt_no_seeders']],
    [['daysprune' => '7'],          'fa-clock',             $smartStats['last_7d'],  $lang->browse['opt_days_7']],
    [['freeleech_only' => '1'],     'fa-gift',              $smartStats['freeleech'], $lang->browse['bday_fl_badge'] ?? 'Freeleech'],
];
$kpiHtml = '<div class="br-kpis mb-3">';
foreach ($kpis as [$kChanges, $kIcon, $kVal, $kLbl]) {
    $kpiHtml .= '<a href="' . $smartPreset($kChanges) . '" class="st-kpi st-kpi--muted' . ($smartPresetActive($kChanges) ? ' is-active' : '') . '">'
        . '<div class="min-w-0"><div class="st-kpi__val">' . ts_nf($kVal) . '</div><div class="st-kpi__lbl">' . $kLbl . '</div></div>'
        . '<i class="fa-solid ' . $kIcon . ' st-kpi__bg" aria-hidden="true"></i></a>';
}
$kpiHtml .= '</div>';

$categoriesHtml = $catTiles !== '' ? '
<section class="st-card br-cats mb-3">
  <button type="button" class="br-fold st-card__head" data-br-fold="brCats" aria-expanded="true" aria-controls="brCats">
    <span class="st-ico st-soft-info"><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i></span>
    <span class="br-fold__title">' . $lang->browse['tcategory'] . '</span>
    <i class="fa-solid fa-chevron-down br-fold__chev" aria-hidden="true"></i>
  </button>
  <div id="brCats" class="st-card__body br-cats__grid">' . $catTiles . '</div>
</section>' : '';


// ── Вывод ──────────────────────────────────────────────────────────────────
stdhead($lang->browse['btitle']);

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=7">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/browse.css?ver=3">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/airbnb.css">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">';
if ($is_mod) {
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/moderation-modal.css">';
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/browse-moderation.css">';
}

// Флеш-сообщения после модерации и «восстановить фильтры» - в browse.js
$flashOk  = is_string($_GET['mod_success'] ?? null) ? $_GET['mod_success'] : '';
$flashErr = is_string($_GET['mod_error'] ?? null) ? $_GET['mod_error'] : '';

echo '
<div class="container-md st-page br-page"'
    . ' data-has-filters="' . (!empty($activeFilters) ? '1' : '0') . '"'
    . ($flashOk !== '' ? ' data-flash-ok="' . htmlspecialchars($flashOk, ENT_QUOTES) . '"' : '')
    . ($flashErr !== '' ? ' data-flash-err="' . htmlspecialchars($flashErr, ENT_QUOTES) . '"' : '') . '>
  <div id="brRestoreSlot"></div>
  ' . $___notice . '
  ' . $headerHtml . '
  ' . $kpiHtml . '
  ' . $carouselHtml . '
  ' . $categoriesHtml . '
  ' . $SearchTorrent . '
  ' . $modFormOpen . '
  ' . $modBar . '
  ' . $ListTorrents . '
  ' . $modModals . '
  ' . $modFormClose . '
</div>

<div class="poster-zoom-overlay" id="posterZoomOverlay" aria-hidden="true">
  <img src="" alt="" class="poster-zoom-img" id="posterZoomImg">
</div>';


// JS-строки: ключи js_* из ланга -> AGS_LANG без префикса
$agsJsLang = [];
foreach ($lang->browse as $agsKey => $agsVal) {
    if (str_starts_with((string)$agsKey, 'js_')) {
        $agsJsLang[substr((string)$agsKey, 3)] = preg_replace('/%(\d+)\$s/', '{$1}', (string)$agsVal);
    }
}

// Ключи js_bm_* для bookmark.js (лежат в ланге details) -> AGS_LANG без префикса js_
ags_bookmark_lang();
foreach ($lang->details ?? [] as $agsKey => $agsVal) {
    if (str_starts_with((string)$agsKey, 'js_bm_')) {
        $agsJsLang[substr((string)$agsKey, 3)] = preg_replace('/%(\d+)\$s/', '{$1}', (string)$agsVal);
    }
}

echo '<script>const AGS_LANG = ' . json_encode($agsJsLang, $agsJsonFlags) . ';</script>';

// Уведомления: SweetAlert2 (swal-notify.js даёт тот же showToast())
echo '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>';
echo '<script src="' . $BASEURL . '/scripts/swal-notify.js?ver=2"></script>';
echo '<script src="' . $BASEURL . '/scripts/bookmark.js?ver=7"></script>';
echo '<script src="' . $BASEURL . '/scripts/popover.js"></script>';
echo '<script src="' . $BASEURL . '/admin/scripts/flatpickr.js"></script>';
echo '<script src="' . $BASEURL . '/scripts/browse.js?ver=3"></script>';

if ($is_mod) {
    echo '<script src="' . $BASEURL . '/scripts/browse-moderation.js?ver=3"></script>';
}

stdfoot();
