<?php

declare(strict_types=1);

define('THIS_SCRIPT', 'browse.php');
define('B_VERSION', '6.6.3');
define("SCRIPTNAME", "browse.php");

$templatelist = "browses,browse_table,browse_edit,browse_categories,browse_categories2,multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";

require './global.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_bookmark.php';



if (!isset($CURUSER)) {
    print_no_permission();
}

$lang->load('browse');

$is_mod = is_mod($usergroups);


$category = (int)($_POST['category'] ?? $_GET['category'] ?? 0);
$keywords = $_POST['keywords'] ?? $_GET['keywords'] ?? '';
$search_type = trim($_POST['search_type'] ?? $_GET['search_type'] ?? '');

$special_search = TS_Global('special_search');

$sort = TS_Global('sort');
$order = TS_Global('order');
$daysprune = TS_Global('daysprune');
$include_dead_torrents = TS_Global('include_dead_torrents');
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
            echo '<link href="' . $BASEURL . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">';
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
            echo '<link href="' . $BASEURL . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">';
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
            echo '<link href="' . $BASEURL . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">';
            echo '<link href="' . $BASEURL . '/include/templates/default/style/messagess.css" rel="stylesheet">';
            break;
    }
} elseif ($bdayreward === 'yes' && $bdayrewardtype) {
    $curuserbday = explode('-', $CURUSER['birthday']);
    if (date('j-n') === $curuserbday[0] . '-' . $curuserbday[1]) {
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
        $scdesc = htmlspecialchars_uni($sc['cat_desc']);
        $SEOLinkC = ts_seo($sc['id'], $sc['name'], 'c');
        $subcategories[$sc['pid']][] = '
        <span id="category' . $sc['id'] . '"' . (
            (isset($category) && $category === $sc['id']) || 
            (!$category && str_contains(($CURUSER['notifs'] ?? ''), '[cat' . $sc['id'] . ']') && $usergroups['canemailnotify'] === 'yes') ? 
            ' class="highlight"' : ''
        ) . '>
            <a href="' . $SEOLinkC . '" title="' . $scdesc . '">' . $sc['name'] . '</a>
        </span>';
    }
    unset($_categoriesS);
}

$count = 0;
eval("\$categories = \"" . $templates->get("browse_categories") . "\";");

if (($rows = count($_categoriesC)) > 0) {
    foreach ($_categoriesC as $c) {
        $tracker_cats_per_row = '5';
        $table_cat_width = '';
        $table_cat_height = '';

        $searcincategories[] = $c['id'];
        if ($count && $count % $tracker_cats_per_row === 0) {
            $categories .= '</tr><tr class="none">';
        }

        $tracker_cats_width = '';
        $cname = htmlspecialchars_uni($c['name']);
        $cdesc = htmlspecialchars_uni($c['cat_desc']);
        $SEOLinkC = ts_seo($c['id'], $cname, 'c');

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
                            str_contains(($CURUSER['notifs'] ?? ''), '[cat' . $c['id'] . ']') && 
                            $usergroups['canemailnotify'] === 'yes'
                        ) ? 
                        ' class="fw-bold text-primary"' : ''
                    ) . '>
                        <a href="' . $SEOLinkC . '" title="' . $cdesc . '" class="text-decoration-none category-link" data-cat-id="' . $c['id'] . '">
                             <h6 class="mb-1">' . $cname . '</h6>
                        </a>
                    </span>
                    <div class="small text-muted">
                        ' . (isset($subcategories[$c['id']]) ? implode(', ', $subcategories[$c['id']]) : $c['cat_desc']) . '
                    </div>
                </div>
            </div>
        </td>';

        $count++;
    }
    unset($_categoriesC);
}

eval("\$categories .= \"" . $templates->get("browse_categories2") . "\";");







require_once INC_PATH . '/functions_category.php';
$catdropdown = ts_category_list('category', ($category ?? ''), '<option value="0" style="color: gray;">' . $lang->browse['alltypes'] . '</option>', 'categories');












$size_min = $_GET['size_min'] ?? '';
$size_max = $_GET['size_max'] ?? '';

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





$SearchTorrent = '
<div class="container mt-3">
    ' . $lang->browse['tsearch'] . '
    <form method="get" action="' . $_SERVER['SCRIPT_NAME'] . '" name="searchtorrent" id="searchtorrent">
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

    </form>
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
    $WHERE .= " AND t.visible = 'no' OR (t.leechers > 0 AND t.seeders = 0) OR (t.leechers = 0 AND t.seeders = 0)";
}

$extraquery = [];
$extra_params = [];

if ($keywords && $search_type) {
    $OrjKeywords = $keywords;
    $Links[] = 'keywords=' . htmlspecialchars_uni($keywords);
    $Links[] = 'search_type=' . htmlspecialchars_uni($search_type);
    
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
            $squerycats[] = $squery['id'];
        }
        $extraquery[] = 't.category IN (' . $category . ', ' . implode(', ', $squerycats) . ')';
    } else {
        $extraquery[] = "t.category = ?";
        $extra_params[] = $category;
    }
    $Links[] = 'category=' . $category;
}




if ($special_search) {
    $Links[] = 'special_search=' . htmlspecialchars_uni($special_search);
}



// Фильтр по размеру — ДО применения extraquery
$size_min_val = ($size_min !== '') ? (int)$size_min : null;
$size_max_val = ($size_max !== '') ? (int)$size_max : null;

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


if (count($extraquery) > 0) {
    $WHERE .= ' AND ' . implode(' AND ', $extraquery);
    $params = array_merge($params, $extra_params);
    $Links[] = 'do=search';
    $Links[] = 'keywords=' . urlencode(htmlspecialchars_uni($keywords));
    $Links[] = 'search_type=' . urlencode(htmlspecialchars_uni($search_type));
}





$orderby = 't.sticky, t.added DESC';



$torrentsperpage = ($CURUSER['torrentsperpage'] <> 0 ? (int)$CURUSER['torrentsperpage'] : $ts_perpage);
$threadcount = 0;

$count_sql = 'SELECT t.id, c.name, u.usergroup, g.gid 
              FROM torrents t' . $innerjoin . ' 
              LEFT JOIN users u ON (t.owner=u.id) 
              LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
              LEFT JOIN categories c ON (t.category=c.id)' . $WHERE . ' 
              ORDER BY ' . $orderby;

$countquery = $db->sql_query_prepared($count_sql, $params);
$threadcount = (int)$db->num_rows($countquery);


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

$page_url = $_SERVER['SCRIPT_NAME'] . '?' . (is_array($Links) && count($Links) > 0 ? implode('&amp;', $Links) : '');
$multipage = multipage($threadcount, $perpage, $page, $page_url);





$ListTorrents = '
<script type="text/javascript" src="' . $BASEURL . '/scripts/ts_update.js"></script>
' . ($is_mod ? '
<script type="text/javascript">
    
    function toggleAllCheckboxes(source) {
        const checkboxes = document.querySelectorAll(\'input[name="torrentid[]"]\');
        checkboxes.forEach(checkbox => {
            checkbox.checked = source.checked;
        });
    }
    
   
    function updateMasterCheckbox() {
        const checkboxes = document.querySelectorAll(\'input[name="torrentid[]"]\');
        const masterCheckbox = document.getElementById("checkAllSwitch");
        
        if (checkboxes.length === 0) return;
        
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
        
        if (masterCheckbox) {
            masterCheckbox.checked = allChecked;
            masterCheckbox.indeterminate = !allChecked && anyChecked;
        }
    }
    
   
    document.addEventListener("DOMContentLoaded", function() {
        // Добавляем обработчики для дочерних чекбоксов
        document.addEventListener("change", function(e) {
            if (e.target && e.target.name === "torrentid[]") {
                updateMasterCheckbox();
            }
        });
        
        
        const masterCheckbox = document.getElementById("checkAllSwitch");
        if (masterCheckbox) {
            masterCheckbox.addEventListener("change", function() {
                toggleAllCheckboxes(this);
            });
            
            
            updateMasterCheckbox();
        }
    });
    
    function check_it(wHAT) {
        const moveElement = document.getElementById("movetorrent");
        if (moveElement) {
            moveElement.style.display = wHAT.value === "move" ? "block" : "none";
        }
    }
</script>

<form method="post" action="' . $BASEURL . '/admin/index.php?act=manage_torrents" name="manage_torrents" id="manage_torrents">
<input type="hidden" name="do" value="update" />
<input type="hidden" name="return" value="yes" />
<input type="hidden" name="return_address" value="' . $_SERVER['SCRIPT_NAME'] . '?page=' . (int)($_GET['page'] ?? 0) . '&amp;' . 
    (isset($pagelinks) && count($pagelinks) > 0 ? implode('&amp;', $pagelinks) . '&amp;' : '') . 
    (isset($pagelinks2) && count($pagelinks2) > 0 ? implode('&amp;', $pagelinks2) : '') . '" />
' : '') . '

<div id="listtorrents">

<thead>


<tr>
		<td>
		</td>
		
		<td>
		</td>
		
		<td>
		</td>
		
		<td>
		</td>

		<td>
	    </td>
		
		<td>
		</td>
		
		<td>
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
        $SEOLinkC = ts_seo($Torrent['category'], $Torrent['catname'], 'c');
        
        $categoryIcon = 'fa-solid fa-question';
        foreach ($_categoriesC as $category) {
            if ($category['name'] === $Torrent['catname']) {
                $categoryIcon = $category['icon'];
                break;
            }
        }
        
        $catssss = '
        <td class="trow1" align="center" class="unsortable2">
            <a href="' . $SEOLinkC . '">
                <i class="' . $categoryIcon . '" style="font-size: 30px; transition: all 0.3s ease;" title="' . htmlspecialchars($Torrent['catname']) . '"></i>
            </a>
        </td>';
        
       	

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
                                <span>' . ts_nf($Torrent['seeders']) . ' seeders</span>
                            </div>' : '') . '
                            ' . ($Torrent['leechers'] > 0 ? '
                            <div class="detail-item text-warning">
                                <i class="bi bi-arrow-down-circle me-2"></i>
                                <span>' . ts_nf($Torrent['leechers']) . ' leechers</span>
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
			
			
			
			
			
			
			
			
			
			
			
			
			
        
        $act = $d_link . "
              <span id=\"bookmark" . $count . "\">" . 
                   get_torrent_bookmark_state($CURUSER['id'], (int)$Torrent['id']) . 
               "</span>"; 
        
        $zax = cutename($Torrent['name']);
        
       /// $ss = '
        //<a href="' . $SEOLink. '" title="' . $zax . '">
        //    ' . (!empty($keywords) ? 
        //        highlight(htmlspecialchars_uni($keywords), cutename($Torrent['name'])) : 
       //         cutename($Torrent['name'])) . '
       /// </a>';
        
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
        
        
		
		$moderation = ($is_mod ? '
<td align="center" class="unsortable2">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="torrentid_' . $Torrent['id'] . '" 
            name="torrentid[]" 
            value="' . $Torrent['id'] . '" 
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









	
$ListTorrentsss = '
<tr class="torrent-row"
    data-id="' . (int)$Torrent['id'] . '" 
    data-seeders="' . $s . '" 
    data-leechers="' . $l . '"
    data-external="' . ($Torrent['ts_external'] === 'yes' ? 'yes' : 'no') . '">	
	
	
	
	
	





<style>
/* ===== MODERN TORRENT TABLE ===== */
/* Enhanced typography and spacing */


.table td, .table th {
    font-size: 1rem;
}
thead th {
    font-weight: 600;
    white-space: nowrap;
    vertical-align: middle;
}
thead th i { opacity: 0.75; }



/* ===== POSTER STYLES ===== */
.torrent-poster-cell {
    width: 85px;
    padding: 8px 6px !important;
    text-align: center;
    vertical-align: middle;
}

.torrent-poster {
    width: 60px;
    height: 85px;
    object-fit: cover;
    border-radius: 8px;
    display: block;
    margin: 6px auto 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}

.torrent-poster:hover {
    transform: scale(1.08) translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.25);
}

.torrent-poster-placeholder {
    width: 60px;
    height: 85px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--bs-secondary-bg), var(--bs-dark-bg));
    border-radius: 8px;
    margin: 6px auto 0;
    border: 1px solid rgba(var(--bs-primary-rgb), 0.2);
}

/* ===== POSTER ZOOM ENHANCED ===== */
.poster-zoom-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    z-index: 9999;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
}
.poster-zoom-img {
    max-width: 280px;
    max-height: 400px;
    object-fit: cover;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,.5);
    opacity: 0;
    transform: scale(0.85);
    transition: opacity 0.2s ease, transform 0.2s ease;
    pointer-events: none;
}
.poster-zoom-img.visible {
    opacity: 1;
    transform: scale(1);
}

/* ===== CATEGORY ICON ===== */
.category-icon-link {
    display: block;
    text-align: center;
    text-decoration: none;
    line-height: 1;
    margin-bottom: 6px;
}

.category-icon-small {
    font-size: 20px;
    transition: all 0.2s ease;
    display: inline-block;
}

.category-icon-small:hover {
    transform: scale(1.2);
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
}


.category-card:hover {
    border-color: #3b82f6 !important;
    background: rgba(59,130,246,0.05);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59,130,246,0.12);
}

/* ===== TORRENT INFO ===== */
.torrent-info-cell {
    vertical-align: middle !important;
    padding: 10px 12px !important;
}

.torrent-title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 6px;
}

.torrent-name-link {
    font-weight: 700;
    font-size: 1rem;
    color: var(--bs-body-color);
    text-decoration: none;
    flex: 1;
    line-height: 1.4;
    transition: all 0.2s ease;
    display: inline-block;
}

.torrent-name-link:hover { 
    color: var(--bs-primary);
    transform: translateX(2px);
}

.torrent-actions-inline {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.torrent-actions-inline a {
    opacity: 0.7;
    transition: all 0.2s ease;
}

.torrent-actions-inline a:hover {
    opacity: 1;
    transform: scale(1.1);
}

.torrent-meta {
    margin-top: 6px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 0.8rem;
    color: var(--bs-secondary-color);
}

.torrent-date {
    display: inline-flex;
    align-items: center;
    background: rgba(var(--bs-primary-rgb), 0.08);
    padding: 2px 8px;
    border-radius: 12px;
}

.torrent-flags {
    display: inline-flex;
    gap: 4px;
}

/* ===== TAGS ENHANCED ===== */
.torrent-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 6px;
}

.torrent-tags .badge {
    font-size: 0.7rem;
    padding: 3px 10px;
    border-radius: 20px;
    font-weight: 600;
    background: linear-gradient(135deg, var(--bs-primary), var(--bs-info));
    color: white;
    transition: all 0.2s ease;
}

.torrent-tags .badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

/* ===== STAT CELLS ===== */
.torrent-stat-cell {
    vertical-align: middle !important;
    white-space: nowrap;
    font-size: 0.9rem;
    padding: 8px 6px !important;
    text-align: center;
    font-weight: 600;
}

.torrent-stat-cell a {
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.torrent-stat-cell a:hover {
    transform: translateY(-1px);
}

/* Seeders specific */
.torrent-stat-cell a.text-success:hover {
    text-shadow: 0 0 8px rgba(40,167,69,0.3);
}

/* Leechers specific */
.torrent-stat-cell a.text-danger:hover {
    text-shadow: 0 0 8px rgba(220,53,69,0.3);
}

/* ===== UPLOADER ===== */
.torrent-uploader-cell {
    vertical-align: middle !important;
    font-size: 0.85rem;
    padding: 8px 8px !important;
    max-width: 130px;
    font-weight: 500;
}

.torrent-uploader-cell a {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s ease;
}

.torrent-uploader-cell a:hover {
    color: var(--bs-primary) !important;
    transform: translateX(2px);
}

/* ===== ROW ANIMATIONS ===== */
.torrent-row {
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.torrent-row:hover {
    background: rgba(var(--bs-primary-rgb), 0.05) !important;
    border-left-color: var(--bs-primary);
    transform: translateX(2px);
}


/* Dead torrent */
.torrent-row.is-dead { opacity: 0.55; filter: grayscale(0.3); }
.torrent-row.is-dead:hover { opacity: 0.8; filter: none; }






/* ===== RESPONSIVE DESIGN ===== */
/* Mobile */
@media (max-width: 768px) {
    .torrent-poster { width: 40px; height: 55px; }
    .torrent-poster-placeholder { width: 40px; height: 55px; }
    .torrent-name-link { font-size: 0.9rem !important; }
    .torrent-stat-cell { font-size: 0.85rem !important; }
    thead th i { font-size: 1rem; }
}
</style>



<!-- Enhanced Poster Zoom Overlay -->
<div class="poster-zoom-overlay" id="posterZoomOverlay">
    <img src="" alt="Poster preview" class="poster-zoom-img" id="posterZoomImg">
</div>

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
        <a href="'.$BASEURL.'/details.php?id='.$Torrent['id'].'&tab=peers#seeders" 
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
        <a href="'.$BASEURL.'/details.php?id='.$Torrent['id'].'&tab=peers#leechers" 
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
                <div class="text-center py-5">
                    <div class="empty-state">
                        <i class="fa-regular fa-folder-open fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted mb-3">No torrents uploaded yet</h4>
                    </div>
                </div>
            </div>
        </td>
    </tr>';
}

//eval("\$bedit = \"" . $templates->get("browse_edit") . "\";");



$bedit = '
<div class="container mt-3">
    <div class="card">
        <div class="card-header py-2 px-3">
            <span style="font-size:.8rem;font-weight:600;color:#94a3b8;
                         text-transform:uppercase;letter-spacing:1px;">
                <i class="fas fa-shield-alt me-2" style="color:#3b82f6;"></i>
                Moderation Actions
            </span>
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-center">

                <!-- Action select -->
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="actiontype" 
                            id="actiontype" onchange="check_it(this)"
                            style="border-radius:8px;border:1.5px solid #e2e8f0;font-size:.83rem;">
                       
					  <option value="0">▸ Select Action</option>
<optgroup label="── Torrent ──">
    <option value="move">↗ Move selected</option>
    <option value="delete">✕ Delete selected</option>
    <option value="sticky">★ Sticky / Unsticky</option>
    <option value="visible">◎ Visible / Hidden</option>
    <option value="banned">◌ Ban / Unban</option>
    <option value="nuke">✦ Nuke / Unnuke</option>
</optgroup>
<optgroup label="── Promo ──">
    <option value="free">◈ Free / Non-Free</option>
    <option value="silver">◇ Silver / Non-Silver</option>
    <option value="doubleupload">⊕ Double Upload ON/OFF</option>
</optgroup>
<optgroup label="── Other ──">
    <option value="anonymous">◉ Anonymize / Deanon</option>
    <option value="openclose">⊞ Open / Close comments</option>
    <option value="request">◫ Request / Non-Request</option>
</optgroup>
                    
					
					</select>
                </div>

                <!-- Move category (hidden) -->
                <div class="col-md-4" id="movetorrent" style="display:none;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="font-size:.78rem;color:#64748b;white-space:nowrap;">
                            <i class="fas fa-folder-open me-1"></i>Move to:
                        </span>
                        '.$catdropdown.'
                    </div>
                </div>

                <!-- Submit -->
                <div class="col-auto ms-auto">
                    <button type="submit" class="btn btn-primary"
                            style="border-radius:8px;font-weight:600;font-size:.83rem;">
                        <i class="fas fa-play me-1"></i> Apply
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>
</form>
';








$ListTorrents .= '
    </tbody>
</table>
' . ($is_mod ? $bedit : '') . '
</div>';

stdhead($lang->browse['btitle']);




$showimages = 'yes';
$i_torrent_limit = '15';

$torrent_cache = $cache->read('torrents');
$carouselItems = [];
if (!empty($torrent_cache) && is_array($torrent_cache)) {
    foreach ($torrent_cache as $row2) {
        if (!empty($row2['t_image'])) {
            $carouselItems[] = $row2;
        }
    }
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
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/autocomplete.js"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/category-highlight.js"></script>';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/autocomplete.css">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/bootstrap-icons.css">';








$actionns = $is_mod ? $lang->browse['acction'] : '';

eval("\$table = \"" . $templates->get("browse_table") . "\";");

echo '
' . $___notice . '
' . $categories . '
' . $SearchTorrent . '
' . $table . '
';




?>
<script>
(function() {
    const overlay = document.getElementById('posterZoomOverlay');
    const img     = document.getElementById('posterZoomImg');
    let timer     = null;

    document.addEventListener('mouseenter', function(e) {
        if (!e.target || !e.target.closest) return;
        const link = e.target.closest('.poster-link[data-zoom]');
        if (!link) return;

        const src = link.dataset.zoom;
        if (!src) return;

        clearTimeout(timer);
        timer = setTimeout(() => {
            img.src = src;
            img.classList.add('visible');
        }, 150);
    }, true);

    document.addEventListener('mouseleave', function(e) {
        if (!e.target || !e.target.closest) return;
        const link = e.target.closest('.poster-link[data-zoom]');
        if (!link) return;

        clearTimeout(timer);
        img.classList.remove('visible');
        setTimeout(() => { img.src = ''; }, 200);
    }, true);

    document.addEventListener('mousemove', function(e) {
        if (!img.classList.contains('visible')) return;

        const x = e.clientX;
        const y = e.clientY;
        const W = window.innerWidth;
        const H = window.innerHeight;

        const offsetX = x > W / 2 ? -300 : 20;
        const offsetY = y > H / 2 ? -420 : 20;

        img.style.position  = 'fixed';
        img.style.left      = (x + offsetX) + 'px';
        img.style.top       = (y + offsetY) + 'px';
        img.style.transform = 'none';
    });
})();


document.querySelectorAll('.torrent-row').forEach(row => {
    const seeders  = parseInt(row.dataset.seeders  || 0);
    const leechers = parseInt(row.dataset.leechers || 0);
    const external = row.dataset.external === 'yes';
    
    if (seeders === 0 && leechers === 0 && !external) {
        row.classList.add('is-dead');
    }
});

</script>


<?




stdfoot();