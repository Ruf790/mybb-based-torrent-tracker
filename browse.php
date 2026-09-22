<?php

declare(strict_types=1);

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'browse.php');
define('B_VERSION', '6.6.3');
define("SCRIPTNAME", "browse.php");


require './global.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_bookmark.php';

maxsysop();


if (empty($CURUSER['id'])) {
    print_no_permission();
}


$lang->load('browse');

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


$search_type = trim($_POST['search_type'] ?? $_GET['search_type'] ?? '');

$special_search        = trim($_GET['special_search']        ?? $_POST['special_search']        ?? '');
$sort      = trim($_GET['sort']      ?? $_POST['sort']      ?? '');
$order     = trim($_GET['order']     ?? $_POST['order']     ?? '');
//$daysprune = (int)($_GET['daysprune'] ?? $_POST['daysprune'] ?? 0);
$include_dead_torrents = trim($_GET['include_dead_torrents'] ?? $_POST['include_dead_torrents'] ?? '');


$Links = [];
require_once INC_PATH . '/functions_mkprettytime.php';




$_freelechmod = $_silverleechmod = $_x2mod = false;
$___notice = '';
include TSDIR . '/cache/freeleech.php';


if ($__F_START < get_date_time() && $__F_END > get_date_time()) {
    
    switch($__FLSTYPE) {
        case 'freeleech':
            $___notice = '
            <br><br>
            <div class="container md">
            <div class="card error-card4 fade show">
              <div class="card-header4">
                <i class="bi bi-exclamation-triangle-fill error-icon4"></i>
                <div><h2 class="mb-0">All torrents are Free Leech</h2></div>
              </div>
              <div class="card-body">
                <div class="alert4 alert-info" role="alert">
                 ' . sprintf($lang->browse['f_leech'], $__F_START, $__F_END) . '
                </div>
              </div>
            </div>
            </div>';

            $_freelechmod = true;
            echo '<link href="' . $BASEURL . '/include/templates/default/style/messagess.css" rel="stylesheet">';
            break;

        case 'silverleech':
            $___notice = '
            <br><br>
            <div class="container md">
            <div class="card error-card4 fade show">
              <div class="card-header4">
                <i class="bi bi-exclamation-triangle-fill error-icon4"></i>
                <div><h2 class="mb-0">All torrents are Silver Leech</h2></div>
              </div>
              <div class="card-body">
                <div class="alert4 alert-info" role="alert">
                ' . sprintf($lang->browse['s_leech'], $__F_START, $__F_END) . '
                </div>
              </div>
            </div>
            </div>';

            $_silverleechmod = true;
            
            echo '<link href="' . $BASEURL . '/include/templates/default/style/messagess.css" rel="stylesheet">';
            break;

        case 'doubleupload':
            $___notice = '
            <br><br>
            <div class="container md">
            <div class="card error-card4 fade show">
              <div class="card-header4">
                <i class="bi bi-exclamation-triangle-fill error-icon4"></i>
                <div><h2 class="mb-0">All torrents are Double Upload (x2)</h2></div>
              </div>
              <div class="card-body">
                <div class="alert4 alert-info" role="alert">
                 ' . sprintf($lang->browse['d_leech'], $__F_START, $__F_END) . '
                </div>
              </div>
            </div>
            </div>';

            $_x2mod = true;
            
            echo '<link href="' . $BASEURL . '/include/templates/default/style/messagess.css" rel="stylesheet">';
            break;
    }
} elseif ($bdayreward === 'yes' && $bdayrewardtype) {
    $curuserbday = !empty($CURUSER['birthday']) ? explode('-', $CURUSER['birthday']) : [];
    if (isset($curuserbday[0], $curuserbday[1]) && date('j-n') === $curuserbday[0] . '-' . $curuserbday[1]) {
        switch ($bdayrewardtype) {
            case 'freeleech':
                $___notice = '
<div class="container mt-3">
   <div class="alert alert-primary">
    <span id="new_ann" style="display: block;">
    ' . sprintf($lang->browse['f_leech'], $curuserbday[0] . '-' . $curuserbday[1] . '-' . date('Y'), ($curuserbday[0] + 1) . '-' . $curuserbday[1] . '-' . date('Y')) . '
  </div>
</div>';
                break;
            case 'silverleech':
                $___notice = show_notice(sprintf($lang->browse['s_leech'], $curuserbday[0] . '-' . $curuserbday[1] . '-' . date('Y'), ($curuserbday[0] + 1) . '-' . $curuserbday[1] . '-' . date('Y')), false, $lang->browse['s_leech_h']);
                break;
            case 'doubleupload':
                $___notice = show_notice(sprintf($lang->browse['d_leech'], $curuserbday[0] . '-' . $curuserbday[1] . '-' . date('Y'), ($curuserbday[0] + 1) . '-' . $curuserbday[1] . '-' . date('Y')), false, $lang->browse['d_leech_h']);
                break;
        }
    }
}







require TSDIR . '/cache/categories.php';
$subcategories = [];
$searcincategories = [];

if (count($_categoriesS) > 0) {
    foreach ($_categoriesS as $sc) {

        $sc['name'] = htmlspecialchars_uni($sc['name']);
        $searcincategories[] = $sc['id'];

        $SEOLinkC = get_category_link($sc['id']);

        $subcategories[$sc['pid']][] = '
        <span id="category' . $sc['id'] . '"' . (
            (isset($category) && $category === $sc['id']) ||
            (!$category && str_contains(($CURUSER['notifs'] ?? ''), '[cat' . $sc['id'] . ']'))
            ? ' class="highlight"'
            : ''
        ) . '>
            <a href="' . $SEOLinkC . '" title="' . $sc['name'] . '">' . $sc['name'] . '</a>
        </span>';
    }

    unset($_categoriesS);
}

$count = 0;

$categories = '

<div class="container mt-3">
 
  <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
  
<table>
<tbody>
	<tr>
	<div class="card-header bg-gradient bg-primary text-white py-2 px-3">
            
        <i class="fas fa-th-large me-2"></i>'.$lang->browse['tcategory'].'
            
        </div>			
	</tr>
	
	<tr>
		<td align="center">
			<table border="0" cellspacing="0" cellpadding="0" align="left">
				<tr class="none">';






$catIconMap = [];
if (($rows = count($_categoriesC)) > 0) {
    foreach ($_categoriesC as $c) {
        $catIconMap[$c['id']] = $c['icon'];
		$tracker_cats_per_row = '5';
        $table_cat_width = '';
        $table_cat_height = '';

        $searcincategories[] = $c['id'];
        if ($count && $count % $tracker_cats_per_row === 0) {
            $categories .= '</tr><tr class="none">';
        }

        $tracker_cats_width = '';
        $cname = htmlspecialchars_uni($c['name']);
        $SEOLinkC = get_category_link($c['id']);

$categories .= '
<td class="p-2">
    <div class="d-flex border rounded p-2 category-container" data-category-id="' . $c['id'] . '">
        <div class="text-center">
            <a href="' . $SEOLinkC . '" class="d-block">
                <i class="' . $c['icon'] . ' fa-2x category-icon" title="' . $cname . '"></i>
            </a>
        </div>

        <div class="ms-2" style="width: ' . $tracker_cats_width . 'px;">

            <span id="category' . $c['id'] . '"' . (
                (isset($category) && $category === $c['id']) ||
                (
                    !$category &&
                    str_contains(($CURUSER['notifs'] ?? ''), '[cat' . $c['id'] . ']')
                )
                ? ' class="fw-bold text-primary"'
                : ''
            ) . '>

                <a href="' . $SEOLinkC . '" title="' . $cname . '" class="text-decoration-none category-link" data-cat-id="' . $c['id'] . '">
                    <h6 class="mb-1">' . $cname . '</h6>
                </a>

            </span>

            <div class="small text-muted">
                ' . (isset($subcategories[$c['id']]) ? implode(', ', $subcategories[$c['id']]) : '') . '
            </div>

        </div>
    </div>
</td>';

        $count++;
    }

    // Имя выбранной категории для хлебных крошек - ищем, пока массив
    // ещё не очищен ниже.
    $catName = 'All';
    if ($category) {
        foreach ($_categoriesC as $c) {
            if ((int)$c['id'] === $category) {
                $catName = $c['name'];
                break;
            }
        }
    }

    unset($_categoriesC);
}

$categories .= '
</tr>
			</table>
		</td>
	</tr>
</tbody>
</table>
 </div>
</div>

';







require_once INC_PATH . '/functions_category.php';
$catdropdown = ts_category_list('category', ($category ?? ''), '<option value="0" style="color: gray;">' . $lang->browse['alltypes'] . '</option>', 'categories');












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
    ''    => 'Any Seeders',
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
    $seeders_select .= '<option value="' . $val . '"' . $selected . '>' . $label . '</option>';
}
$seeders_select .= '</select>';

$size_options_min = [
    ''            => 'Min Size',
    '536870912'   => '0.5 GB',
    '1073741824'  => '1 GB',
    '2147483648'  => '2 GB',
    '5368709120'  => '5 GB',
    '10737418240' => '10 GB',
    '21474836480' => '20 GB',
    '53687091200' => '50 GB',
];

$size_options_max = [
    ''             => 'Max Size',
    '536870912'    => '0.5 GB',
    '1073741824'   => '1 GB',
    '2147483648'   => '2 GB',
    '5368709120'   => '5 GB',
    '10737418240'  => '10 GB',
    '21474836480'  => '20 GB',
    '53687091200'  => '50 GB',
    '107374182400' => '100 GB',
];

$size_min_select = '<select class="form-select" name="size_min">';
foreach ($size_options_min as $val => $label) {
    $selected = ($size_min == $val) ? ' selected' : '';
    $size_min_select .= '<option value="' . $val . '"' . $selected . '>' . $label . '</option>';
}
$size_min_select .= '</select>';

$size_max_select = '<select class="form-select" name="size_max">';
foreach ($size_options_max as $val => $label) {
    $selected = ($size_max == $val) ? ' selected' : '';
    $size_max_select .= '<option value="' . $val . '"' . $selected . '>' . $label . '</option>';
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
$added_from = $to_ts_browse($_GET['added_from'] ?? null, false);
$added_to   = $to_ts_browse($_GET['added_to'] ?? null, true);

$daysprune_options = [
    0   => 'Any time',
    1   => 'Last 24 hours',
    7   => 'Last 7 days',
    30  => 'Last 30 days',
    90  => 'Last 3 months',
    180 => 'Last 6 months',
    365 => 'Last year',
];

$daysprune_select = '<select class="form-select" name="daysprune" id="daysprune">';
foreach ($daysprune_options as $val => $label) {
    $selected = ((int)$daysprune === $val) ? ' selected' : '';
    $daysprune_select .= '<option value="' . $val . '"' . $selected . '>' . $label . '</option>';
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
    $activeFilters[] = ['label' => 'Category #' . $category, 'param' => 'category'];
}
if ($size_min_val) {
    $activeFilters[] = ['label' => 'Size ≥ ' . mksize($size_min_val), 'param' => 'size_min'];
}
if ($size_max_val) {
    $activeFilters[] = ['label' => 'Size ≤ ' . mksize($size_max_val), 'param' => 'size_max'];
}
if ($min_seeders_val) {
    $activeFilters[] = ['label' => 'Seeders ≥ ' . $min_seeders_val, 'param' => 'min_seeders'];
}
if ($keywords !== '') {
    $activeFilters[] = ['label' => 'Search: ' . htmlspecialchars_uni($keywords), 'param' => 'keywords'];
}
if ($search_type !== '' && $search_type !== 't_both') {
    $activeFilters[] = ['label' => 'Type: ' . htmlspecialchars_uni($search_type), 'param' => 'search_type'];
}
if ($special_search !== '') {
    $activeFilters[] = ['label' => 'Special: ' . htmlspecialchars_uni($special_search), 'param' => 'special_search'];
}
if ($include_dead_torrents === 'yes') {
    $activeFilters[] = ['label' => 'Include dead', 'param' => 'include_dead_torrents'];
}
if ($freeleech_only) {
    $activeFilters[] = ['label' => 'Free Leech only', 'param' => 'freeleech_only'];
}
if ($no_seeders_only) {
    $activeFilters[] = ['label' => 'No seeders', 'param' => 'no_seeders'];
}
if ($seeders_gt_leechers) {
    $activeFilters[] = ['label' => 'Seeders > Leechers', 'param' => 'seeders_gt_leechers'];
}
if ($hide_downloaded) {
    $activeFilters[] = ['label' => 'Hide downloaded', 'param' => 'hide_downloaded'];
}
if ($imdb_min > 0) {
    $activeFilters[] = ['label' => 'IMDb ' . $imdb_min . '+', 'param' => 'imdb_min'];
}

if ($daysprune > 0) {
    $daysLabel = $daysprune_options[$daysprune] ?? ('Last ' . $daysprune . ' days');
    $activeFilters[] = ['label' => $daysLabel, 'param' => 'daysprune'];
}
if ($added_from) {
    $activeFilters[] = ['label' => 'Added from ' . htmlspecialchars($_GET['added_from']), 'param' => 'added_from'];
}
if ($added_to) {
    $activeFilters[] = ['label' => 'Added until ' . htmlspecialchars($_GET['added_to']), 'param' => 'added_to'];
}

$filterGroups = [
    'search'   => ['icon' => 'fa-magnifying-glass', 'color' => 'primary'],
    'category' => ['icon' => 'fa-folder',           'color' => 'info'],
    'health'   => ['icon' => 'fa-heart-pulse',      'color' => 'success'],
    'size'     => ['icon' => 'fa-hdd',              'color' => 'warning'],
    'date'     => ['icon' => 'fa-calendar',         'color' => 'secondary'],
    'promo'    => ['icon' => 'fa-gift',             'color' => 'danger'],
    'rating'   => ['icon' => 'fa-star',             'color' => 'warning'],
    'personal' => ['icon' => 'fa-user',             'color' => 'dark'],
];

$activeFiltersHtml = '';
if (!empty($activeFilters)) {
    $activeFiltersHtml = '<div class="d-flex flex-wrap gap-2 mt-2 mb-2">';
    foreach ($activeFilters as $f) {
        // Учитывает все параметры, включая добавленные позже (category,
        // include_dead_torrents, hide_downloaded, imdb_min, special_search) -
        // в исходном варианте они все молча попадали в "search" по умолчанию.
        $group = match (true) {
            in_array($f['param'], ['keywords', 'search_type'], true) => 'search',
            $f['param'] === 'category' => 'category',
            str_contains($f['param'], 'size') => 'size',
            str_contains($f['param'], 'seeders') => 'health',
            in_array($f['param'], ['daysprune', 'added_from', 'added_to'], true) => 'date',
            str_contains($f['param'], 'freeleech') => 'promo',
            $f['param'] === 'imdb_min' => 'rating',
            in_array($f['param'], ['special_search', 'hide_downloaded', 'include_dead_torrents'], true) => 'personal',
            default => 'search',
        };
        $g = $filterGroups[$group] ?? $filterGroups['search'];
        $rmUrl = browse_url_without($_SERVER['SCRIPT_NAME'], $_GET, $f['param']);
        $activeFiltersHtml .=
            '<a href="' . htmlspecialchars($rmUrl, ENT_QUOTES) . '" ' .
            'class="badge bg-' . $g['color'] . ' bg-opacity-10 text-' . $g['color'] . ' border border-' . $g['color'] . ' text-decoration-none py-2 px-3">' .
            '<i class="fa-solid ' . $g['icon'] . ' me-1"></i>' .
            $f['label'] .
            ' <i class="bi bi-x-lg ms-1"></i>' .
            '</a>';
    }
    $activeFiltersHtml .= '</div>';
}

$resetFiltersBtn = '<a href="' . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES) . '" class="btn btn-outline-secondary btn-sm">'
    . '<i class="fa-solid fa-rotate-left me-1"></i>Reset filters</a>';

// Quick Filters: быстрые пресеты используют существующие фильтры каталога.
// По умолчанию включены (не нужно нажимать отдельную кнопку) - выключить
// можно через ссылку "Classic view" (smart_browse=0).
$smartBrowse = (($_GET['smart_browse'] ?? '1') !== '0');

$smartPreset = static function (array $changes): string {
    $params = $_GET;
    foreach ($changes as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $params['smart_browse'] = '1';
    return htmlspecialchars($_SERVER['SCRIPT_NAME'] . '?' . http_build_query($params), ENT_QUOTES);
};

// Активная подсветка пресета - раньше кнопки всегда выглядели одинаково,
// даже если фильтр уже применён, никакой визуальной обратной связи не было.
$smartPresetActive = static function (array $changes): bool {
    foreach ($changes as $key => $value) {
        $current = $_GET[$key] ?? null;
        if ($value === null) {
            if ($current !== null) {
                return false;
            }
        } elseif ((string)$current !== (string)$value) {
            return false;
        }
    }
    return true;
};
$smartBtnClass = static function (array $changes) use ($smartPresetActive): string {
    return $smartPresetActive($changes) ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-primary';
};

$smartBrowseHtml = '';
if ($smartBrowse) {
    $smartBrowseHtml = '
    <section class="card border-0 shadow-sm mb-3 smart-browse-panel" style="border-radius:18px;">
      <div class="card-body p-3 p-lg-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 smart-browse-heading">
          <div><div class="smart-eyebrow">DISCOVER</div><h4 class="mb-1"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Quick Filters</h4>
          <small>Find your next torrent with quick, focused filters.</small></div>
        </div>
        
        <div class="smart-filter-label">QUICK FILTERS</div>
        <div class="d-flex flex-wrap gap-2 mb-2 smart-filter-buttons">
          <a class="' . $smartBtnClass(['health' => 'seeded']) . '" href="' . $smartPreset(['health' => 'seeded']) . '"><i class="fa-solid fa-heart-pulse me-1"></i>Has seeders <span class="smart-preset-count">(@@CNT-SEEDED@@)</span></a>
          <a class="' . $smartBtnClass(['sort' => 'seeders', 'dir' => 'desc']) . '" href="' . $smartPreset(['sort' => 'seeders', 'dir' => 'desc']) . '"><i class="fa-solid fa-arrow-down-wide-short me-1"></i>Most seeders</a>
          <a class="' . $smartBtnClass(['min_seeders' => '1']) . '" href="' . $smartPreset(['min_seeders' => '1']) . '"><i class="fa-solid fa-signal me-1"></i>Active torrents <span class="smart-preset-count">(@@CNT-SEEDED@@)</span></a>
          <a class="' . $smartBtnClass(['daysprune' => '7']) . '" href="' . $smartPreset(['daysprune' => '7']) . '"><i class="fa-regular fa-clock me-1"></i>Last 7 days <span class="smart-preset-count">(@@CNT-7D@@)</span></a>
          <a class="' . $smartBtnClass(['daysprune' => '30']) . '" href="' . $smartPreset(['daysprune' => '30']) . '"><i class="fa-regular fa-calendar me-1"></i>Last 30 days <span class="smart-preset-count">(@@CNT-30D@@)</span></a>
          <a class="' . $smartBtnClass(['size_min' => '5368709120']) . '" href="' . $smartPreset(['size_min' => '5368709120']) . '"><i class="fa-solid fa-database me-1"></i>5 GB+ <span class="smart-preset-count">(@@CNT-BIG@@)</span></a>
          <a class="' . $smartBtnClass(['freeleech_only' => '1']) . '" href="' . $smartPreset(['freeleech_only' => '1']) . '"><i class="bi bi-gift me-1"></i>Free Leech only <span class="smart-preset-count">(@@CNT-FL@@)</span></a>
        </div>

        <div class="smart-filter-label">POPULAR</div>
        <div class="d-flex flex-wrap gap-2 mb-2 smart-filter-buttons">
          <a class="' . $smartBtnClass(['sort' => 'snatched', 'dir' => 'desc']) . '" href="' . $smartPreset(['sort' => 'snatched', 'dir' => 'desc']) . '"><i class="fa-solid fa-fire me-1"></i>Most downloaded</a>
          <a class="' . $smartBtnClass(['sort' => 'leechers', 'dir' => 'desc']) . '" href="' . $smartPreset(['sort' => 'leechers', 'dir' => 'desc']) . '"><i class="fa-solid fa-arrow-down-wide-short me-1"></i>Most leeched</a>
        </div>

        <div class="smart-filter-label">HEALTH</div>
        <div class="d-flex flex-wrap gap-2 mb-2 smart-filter-buttons">
          <a class="' . $smartBtnClass(['no_seeders' => '1']) . '" href="' . $smartPreset(['no_seeders' => '1']) . '"><i class="fa-solid fa-skull-crossbones me-1"></i>No seeders</a>
          <a class="' . $smartBtnClass(['seeders_gt_leechers' => '1']) . '" href="' . $smartPreset(['seeders_gt_leechers' => '1']) . '"><i class="fa-solid fa-scale-balanced me-1"></i>Seeders &gt; Leechers</a>
        </div>

        <div class="smart-filter-label">FRESHNESS &amp; SIZE</div>
        <div class="d-flex flex-wrap gap-2 mb-2 smart-filter-buttons">
          <a class="' . $smartBtnClass(['daysprune' => '1']) . '" href="' . $smartPreset(['daysprune' => '1']) . '"><i class="fa-solid fa-star me-1"></i>Added today</a>
          <a class="' . $smartBtnClass(['daysprune' => '3']) . '" href="' . $smartPreset(['daysprune' => '3']) . '"><i class="fa-regular fa-clock me-1"></i>Last 3 days</a>
          <a class="' . $smartBtnClass(['size_max' => '536870912']) . '" href="' . $smartPreset(['size_max' => '536870912']) . '"><i class="fa-solid fa-mobile-screen me-1"></i>Small (&lt;0.5GB)</a>
          <a class="' . $smartBtnClass(['size_max' => '1073741824']) . '" href="' . $smartPreset(['size_max' => '1073741824']) . '"><i class="fa-solid fa-mobile-screen me-1"></i>Small torrents (&lt;1GB)</a>
          <a class="' . $smartBtnClass(['size_min' => '10737418240']) . '" href="' . $smartPreset(['size_min' => '10737418240']) . '"><i class="fa-solid fa-database me-1"></i>10 GB+</a>
          <a class="' . $smartBtnClass(['size_min' => '53687091200']) . '" href="' . $smartPreset(['size_min' => '53687091200']) . '"><i class="fa-solid fa-database me-1"></i>50 GB+</a>' .
          ($CURUSER['id'] ? '
          <a class="' . $smartBtnClass(['hide_downloaded' => '1']) . '" href="' . $smartPreset(['hide_downloaded' => '1']) . '"><i class="fa-solid fa-eye-slash me-1"></i>Hide downloaded</a>' : '') . '
        </div>' .
        ($CURUSER['id'] ? '

        <div class="smart-filter-label">AUTHORSHIP</div>
        <div class="d-flex flex-wrap gap-2 mb-2 smart-filter-buttons">
          <a class="' . $smartBtnClass(['special_search' => 'mytorrents']) . '" href="' . $smartPreset(['special_search' => 'mytorrents']) . '"><i class="fa-solid fa-user me-1"></i>My uploads</a>
          <a class="' . $smartBtnClass(['special_search' => 'mybookmarks']) . '" href="' . $smartPreset(['special_search' => 'mybookmarks']) . '"><i class="fa-solid fa-bookmark me-1"></i>My bookmarks</a>
          <a class="' . $smartBtnClass(['special_search' => 'myreseeds']) . '" href="' . $smartPreset(['special_search' => 'myreseeds']) . '"><i class="fa-solid fa-rotate me-1"></i>Need reseed</a>
          <a class="' . $smartBtnClass(['include_dead_torrents' => 'yes']) . '" href="' . $smartPreset(['include_dead_torrents' => 'yes']) . '"><i class="fa-solid fa-ghost me-1"></i>Include dead</a>
        </div>' : '') . '

        <div class="smart-filter-label">RATING</div>
        <div class="d-flex flex-wrap gap-2 mb-2 smart-filter-buttons">
          <a class="' . $smartBtnClass(['imdb_min' => '7']) . '" href="' . $smartPreset(['imdb_min' => '7']) . '">⭐ IMDb 7+</a>
          <a class="' . $smartBtnClass(['imdb_min' => '8']) . '" href="' . $smartPreset(['imdb_min' => '8']) . '">⭐ IMDb 8+</a>
          <a class="' . $smartBtnClass(['imdb_min' => '9']) . '" href="' . $smartPreset(['imdb_min' => '9']) . '">⭐ IMDb 9+</a>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-2 smart-filter-buttons">
          <a class="btn btn-sm btn-outline-secondary" href="' . $smartPreset(['min_seeders' => null, 'daysprune' => null, 'size_min' => null, 'size_max' => null, 'health' => null, 'freeleech_only' => null, 'no_seeders' => null, 'seeders_gt_leechers' => null, 'hide_downloaded' => null, 'special_search' => null, 'include_dead_torrents' => null, 'imdb_min' => null, 'added_from' => null, 'added_to' => null]) . '"><i class="fa-solid fa-rotate-left me-1"></i>Clear quick filters</a>
        </div>

      </div>
    </section>';
}

$SearchTorrent = '
<div class="container mt-3">
    ' . $smartBrowseHtml . '
    ' . $lang->browse['tsearch'] . '
    <form method="get" action="' . $BASEURL . '/browse.php" name="searchtorrent" id="searchtorrent">
    <input type="hidden" name="do" value="search" />

    <!-- Поиск -->
    <div class="form-group position-relative mb-2">
        <input type="text" class="form-control" id="torrent-search" name="keywords"
               placeholder="Search for a torrent..." autocomplete="off"
               value="' . ($keywords ? htmlspecialchars_uni($keywords) : '') . '">
        <div id="autocomplete-results" class="dropdown-menu"></div>
    </div>

    <!-- Все фильтры в один ряд -->
    <div class="row g-2 mb-2">
        <div class="col-md-2">
            <select class="form-select" id="search_type" name="search_type">
                <option value="t_name"'        . ($search_type === 't_name'        ? ' selected' : '') . '>' . $lang->browse['t_name']        . '</option>
                <option value="t_description"' . ($search_type === 't_description' ? ' selected' : '') . '>' . $lang->browse['t_description'] . '</option>
                <option value="t_tags"'        . ($search_type === 't_tags'        ? ' selected' : '') . '>Tags</option>
                <option value="t_both"'        . ($search_type === 't_both' || $search_type === '' ? ' selected' : '') . '>' . $lang->browse['t_both'] . '</option>
                <option value="t_uploader"'    . ($search_type === 't_uploader'    ? ' selected' : '') . '>' . $lang->browse['t_uploader']    . '</option>
                <option value="t_genre"'       . ($search_type === 't_genre'       ? ' selected' : '') . '>' . $lang->browse['t_genre']       . '</option>
            </select>
        </div>
        <div class="col-md-2">
            ' . $catdropdown . '
        </div>
        <div class="col-md-2">
            <select class="form-select" name="include_dead_torrents">
                <option value="yes"' . ($include_dead_torrents === 'yes' ? ' selected' : '') . '>' . $lang->browse['incdead1'] . '</option>
                <option value="no"'  . ($include_dead_torrents === 'no'  ? ' selected' : '') . '>' . $lang->browse['incdead2'] . '</option>
            </select>
        </div>
        <div class="col-md-2">
            ' . $size_min_select . '
        </div>
        <div class="col-md-2">
            ' . $size_max_select . '
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
        </div>
    </div>

    <!-- Доп. фильтр по минимальному числу сидов -->
    <div class="row g-2 mb-2">
        <div class="col-md-2">
            ' . $seeders_select . '
        </div>
		<div class="col-md-2">
        ' . $daysprune_select . '
        </div>
        <div class="col-md-2">
            <input type="text" id="added_from_input" class="form-control" name="added_from" placeholder="Added from" autocomplete="off" value="' . htmlspecialchars($_GET['added_from'] ?? '', ENT_QUOTES) . '">
        </div>
        <div class="col-md-2">
            <input type="text" id="added_to_input" class="form-control" name="added_to" placeholder="Added until" autocomplete="off" value="' . htmlspecialchars($_GET['added_to'] ?? '', ENT_QUOTES) . '">
        </div>
    </div>

    </form>

    ' . $activeFiltersHtml . '

    <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
        ' . $resetFiltersBtn . '
        <a href="' . $BASEURL . '/getrss.php?' . htmlspecialchars(http_build_query($_GET), ENT_QUOTES) . '" class="btn btn-outline-warning btn-sm">
            <i class="fa-solid fa-rss"></i> Subscribe via RSS
        </a>
    </div>
</div>
';




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

$SearchTorrent = str_replace(
    ['@@CNT-SEEDED@@', '@@CNT-7D@@', '@@CNT-30D@@', '@@CNT-BIG@@', '@@CNT-FL@@'],
    [$smartStats['active'], $smartStats['last_7d'], $smartStats['last_30d'], $smartStats['big_size'], $smartStats['freeleech']],
    $SearchTorrent
);

// Строим только теперь, когда $threadcount уже реальный - раньше (до этой
// строки) он ещё не был вычислен и всегда показал бы 0.


$resultsCountHtml = '<p class="text-muted small mb-2 mt-2">'
    . '<i class="bi bi-funnel me-1"></i> Found <strong>' . $threadcount . '</strong> torrent(s)'
    . ($search_type !== '' && $search_type !== 't_both' ? ' · by <em>' . htmlspecialchars_uni($search_type) . '</em>' : '')
    . ($daysprune > 0 ? ' · <em>' . htmlspecialchars_uni($daysprune_options[$daysprune] ?? ($daysprune . 'd')) . '</em>' : '')
    . '</p>';	
	


if (!$torrentsperpage || $torrentsperpage < 1) {
    $torrentsperpage = 20;
}

$perpage = (int)$torrentsperpage;

if (isset($mybb->input['page']) && (int)$mybb->input['page'] > 0) {
    $page = (int)$mybb->input['page'];
    $start = ($page - 1) * $perpage;
    $pages = ceil($threadcount / $perpage);
    
    if ($page > $pages || $page <= 0) {
        $start = 0;
        $page = 1;
    }
} else {
    $start = 0;
    $page = 1;
}

$end = $start + $perpage;
$lower = $start + 1;
$upper = $end;

if ($upper > $threadcount) {
    $upper = $threadcount;
}

$page_url = $BASEURL . '/browse.php?' . (is_array($Links) && count($Links) > 0 ? implode('&amp;', $Links) : '');
$multipage = multipage($threadcount, $perpage, $page, $page_url);

// Ссылка-заголовок с индикатором направления сортировки (стрелка вверх/вниз),
// сохраняет все активные фильтры из $page_url. Первый клик по колонке -
// сортировка по возрастанию, повторный клик по той же - переключает
// направление.
function render_sort_header(string $label, string $col, string $pageUrl, ?string $currentSort, string $currentDir): string
{
    $isActive = ($currentSort === $col);
    $nextDir  = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow    = $isActive
        ? ($currentDir === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>')
        : '';
    $sep         = str_ends_with($pageUrl, '?') ? '' : '&amp;';
    $href        = $pageUrl . $sep . 'sort=' . $col . '&amp;dir=' . $nextDir;
    $activeClass = $isActive ? ' text-primary fw-bold' : 'text-dark';

    return '<a href="' . $href . '" class="text-decoration-none ' . $activeClass . '">' . $label . $arrow . '</a>';
}





$ListTorrents = '
' . ($is_mod ? '
<form method="post" action="' . $BASEURL . '/admin/index.php?act=manage_torrents" name="manage_torrents" id="manage_torrents">
<input type="hidden" name="do" value="update" />
<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
<input type="hidden" name="return" value="yes" />
<input type="hidden" name="return_address" value="' . $BASEURL . '/browse.php?page=' . (int)($_GET['page'] ?? 0) . '&amp;' . 
    (!empty($Links) ? implode('&amp;', $Links) : '') . '" />
' : '') . '

<div id="listtorrents">

<thead style="display:none">


<tr>
		<td>
		</td>
		
		<td>
		' . render_sort_header('Name', 'name', $page_url, $sortBy, $sortDir) . '
		</td>
		
		<td class="text-center">
		' . render_sort_header('Size', 'size', $page_url, $sortBy, $sortDir) . '
		</td>

		<td class="text-center">
	    ' . render_sort_header('Snatched', 'snatched', $page_url, $sortBy, $sortDir) . '
		</td>
		
		<td class="text-center">
		' . render_sort_header('Seeders', 'seeders', $page_url, $sortBy, $sortDir) . '
		</td>
		
		<td class="text-center">
		' . render_sort_header('Leechers', 'leechers', $page_url, $sortBy, $sortDir) . '
		</td>
		
		<td>
		Uploader
		</td>
	
	
	
    ' . ($is_mod ? '
    <th class="thead text-center">
        <div class="form-check form-switch">
            <input 
                class="form-check-input" 
                type="checkbox" 
                id="checkAllSwitch"
                role="switch" />
        </div>
    </th>' : '') . '
</tr>
</thead>
<tbody>';



		
		
	$sql = 'SELECT t.*, c.name as catname, u.username, u.usergroup 
        FROM torrents t ' . $innerjoin . ' 
        LEFT JOIN categories c ON (t.category=c.id) 
        LEFT JOIN users u ON (t.owner=u.id) '
        . $WHERE . ' ORDER BY ' . $orderby . ' LIMIT ?, ?';	
		
		
		

$data_params = [...$params, (int)$start, (int)$perpage];
$Query = $db->sql_query_prepared($sql, $data_params);


$TotalTorrents = [];

if ($db->num_rows($Query)) {
    while($t = $db->fetch_array($Query)) {
        $TotalTorrents[] = $t;
    }
}

// ── "Уже скачивали" (подсветка строки) ────────────────────────────────────
// Одним пакетным запросом на все торренты текущей страницы, а не по
// запросу на каждую строку в цикле ниже (N+1) - тот же id-список, что
// уже получили выше.
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





if ($TotalTorrents && count($TotalTorrents)) 
{
    
	
    $worked = 0;
    foreach($TotalTorrents as $Torrent) {
        

       
        if (empty($Torrent["tags"])) {
            $keywords2 = '';
        } else {
            $tags = explode(",", $Torrent['tags']);
            $keywords2 = "";
            foreach ($tags as $tag) {
                $keywords2 .= '<a href="' . $BASEURL . '/browse.php?do=search&keywords=' . urlencode($tag) . '&search_type=t_tags" title="' . htmlspecialchars($tag) . '" class="badge bg-primary">' . htmlspecialchars($tag) . '</a> ';
            }
            $keywords2 = substr($keywords2, 0, -1);
        }

        $SEOLink = get_torrent_link($Torrent['id']);
        $SEOLinkC = get_category_link($Torrent['category']);
        
       
        $categoryIcon = $catIconMap[$Torrent['category']] ?? 'fa-solid fa-question';
        
       	

		$torrentPeersLink = get_torrent_link($Torrent['id']) . (str_contains(get_torrent_link($Torrent['id']), '?') ? '&' : '?') . 'tab=peers';

		$d_link = '<a href="' . get_download_link($Torrent['id']) . '" class="badge-popover download-popover" 
           data-bs-toggle="popover" data-bs-placement="top" 
           data-bs-title="📥 Download Torrent" 
           data-bs-content="' . htmlspecialchars('
                <div class="download-popover-content">
                    <div class="torrent-info mb-3">
                        <strong>' . htmlspecialchars($Torrent['name']) . '</strong>
                        <div class="torrent-details mt-2">
                            <div class="detail-item">
                                <i class="bi bi-hdd me-2"></i>
                                <span>' . mksize($Torrent['size']) . '</span>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-file-earmark me-2"></i>
                                <span>' . ts_nf($Torrent['numfiles']) . ' files</span>
                            </div>
                            ' . ($Torrent['seeders'] > 0 ? '
                            <div class="detail-item text-success">
                                <i class="bi bi-arrow-up-circle me-2"></i>
                                <a href="' . $torrentPeersLink . '#seeders" class="text-decoration-none text-success">
                                    <span>' . ts_nf($Torrent['seeders']) . ' seeders</span>
                                </a>
                            </div>' : '') . '
                            ' . ($Torrent['leechers'] > 0 ? '
                            <div class="detail-item text-warning">
                                <i class="bi bi-arrow-down-circle me-2"></i>
                                <a href="' . $torrentPeersLink . '#leechers" class="text-decoration-none text-warning">
                                    <span>' . ts_nf($Torrent['leechers']) . ' leechers</span>
                                </a>
                            </div>' : '') . '
                        </div>
                    </div>
                    <div class="download-actions">
                        <button class="btn btn-success btn-sm w-100" onclick="window.location.href=\'' . get_download_link($Torrent['id']) . '\'">
                            <i class="bi bi-download me-1"></i>Download .torrent
                        </button>
                        <div class="text-center mt-2">
                            <small class="text-muted">Click icon to download immediately</small>
                        </div>
                    </div>
                </div>
           ', ENT_QUOTES) . '" 
           data-bs-html="true" data-bs-trigger="hover focus">
           <i class="fa-sharp fa-solid fa-file-arrow-down fa-lg" style="color: #055df5;"></i>
        </a>';	
			
			
			
	
			
        
        $zax = cutename($Torrent['name']);
        
   
        
        $flags = GetTorrentTags($Torrent);
        $added = my_datee('relative', $Torrent['added']);
        $size = mksize($Torrent['size']);
        $times_completed = ts_nf($Torrent['times_completed']);
        $sedars = ts_nf($Torrent['seeders']);
        $lechars = ts_nf($Torrent['leechers']);
        
        $uploader = (!$is_mod && $Torrent['owner'] != $CURUSER['id'] && $Torrent['anonymous'] === 'yes' ? '
            <div>
                <i class="bi bi-eye-slash fs-5 opacity-50 mb-2 d-block"></i>  
            </div>' : '
            <a href="' . get_profile_link($Torrent['owner']) . '">' . format_name($Torrent['username'], $Torrent['usergroup']) . '</a>
            ' . ($Torrent['anonymous'] === 'yes' ? '
            <div>	
                <i class="bi bi-eye-slash fs-5 opacity-50 mb-2 d-block"></i>
            </div>' : '') . '
        ');
        
        
		

$torrentImage = !empty($Torrent['t_image']) ? htmlspecialchars($Torrent['t_image']) : '';

$moderation = ($is_mod ? '
<td align="center" class="unsortable2">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="torrentid_' . $Torrent['id'] . '" 
            name="torrentid[]" 
            value="' . $Torrent['id'] . '" 
            data-title="' . htmlspecialchars($Torrent['name']) . '"
            data-image="' . $torrentImage . '"
            data-size="' . (int)$Torrent['size'] . '"
            data-seeders="' . (int)$Torrent['seeders'] . '"
            data-leechers="' . (int)$Torrent['leechers'] . '"
            data-category="' . (int)$Torrent['category'] . '"
            data-catname="' . htmlspecialchars($Torrent['catname']) . '"
            role="switch"
        />
    </div>
</td>' : '');
		
		
        
       
	   
	   
	   
	   
	   
	   
$poster_zoom = !empty($Torrent['t_image']) 
    ? 'data-zoom="'.htmlspecialchars_uni($Torrent['t_image']).'"' 
    : '';		
		
		// Постер торрента
$poster_html = '';
if (!empty($Torrent['t_image'])) {
    // URL уже полный — используем как есть
    $poster_html = '<img src="'.htmlspecialchars_uni($Torrent['t_image']).'" 
                        alt="'.htmlspecialchars_uni($Torrent['name']).'"
                        class="torrent-poster"
                        loading="lazy"
                        onerror="this.style.display=\'none\'">';
} else {
    $poster_html = '<div class="torrent-poster-placeholder">
        <i class="'.$categoryIcon.' fa-2x text-muted"></i>
    </div>';
}





$s = (int)$Torrent['seeders'];
$l = (int)$Torrent['leechers'];
$total_peers = $s + $l;




$torrentPeersLinkRow = get_torrent_link($Torrent['id']) . (str_contains(get_torrent_link($Torrent['id']), '?') ? '&' : '?') . 'tab=peers';
$isAlreadySnatched = isset($already_snatched_ids[(int)$Torrent['id']]);

$ListTorrentsss = '
<tr class="torrent-row' . ($isAlreadySnatched ? ' torrent-row-snatched' : '') . '"
    ' . ($isAlreadySnatched ? 'title="You have already downloaded this torrent"' : '') . '
    data-id="' . (int)$Torrent['id'] . '" 
    data-seeders="' . $s . '" 
    data-leechers="' . $l . '">	

<!-- Category Icon + Poster -->
<td class="torrent-poster-cell">
    <a href="'.$SEOLinkC.'" class="category-icon-link" data-tooltip="'.$Torrent['catname'].'">
        <i class="'.$categoryIcon.' category-icon-small"></i>
    </a>
    <a href="'.$SEOLink.'" class="poster-link" '.$poster_zoom.'>
        '.$poster_html.'
    </a>
</td>

<!-- Torrent Information -->
<td class="torrent-info-cell">
    <div class="torrent-title">
        <a href="'.$SEOLink.'" 
           class="torrent-name-link"
           data-tooltip="'.$zax.'">
           '.(!empty($keywords) ? 
               highlight(htmlspecialchars_uni($keywords), cutename($Torrent['name'])) : 
               cutename($Torrent['name'])).'
        </a>
        <div class="torrent-actions-inline">
            '.$d_link.'
            <span id="bookmark'.$count.'" data-tooltip="Bookmark">
                '.get_torrent_bookmark_state($CURUSER['id'], (int)$Torrent['id']).'
            </span>
        </div>
    </div>

    <div class="torrent-meta">
        <span class="torrent-date">
            <i class="bi bi-calendar3 me-1"></i>'.$added.'
        </span>
        '.($flags ? '<span class="torrent-flags">'.$flags.'</span>' : '').'
    </div>
    
    '.($keywords2 ? '<div>'.$keywords2.'</div>' : '').'
</td>

<!-- Size -->
<td class="torrent-stat-cell text-center">
    <i class="bi bi-hdd-stack me-1 text-muted"></i>
    <span class="fw-bold">'.$size.'</span>
</td>

<!-- Snatched -->
<td class="torrent-stat-cell text-center">
    <a href="'.$BASEURL.'/viewsnatches.php?id='.$Torrent['id'].'" 
       class="text-decoration-none text-muted"
       data-tooltip="Total snatched count">
        <i class="bi bi-cloud-download me-1"></i>
        <span class="fw-semibold">'.$times_completed.'</span>
    </a>
</td>

<!-- Seeders -->
<td class="torrent-stat-cell text-center">
    <span id="seeders_'.$Torrent['id'].'">
        <a href="'.$torrentPeersLinkRow.'#seeders" 
           class="text-decoration-none text-success fw-bold"
           data-tooltip="Current seeders">
            <i class="bi bi-arrow-up-circle-fill me-1"></i>
            <span class="fw-bold">'.$sedars.'</span>
        </a>
    </span>
</td>

<!-- Leechers -->
<td class="torrent-stat-cell text-center">
    <span id="leechers_'.$Torrent['id'].'">
        <a href="'.$torrentPeersLinkRow.'#leechers" 
           class="text-decoration-none text-danger"
           data-tooltip="Current leechers">
            <i class="bi bi-arrow-down-circle-fill me-1"></i>
            <span class="fw-bold">'.$lechars.'</span>
        </a>
    </span>
</td>

<!-- Uploader -->
<td class="torrent-uploader-cell">
    <i class="bi bi-person-badge me-1 text-muted"></i>
    '.$uploader.'
</td>

<!-- Moderation Checkbox -->
'.$moderation.'

</tr>';



	   
	   
	   
	   
	   
	   
	   
	   
	   
		
		
		
		$ListTorrents .= $ListTorrentsss;
        $count++;
    }
} else {
    $ListTorrents .= '
    <tr>
        <td colspan="' . ($is_mod ? '10' : '9') . '">
            <div class="card-body p-4">
                <div class="empty-state text-center py-5">
                    <i class="fa-regular fa-folder-open fa-4x text-muted mb-4"></i>
                    <h4>No torrents found</h4>
                    <p class="text-muted">Try changing filters or <a href="' . $BASEURL . '/browse.php">reset them</a>.</p>
                    <a href="' . $BASEURL . '/browse.php" class="btn btn-primary mt-3">
                        <i class="fa-solid fa-rotate-left me-1"></i> Reset filters
                    </a>
                </div>
            </div>
        </td>
    </tr>';
}




$bedit = '
<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/moderation-modal.css">

<!-- Moderation button with stats -->
<div class="container mt-3">


<div class="d-flex justify-content-between align-items-center">
    <div class="moderation-stats">
        <span class="badge bg-primary rounded-pill px-3 py-2" id="totalSelectedBadge">
            <i class="fas fa-check-circle me-1"></i>
            <span id="selectedCountDisplay" aria-live="polite">0</span> selected
        </span>
        <span class="badge bg-secondary rounded-pill px-3 py-2 ms-2">
            <i class="fas fa-list me-1"></i>
            <span id="totalTorrentsCount">' . $threadcount . '</span> total
        </span>
    </div>

    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary" onclick="ModerationModal.selectAllFiltered(this)">
            <i class="fas fa-check-double me-1"></i>Select All Matching Filter
        </button>
        <button type="button" class="btn btn-mod-trigger" data-bs-toggle="modal" data-bs-target="#moderationModal">
            <i class="fas fa-shield-alt me-2"></i>Moderation Actions
        </button>
    </div>
</div>


</div>

<!-- Moderation Actions Modal -->
<div class="modal fade" id="moderationModal" tabindex="-1" aria-labelledby="moderationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
    <div class="modal-content mod-modal-content shadow-lg">

      <div class="modal-header mod-modal-header">
        <h5 class="modal-title" id="moderationModalLabel">
            <i class="fas fa-shield-alt me-2"></i>Moderation Actions
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body mod-modal-body">

        <div class="row">
            <!-- Левая колонка: превью постера -->
            <div class="col-md-3 mod-poster-column">
                <div class="mod-poster-container" id="modPosterContainer">
                    <div class="mod-poster-placeholder" id="modPosterPlaceholder">
                        <i class="fas fa-images fa-3x text-muted"></i>
                        <p class="text-muted mt-2 small">Select torrents to preview</p>
                    </div>
                    <div class="mod-poster-wrapper" id="modPosterWrapper" style="display:none;">
                        <img id="modPosterPreview" src="" alt="Torrent poster" class="mod-poster-img">
                        <div class="mod-poster-count" id="modPosterCount"></div>
                    </div>
                </div>
            </div>

            <!-- Правая колонка: действия -->
            <div class="col-md-9">
                <!-- Selection info -->
                <div class="mod-selection-info alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong id="modalSelectedCount" aria-live="polite">0</strong> torrent(s) selected
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>

                <!-- Progress bar -->
                <div class="mod-progress-container mb-2" id="modProgressContainer" style="display:none;">
                    <div class="d-flex justify-content-between small">
                        <span>Selected: <strong id="selectedCount2">0</strong> / <span id="totalCount2">0</span></span>
                        <span id="progressPercentage">0%</span>
                    </div>
                    <div class="progress" style="height:6px;border-radius:4px;">
                        <div id="selectionProgress" class="progress-bar" style="width:0%;"></div>
                    </div>
                </div>
				
				
			<div class="mod-restore-info" id="modRestoreInfo" style="display:none;">
    <i class="fas fa-history me-1"></i>
    <span id="modRestoreMessage">Selection restored from previous session</span>
    <button type="button" class="btn-close btn-close-sm float-end" id="modRestoreCloseBtn" aria-label="Close"></button>
</div>
				
				
				
				

                <!-- Сводная статистика по выбранным торрентам - ПЕРЕМЕЩЕНА СЮДА -->
                <div class="mod-summary-stats" id="modSummaryStats" style="display:none;">
                    <div class="mod-summary-stat">
                        <i class="fas fa-hdd"></i>
                        <span id="modSummarySize">0 B</span>
                    </div>
                    <div class="mod-summary-stat">
                        <i class="fas fa-arrow-up text-success"></i>
                        <span id="modSummarySeeders">0</span>
                    </div>
                    <div class="mod-summary-stat">
                        <i class="fas fa-arrow-down text-danger"></i>
                        <span id="modSummaryLeechers">0</span>
                    </div>
                    <div class="mod-summary-stat">
                        <i class="fas fa-folder"></i>
                        <span id="modSummaryCategories">0</span>
                    </div>
                </div>

                <!-- Mini-list of selected torrents -->
                <div id="selectedTorrentsList" class="mod-selected-list" style="display:none;"></div>

                <label class="mod-label" for="actiontype">
                    <i class="fas fa-bolt me-1"></i>Choose an action
                </label>
                <select class="form-select mod-select" name="actiontype" id="actiontype">
                    <option value="0">⚙️ Select Action</option>
                    <optgroup label="── Torrent ──">
                        <option value="move">📁 Move selected</option>
                        <option value="delete">🗑️ Delete selected</option>
                        <option value="sticky">📌 Sticky / Unsticky</option>
                        <option value="visible">👁️ Visible / Hidden</option>
                        <option value="banned">🚫 Ban / Unban</option>
                        <option value="nuke">☢️ Nuke / Unnuke</option>
                        <option value="openclose">💬 Open / Close comments</option>
                    </optgroup>
                    <optgroup label="── Promo ──">
                        <option value="free">🎁 Free / Non-Free</option>
                        <option value="silver">🥈 Silver / Non-Silver</option>
                        <option value="doubleupload">⏫ Double Upload ON/OFF</option>
                        <option value="thirtypercent">🟣 30% Leech ON/OFF</option>
                    </optgroup>
                    <optgroup label="── Other ──">
                        <option value="anonymous">🎭 Anonymize / Deanon</option>
                        <option value="request">📩 Request / Non-Request</option>
                        <option value="resetrating">⭐ Reset Rating</option>
                    </optgroup>
                </select>

                <!-- Move category (hidden until "Move selected" is chosen) -->
                <div id="movetorrent" class="mod-move-block" style="display:none;">
                    <div class="mod-move-container">
                        <span class="mod-move-label">
                            <i class="fas fa-folder-open me-1"></i>Move to category
                        </span>
                        ' . $catdropdown . '
                    </div>
                    <div id="modMoveCategoryWarning" class="mod-move-mismatch-warning" style="display:none;">
                        <i class="fas fa-triangle-exclamation me-1"></i>
                        <span id="modMoveCategoryWarningText">Selected torrents come from different categories.</span>
                    </div>
                </div>

                <!-- Action info -->
                <div id="actionInfo" class="mod-action-info" style="display:none;">
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="actionDescription">Select an action</span>
                    </div>
                </div>

                <div class="mod-hint">
                    <i class="fas fa-circle-info me-1"></i>
                    <span>Select torrents in the table below, choose an action, then apply.</span>
                </div>
            </div>
        </div>

      </div>

      
	  <div class="modal-footer mod-modal-footer">
    <button type="button" class="btn btn-outline-danger btn-sm me-auto" id="clearAllSelectionsBtn">
        <i class="fas fa-trash-alt me-1"></i>Clear All
    </button>
    <button type="button" class="btn btn-mod-cancel" data-bs-dismiss="modal">
        <i class="fas fa-times me-1"></i>Cancel
    </button>
    <button type="button" class="btn btn-mod-apply" id="applyActionBtn">
        <i class="fas fa-play me-1"></i> Apply
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
            <i class="fas fa-exclamation-triangle me-2"></i>Please confirm
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body mod-modal-body">
        <p id="modConfirmMessage" class="mb-0"></p>
        <div id="modConfirmTorrentList" class="mod-selected-list mt-3" style="display:none;"></div>
      </div>
      <div class="modal-footer mod-modal-footer">
        <button type="button" class="btn btn-mod-cancel" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>Cancel
        </button>
        <button type="button" class="btn btn-mod-danger" id="modConfirmAcceptBtn">
            <i class="fas fa-check me-1"></i>Confirm
        </button>
      </div>
    </div>
  </div>
</div>

</form>

<!-- Initialize modal -->
<script>
    window.browseModTotalTorrents = ' . $threadcount . ';
</script>';










$ListTorrents .= '
    </tbody>
</table>
' . ($is_mod ? $bedit : '') . '
</div>';

stdhead($lang->browse['btitle']);






$showimages = 'yes';
$i_torrent_limit = '15';


$carouselItems = [];
$carouselQuery = $db->sql_query_prepared(
    "SELECT id, name, t_image FROM torrents
     WHERE t_image != '' AND visible = 'yes' AND banned = 'no'
     ORDER BY added DESC
     LIMIT ?",
    [(int)$i_torrent_limit]
);
while ($carouselQuery && ($row2 = $db->fetch_array($carouselQuery))) {
    $carouselItems[] = $row2;
}
$total = count($carouselItems);

if ($showimages === 'yes' && $total > 0): ?>
<div class="container mt-3">
    <div id="cachedTorrentCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
        <!-- Carousel Indicators (dots) -->
        <div class="carousel-indicators">
            <?php for ($i = 0; $i < $total; $i++): ?>
                <button type="button" data-bs-target="#cachedTorrentCarousel" data-bs-slide-to="<?= $i ?>" 
                    class="<?= $i === 0 ? 'active' : '' ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>" 
                    aria-label="Slide <?= $i + 1 ?>"></button>
            <?php endfor; ?>
        </div>

        <!-- Carousel Inner -->
        <div class="carousel-inner">
            <?php foreach ($carouselItems as $index => $row2): 
                $seolink = get_torrent_link($row2['id']);
                $title = htmlspecialchars($row2['name']);
                $image = htmlspecialchars($row2['t_image']);
            ?>
            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                <a href="<?= $seolink ?>">
                    <img src="<?= $image ?>" class="d-block w-100" alt="<?= $title ?>" 
                         style="max-height: 300px; object-fit: cover;">
                </a>
                <div class="carousel-caption d-none d-md-block">
                    <h5><?= $title ?></h5>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Controls -->
        <button class="carousel-control-prev" type="button" data-bs-target="#cachedTorrentCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#cachedTorrentCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>

    <!-- Optional Thumbnails Below Carousel -->
    <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
        <?php foreach ($carouselItems as $index => $row2): ?>
            <img src="<?= htmlspecialchars($row2['t_image']) ?>" alt="Thumb <?= $index + 1 ?>" 
                 style="width: 80px; height: 50px; object-fit: cover; cursor: pointer;" 
                 onclick="bootstrap.Carousel.getInstance(document.querySelector('#cachedTorrentCarousel')).to(<?= $index ?>);">
        <?php endforeach; ?>
    </div>
</div>
<?php endif;


echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/toast.js"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/bookmark.js"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/popover.js"></script>';
echo '<link rel="stylesheet" href="'.$BASEURL.'/admin/templates/airbnb.css">';
echo '<script src="'.$BASEURL.'/admin/scripts/flatpickr.js"></script>';
echo '<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof flatpickr === "undefined") return;
    flatpickr("#added_from_input", { dateFormat: "Y-m-d", allowInput: true });
    flatpickr("#added_to_input", { dateFormat: "Y-m-d", allowInput: true });
});
</script>';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/autocomplete.css">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/browse.css">';
echo '<style>
/* Smart Browse visual refresh: scoped so Classic view keeps the forum theme. */
/* Smart Browse — Light Theme */

.smart-browse-panel {
    background: #ffffff;
    color: #18233b;
    border: 1px solid #e3eaf5 !important;
    box-shadow: 0 10px 28px rgba(30, 55, 90, .08) !important;
}

.smart-browse-panel .text-muted,
.smart-browse-panel small {
    color: #71819c !important;
}

.smart-browse-heading h4 {
    color: #18233b;
    font-weight: 650;
    letter-spacing: -.025em;
}

.smart-browse-heading .smart-eyebrow {
    color: #6657e8;
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: .18em;
    margin-bottom: .3rem;
}

.smart-browse-panel .btn {
    border-radius: 10px;
    transition: transform .16s ease,
                background-color .16s ease,
                border-color .16s ease;
}

.smart-browse-panel .btn:hover {
    transform: translateY(-1px);
}

.smart-browse-panel .btn-primary {
    background: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
}

.smart-browse-panel .btn-outline-primary {
    color: #355b99;
    border-color: #d9e4f3;
    background: #f7f9fd;
}

.smart-browse-panel .btn-outline-primary:hover {
    color: #fff;
    background: #0d6efd;
    border-color: #0d6efd;
}

.smart-browse-panel .btn-outline-secondary {
    color: #526581;
    border-color: #d9e4f3;
    background: #fff;
}

.smart-view-switch {
    padding: 5px;
    width: max-content;
    max-width: 100%;
    border: 1px solid #e1e8f3;
    border-radius: 12px;
    background: #f5f8fd;
}

.smart-view-switch .btn {
    min-width: 100px;
}

.smart-filter-label {
    color: #7585a0;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .13em;
    margin: 0 0 .65rem;
}

.smart-filter-buttons {
    gap: .6rem !important;
}

.smart-preset-count {
    opacity: .65;
    font-size: .85em;
}

/* ── Flatpickr overrides ───────────────────────────────────────────────────── */
.flatpickr-calendar {
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(0,0,0,.08);
}
.flatpickr-day.today {
    border-color: var(--bs-primary);
}
.flatpickr-day.selected,
.flatpickr-day.startRange,
.flatpickr-day.endRange {
    background: var(--bs-primary);
    border-color: var(--bs-primary);
}

/* Search field */

body:has(.smart-browse-panel) #torrent-search {
    min-height: 54px;
    border-radius: 13px;
    padding: .85rem 1rem;
    background: #fff;
    border: 1px solid #dce5f2;
    color: #1d2b45;
    box-shadow: 0 2px 8px rgba(35, 60, 100, .03);
}

body:has(.smart-browse-panel) #torrent-search::placeholder {
    color: #8797b1;
}

body:has(.smart-browse-panel) #torrent-search:focus {
    border-color: #8175f3;
    box-shadow: 0 0 0 .2rem rgba(100, 87, 255, .13);
}

/* Torrent cards */

body:has(.smart-browse-panel) #smartTorrentCards .smart-torrent-card {
    color: #1c2a43;
    background: #fff;
    border: 1px solid #e2eaf5;
    border-radius: 15px;
    transition: transform .18s ease,
                border-color .18s ease,
                box-shadow .18s ease;
}

body:has(.smart-browse-panel) #smartTorrentCards .smart-torrent-card:hover {
    transform: translateY(-3px);
    border-color: #aaa2ff;
    box-shadow: 0 12px 28px rgba(50, 70, 120, .12) !important;
}

body:has(.smart-browse-panel) #smartTorrentCards .card-title a {
    color: #20385f;
}

body:has(.smart-browse-panel) #smartTorrentCards .small {
    color: #74839d !important;
}

body:has(.smart-browse-panel) #smartTorrentCards .smart-card-poster {
    background: #f2f5fa;
    border-radius: 10px;
    overflow: hidden;
}

body:has(.smart-browse-panel) #smartTorrentCards .badge {
    font-weight: 650;
}

body:has(.smart-browse-panel) #smartTorrentCards .card-body {
    padding: 1rem;
}
.torrent-row-snatched {
    background-color: rgba(13, 110, 253, 0.08) !important;
    border-left: 3px solid #0d6efd !important;
}
.torrent-row-snatched:hover {
    background-color: rgba(13, 110, 253, 0.14) !important;
}
.torrent-row-snatched td {
    background-color: transparent !important;
}
</style>';











$actionns = $is_mod ? $lang->browse['acction'] : '';

$table = '

<!-- Enhanced Poster Zoom Overlay (один раз на страницу) -->
<div class="poster-zoom-overlay" id="posterZoomOverlay">
    <img src="" alt="Poster preview" class="poster-zoom-img" id="posterZoomImg">
</div>

<div class="container mt-3">
  <div id="smartTorrentCards" class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3" hidden aria-live="polite"></div>
  <div id="smartTorrentTableWrap">
  <table class="table table-hover">
    '.$multipage.'
    <thead>
      <tr>
        <th>
            <i class="bi bi-tag-fill me-1"></i>
            '.$lang->browse['type'].'
        </th>
        <th>
            <i class="bi bi-file-earmark-text me-1"></i>
            '.render_sort_header($lang->browse['t_name'], 'name', $page_url, $sortBy, $sortDir).'
        </th>
        <th>
            <i class="bi bi-hdd me-1"></i>
            '.render_sort_header($lang->browse['sortby6'], 'size', $page_url, $sortBy, $sortDir).'
        </th>
        <th>
            <i class="bi bi-download me-1"></i>
            '.render_sort_header($lang->browse['sortby7'], 'snatched', $page_url, $sortBy, $sortDir).'
        </th>
        <th>
            <i class="bi bi-arrow-up-circle me-1"></i>
            '.render_sort_header($lang->browse['sortby4'], 'seeders', $page_url, $sortBy, $sortDir).'
        </th>
        <th>
            <i class="bi bi-arrow-down-circle me-1"></i>
            '.render_sort_header($lang->browse['sortby5'], 'leechers', $page_url, $sortBy, $sortDir).'
        </th>
        <th>
            <i class="bi bi-person-circle me-1"></i>
            '.$lang->browse['sortby8'].'
        </th>
        <th>
            <i class="bi bi-gear me-1"></i>
            '.$actionns.'
            '.($is_mod ? '
            <div class="form-check form-switch d-inline-block ms-2 align-middle" title="Select all on this page">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="checkAllSwitchVisible"
                    role="switch" />
            </div>' : '').'
        </th>
      </tr>
    </thead>
    <tbody>
      '.$ListTorrents.'
    </tbody>
  </table>
  </div>
</div>


<div class="container mt-3">
    '.$multipage.'
</div>

';






echo '
' . $___notice . '
' . $categories . '
' . $SearchTorrent . '
' . $table . '
';

echo '<script>
(function () {
  const tableWrap = document.getElementById("smartTorrentTableWrap");
  const cards = document.getElementById("smartTorrentCards");
  const tableBtn = document.getElementById("smartViewTable");
  const cardsBtn = document.getElementById("smartViewCards");
  if (!tableWrap || !cards || !tableBtn || !cardsBtn) return;

  const rows = tableWrap.querySelectorAll("tbody tr.torrent-row");
  rows.forEach((row) => {
    const cells = row.querySelectorAll(":scope > td");
    if (cells.length < 7) return;
    const col = document.createElement("div");
    col.className = "col";
    const card = document.createElement("article");
    card.className = "card h-100 shadow-sm smart-torrent-card";
    if (row.classList.contains("torrent-row-snatched")) card.classList.add("border-primary");

    const poster = cells[0].querySelector(".poster-link");
    const title = cells[1].querySelector(".torrent-name-link");
    const body = document.createElement("div");
    body.className = "card-body d-flex flex-column";
    if (poster) {
      const posterClone = poster.cloneNode(true);
      posterClone.classList.add("smart-card-poster", "mb-2", "d-block", "text-center");
      body.appendChild(posterClone);
    }
    const healthBadge = cells[1].querySelector(".torrent-title .badge");
    if (healthBadge) {
      const badgeClone = healthBadge.cloneNode(true);
      badgeClone.classList.add("mb-2", "align-self-start");
      body.appendChild(badgeClone);
    }
    if (title) {
      const heading = document.createElement("h6");
      heading.className = "card-title";
      const titleClone = title.cloneNode(true);
      titleClone.removeAttribute("data-tooltip");
      heading.appendChild(titleClone);
      body.appendChild(heading);
    }
    const meta = document.createElement("div");
    meta.className = "small text-muted mb-2";
    meta.textContent = cells[1].querySelector(".torrent-meta")?.innerText.trim() || "";
    body.appendChild(meta);
    const stats = document.createElement("div");
    stats.className = "d-flex flex-wrap gap-3 small mt-auto";
    [2,3,4,5].forEach((i, index) => {
      const item = document.createElement("span");
      item.innerHTML = cells[i].innerHTML;
      item.setAttribute("aria-label", ["Size", "Downloads", "Seeders", "Leechers"][index]);
      stats.appendChild(item);
    });
    body.appendChild(stats);
    if (row.classList.contains("torrent-row-snatched")) {
      const badge = document.createElement("span");
      badge.className = "badge bg-primary align-self-start mt-2";
      badge.textContent = "Already downloaded";
      body.appendChild(badge);
    }
    card.appendChild(body);
    col.appendChild(card);
    cards.appendChild(col);
  });

  function setView(view) {
    const showCards = view === "cards";
    tableWrap.hidden = showCards;
    cards.hidden = !showCards;
    tableBtn.classList.toggle("btn-primary", !showCards);
    tableBtn.classList.toggle("btn-outline-primary", showCards);
    cardsBtn.classList.toggle("btn-primary", showCards);
    cardsBtn.classList.toggle("btn-outline-primary", !showCards);
    tableBtn.setAttribute("aria-pressed", String(!showCards));
    cardsBtn.setAttribute("aria-pressed", String(showCards));
    try { localStorage.setItem("browseSmartView", view); } catch (e) {}
  }
  tableBtn.addEventListener("click", () => setView("table"));
  cardsBtn.addEventListener("click", () => setView("cards"));
  let preferred = "table";
  try { preferred = localStorage.getItem("browseSmartView") || "table"; } catch (e) {}
  setView(preferred === "cards" ? "cards" : "table");
})();
</script>
<style>
.smart-torrent-card { border-radius: 12px; overflow: hidden; }
.smart-stats-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin:0 0 1.4rem; }
.smart-stat { display:flex; align-items:center; gap:12px; padding:15px 17px; border:1px solid #3b404c; border-radius:13px; background:linear-gradient(135deg,#292d36,#22252c); min-width:0; }
.smart-stat-icon { display:grid; place-items:center; width:38px; height:38px; flex:0 0 38px; border-radius:11px; background:rgba(102,87,255,.18); color:#a59cff; font-size:1.4rem; font-weight:700; }
.smart-stat strong { display:block; color:#f7f7fb; font-size:1.35rem; line-height:1.15; }
.smart-stat small { display:block; color:#aeb4c0; font-size:.76rem; margin-top:3px; }
.smart-stat-warning .smart-stat-icon { background:rgba(239,68,68,.16); color:#ff8585; }
.smart-badge-new { background:#5144db; color:#fff; letter-spacing:.04em; }
.smart-badge-popular { background:#a84b14; color:#fff; letter-spacing:.04em; }
@media (max-width: 768px) { .smart-stats-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; } .smart-stat { padding:11px; } }
@media (max-width: 420px) { .smart-stats-grid { grid-template-columns:1fr 1fr; } .smart-stat-icon { width:30px; height:30px; flex-basis:30px; } }
.smart-card-poster img, .smart-card-poster .torrent-poster { max-width: 100%; max-height: 210px; object-fit: contain; }
#smartTorrentCards[hidden], #smartTorrentTableWrap[hidden] { display: none !important; }
</style>';

echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/browse.js"></script>';

if ($is_mod) {
    echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/browse-moderation.js"></script>';
	echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/browse-moderation.css">';
}




$__toast_ok  = isset($_GET['mod_success']) ? htmlspecialchars($_GET['mod_success'], ENT_QUOTES) : null;
$__toast_err = isset($_GET['mod_error']) ? htmlspecialchars($_GET['mod_error'], ENT_QUOTES) : null;

if ($__toast_ok || $__toast_err):
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    <?php if ($__toast_ok): ?>
    showToast(<?= json_encode($__toast_ok, JSON_UNESCAPED_UNICODE) ?>, 'success');
    <?php endif; ?>
    <?php if ($__toast_err): ?>
    showToast(<?= json_encode($__toast_err, JSON_UNESCAPED_UNICODE) ?>, 'error');
    <?php endif; ?>

    // Strip mod_success/mod_error from the URL so a refresh doesn't repeat the toast
    if (window.history && window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.delete('mod_success');
        url.searchParams.delete('mod_error');
        window.history.replaceState({}, document.title, url.toString());
    }
});
</script>
<?php endif; ?>

<?php
// ── Запоминание последнего фильтра (localStorage) ──────────────────────────
// Пресеты и фильтры живут только в URL - если уйти со страницы обычной
// ссылкой (не кнопкой "назад" браузера, там URL и так сохраняется), фильтр
// терялся. При заходе с активными фильтрами - сохраняем query string. При
// заходе на пустой browse.php - предлагаем восстановить, если есть что.
$hasActiveFilters = !empty($activeFilters);
?>
<script>
(function () {
    const KEY = 'browse_last_filters';
    const hasFilters = <?= $hasActiveFilters ? 'true' : 'false' ?>;
    const currentQuery = window.location.search;

    if (hasFilters) {
        try { localStorage.setItem(KEY, currentQuery); } catch (e) {}
        return;
    }

    // Пустой browse.php - есть ли что предложить восстановить?
    let saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) {}
    if (!saved || saved === '' || saved === '?') return;

    document.addEventListener('DOMContentLoaded', () => {
        const bar = document.createElement('div');
        bar.className = 'container mt-3';
        bar.innerHTML = '<div class="alert alert-secondary d-flex justify-content-between align-items-center flex-wrap gap-2 mb-0">' +
            '<span><i class="fa-solid fa-clock-rotate-left me-1"></i>You had filters applied last time you browsed.</span>' +
            '<span>' +
                '<a href="' + window.location.pathname + saved + '" class="btn btn-sm btn-primary me-2">Restore filters</a>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary" id="dismissRestoreFilters">Dismiss</button>' +
            '</span>' +
        '</div>';

        const anchor = document.querySelector('nav[aria-label="breadcrumb"]');
        if (anchor && anchor.parentElement) {
            anchor.parentElement.insertAdjacentElement('afterend', bar);
        } else {
            document.body.insertBefore(bar, document.body.firstChild);
        }

        const dismissBtn = document.getElementById('dismissRestoreFilters');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', () => {
                try { localStorage.removeItem(KEY); } catch (e) {}
                bar.remove();
            });
        }
    });
})();
</script>

<?php

stdfoot();