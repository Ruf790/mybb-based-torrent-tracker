<?php


define('THIS_SCRIPT', 'browse.php');
define('B_VERSION', '6.6.3 by xam');
define('SKIP_MOD_QUERIES', true);
define('SKIP_CACHE_MESSAGE', true);
define("SCRIPTNAME", "browse.php");


$templatelist = "browses,browse_table,browse_edit,browse_categories,browse_categories2,multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";



require('./global.php');


require_once INC_PATH . '/functions_multipage.php';




if (!isset($CURUSER))
{
	print_no_permission();
}





/*
	----------------------------------------------------------------------
	TS SE Security Team Message:
	----------------------------------------------------------------------
	Please DO NOT modify this file unless you know what are you doing!
	----------------------------------------------------------------------
*/

if (!headers_sent())
{
	setcookie('acqu', base64_encode($CURUSER['usergroup']), TIMENOW+24*60*60);
}


$lang->load('browse');
//$category = intval(TS_Global('category'));
//$keywords = TS_Global('keywords');
//$search_type = TS_Global('search_type');

$is_mod = is_mod($usergroups);

$category = isset($_POST['category']) ? intval($_POST['category']) : (isset($_GET['category']) ? intval($_GET['category']) : 0);
$keywords = isset($_POST['keywords']) ? $_POST['keywords'] : (isset($_GET['keywords']) ? $_GET['keywords'] : '');
$search_type = isset($_POST['search_type']) ? trim($_POST['search_type']) : (isset($_GET['search_type']) ? trim($_GET['search_type']) : '');

$special_search = TS_Global('special_search');

$sort = TS_Global('sort');
$order = TS_Global('order');
$daysprune = TS_Global('daysprune');
$include_dead_torrents = TS_Global('include_dead_torrents');
$Links = array();
require_once(INC_PATH.'/functions_mkprettytime.php');


$use_ajax_search = 'no';
$xbt_active = 'no';



function return_torrent_bookmark_array($userid)
{
	global $db;
	static $ret;
	if (!$ret)
	{
			$ret = array();
			$res = $db->simple_select("bookmarks", "*", "userid='{$userid}'");
			
			if ($db->num_rows($res) != 0)
			{
				while ($row = mysqli_fetch_array($res))
					$ret[] = $row['torrentid'];
			} 
			else 
			{
                $ret[] = 0;
			}
	}
	return $ret;
}


function get_torrent_bookmark_state($userid, $torrentid, $text = false)
{
	global $lang;
	$userid = 0 + $userid;
	$torrentid = 0 + $torrentid;
	$ret = array();
	$ret = return_torrent_bookmark_array($userid);
	
	if (!count($ret) || !in_array($torrentid, $ret, false)) // already bookmarked
		$act = ($text == true ?  $lang->browse['title_bookmark_torrent']  : "<i class=\"fa-regular fa-star\" style=\"color: #0a5ae6;\" data-toggle=\"tooltip\" data-placement=\"top\" title=\"".$lang->browse['title_bookmark_torrent']."\" /></i>");
	else
		$act = ($text == true ? $lang->browse['title_delbookmark_torrent'] : "<i class=\"fa-solid fa-star\" style=\"color: #0c5be4;\" data-toggle=\"tooltip\" data-placement=\"top\" alt=\"Bookmarked\" title=\"".$lang->browse['title_delbookmark_torrent']."\" /></i>");
	return $act;
}




$_freelechmod = $_silverleechmod = $_x2mod = false;
$___notice = '';
include(TSDIR.'/cache/freeleech.php');
include(INC_PATH.'/readconfig.php');
if ($__F_START < get_date_time() && $__F_END > get_date_time())
{
    // Загружаем стили только при активных режимах
    switch($__FLSTYPE)
    {
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
                 '.sprintf($lang->browse['f_leech'], $__F_START, $__F_END).'
                </div>
              </div>
            </div>
            </div>';

            $_freelechmod = true;

            // Загружаем CSS для Free Leech
            echo '<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">';
            echo '<link href="'.$BASEURL.'/include/templates/default/style/messagess.css" rel="stylesheet">';
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
                '.sprintf($lang->browse['s_leech'], $__F_START, $__F_END).'
                </div>
              </div>
            </div>
            </div>';

            $_silverleechmod = true;

            // Загружаем CSS для Silver Leech
            echo '<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">';
            echo '<link href="'.$BASEURL.'/include/templates/default/style/messagess.css" rel="stylesheet">';
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
                 '.sprintf($lang->browse['d_leech'], $__F_START, $__F_END).'
                </div>
              </div>
            </div>
            </div>';

            $_x2mod = true;

            // Загружаем CSS для Double Upload
            echo '<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">';
            echo '<link href="'.$BASEURL.'/include/templates/default/style/messagess.css" rel="stylesheet">';
        break;
    }
}



elseif ($bdayreward == 'yes' AND $bdayrewardtype)
{
	$curuserbday = explode('-', $CURUSER['birthday']);
	if (date('j-n') === $curuserbday[0].'-'.$curuserbday[1])
	{
		switch ($bdayrewardtype)
		{
			case 'freeleech';
				//$___notice = show_notice(sprintf($lang->browse['f_leech'], $curuserbday[0].'-'.$curuserbday[1].'-'.date('Y'), ($curuserbday[0] + 1).'-'.$curuserbday[1].'-'.date('Y')),false,$lang->browse['f_leech_h']);
			
			
			$___notice = '
<div class="container mt-3">
   <div class="alert alert-primary">
    <span id="new_ann" style="display: block;">
    
	'.sprintf($lang->browse['f_leech'], $curuserbday[0].'-'.$curuserbday[1].'-'.date('Y'), ($curuserbday[0] + 1).'-'.$curuserbday[1].'-'.date('Y')).'
  </div>
</div>';


			break;
			case 'silverleech';
				$___notice = show_notice(sprintf($lang->browse['s_leech'], $curuserbday[0].'-'.$curuserbday[1].'-'.date('Y'), ($curuserbday[0] + 1).'-'.$curuserbday[1].'-'.date('Y')),false,$lang->browse['s_leech_h']);
			break;
			case 'doubleupload';
				$___notice = show_notice(sprintf($lang->browse['d_leech'], $curuserbday[0].'-'.$curuserbday[1].'-'.date('Y'), ($curuserbday[0] + 1).'-'.$curuserbday[1].'-'.date('Y')),false,$lang->browse['d_leech_h']);
			break;
		}
	}
}










require(TSDIR.'/cache/categories.php');
$subcategories = array();
$searcincategories = array();
if (count($_categoriesS) > 0)
{
	foreach ($_categoriesS as $sc)
	{
		
		$sc['name'] = htmlspecialchars_uni($sc['name']);
		$searcincategories[] = $sc['id'];
		$scdesc = htmlspecialchars_uni($sc['cat_desc']);
		$SEOLinkC = ts_seo($sc['id'],$sc['name'],'c');
		$subcategories[$sc['pid']][] = '
		<span id="category'.$sc['id'].'"'.(isset($category) && $category == $sc['id'] || (!$category && strpos($CURUSER['notifs'], '[cat'.$sc['id'].']') !== FALSE && $usergroups['canemailnotify'] == 'yes') ? ' class="highlight"' : '').'>
			<a href="'.$SEOLinkC.'" title="'.$scdesc.'">'.$sc['name'].'</a>
		</span>';
	}
	unset($_categoriesS);
}

$count = 0;


eval("\$categories = \"".$templates->get("browse_categories")."\";");






if (($rows = count($_categoriesC)) > 0)
{
    

    foreach ($_categoriesC as $c)
    {
        $tracker_cats_per_row = '5';
        $table_cat_width = '';
        $table_cat_height = '';
        
        $searcincategories[] = $c['id'];
        if ($count && $count % $tracker_cats_per_row == 0)
        {
            $categories .= '</tr><tr class="none">';
        }

        $tracker_cats_width = '';
        $cname = htmlspecialchars_uni($c['name']);
        $cdesc = htmlspecialchars_uni($c['cat_desc']);
        $SEOLinkC = ts_seo($c['id'],$cname,'c');
        
       
        
         $categories .= '
        <td class="p-2">
        <div class="d-flex border rounded p-2 category-container" data-category-id="'.$c['id'].'">
            <div class="text-center">
                <a href="'.$SEOLinkC.'" class="d-block">
                    <i class="'.$c['icon'].' fa-2x category-icon" title="'.$cname.'"></i>
                </a>
            </div>
            <div class="ms-2" style="width: '.$tracker_cats_width.'px;">
                <span id="category'.$c['id'].'"'.(
                    isset($category) && $category == $c['id'] || 
                    (!$category && strpos($CURUSER['notifs'], '[cat'.$c['id'].']') !== FALSE && 
                    $usergroups['canemailnotify'] == 'yes') 
                    ? ' class="fw-bold text-primary"' 
                    : '').'>
                    
                    <a href="'.$SEOLinkC.'" title="'.$cdesc.'" class="text-decoration-none category-link" data-cat-id="'.$c['id'].'">
                         <h6 class="mb-1">'.$cname.'</h6>
                    </a>
                    
                </span>
                <div class="small text-muted">
                    '.(isset($subcategories[$c['id']]) ? implode(', ', $subcategories[$c['id']]) : $c['cat_desc']).'
                </div>
              </div>
             </div>
         </td>';
        
        $count++;
    }
    unset($_categoriesC);
}









eval("\$categories .= \"".$templates->get("browse_categories2")."\";");



require_once(INC_PATH.'/functions_category.php');
$catdropdown = ts_category_list('category',(isset($category) ? $category : ''),'<option value="0" style="color: gray;">'.$lang->browse['alltypes'].'</option>', 'categories');








$SearchTorrent = '
		<div class="container mt-3">
	
					
					'.$lang->browse['tsearch'].'
				
					<form method="post" action="'.$_SERVER['SCRIPT_NAME'].'" name="searchtorrent" id="searchtorrent">
					<input type="hidden" name="do" value="search" />
					
					
					
					
					
    
	                     
					<div class="form-group position-relative">
					
						<input type="text" class="form-control" id="torrent-search" name="keywords" placeholder="Search for a torrent..." autocomplete="off" value="'.($keywords ? htmlspecialchars_uni($keywords) : '').'">
                        <div id="autocomplete-results" class="dropdown-menu"></div>
						 
                    </div>
					
					</br>
					
					
  
		

				
					
					<label>
    <select class="form-select" id="search_type" name="search_type">
      <option value="t_name"'.($search_type == 't_name' ? ' selected="selected"' : '').'>'.$lang->browse['t_name'].'</option>
						<option value="t_description"'.($search_type == 't_description' ? ' selected="selected"' : '').'>'.$lang->browse['t_description'].'</option>
						
						<option value="t_tags"'.($search_type == 't_tags' ? ' selected="selected"' : '').'>'.'Tags'.'</option>
						
						<option value="t_both"'.($search_type == 't_both' || $search_type == '' ? ' selected="selected"' : '').'>'.$lang->browse['t_both'].'</option>
						<option value="t_uploader"'.($search_type == 't_uploader' ? ' selected="selected"' : '').'>'.$lang->browse['t_uploader'].'</option>
						<option value="t_genre"'.($search_type == 't_genre' ? ' selected="selected"' : '').'>'.$lang->browse['t_genre'].'</option>
    </select>
	</label>
					

      
	 '.$catdropdown.'
	  
    
			
					
					
					<label>
    <select class="form-select" name="include_dead_torrents">
                 <option value="yes"'.($include_dead_torrents == 'yes' ? ' selected="selected"' : '').'>'.$lang->browse['incdead1'].'</option>
				<option value="no"'.($include_dead_torrents == 'no' ? ' selected="selected"' : '').'>'.$lang->browse['incdead2'].'</option>
					
    </select>
	</label>
					
					
					
					
					<button type="submit" class="btn btn-primary" name="submit" value="'.$lang->global['buttonsearch'].'"><i class="fa-solid fa-magnifying-glass"></i> &nbsp;Search</button>
					
					
					</form>
				
		</div>
';











$WHERE = " WHERE".($include_dead_torrents == 'yes' ? '' : " t.visible = 'yes' AND")." t.banned = 'no'";
$Links[] = 'include_dead_torrents='.($include_dead_torrents == 'yes' ? 'yes' : 'no');

$innerjoin = '';
$params = []; // Массив для параметров подготовленных запросов

if ($special_search == 'myreseeds') {
    $Links[] = 'special_search=myreseeds';
    $WHERE .= ' AND t.seeders = 0 AND t.leechers > 0 AND t.owner = ?';
    $params[] = $CURUSER['id'];
} elseif ($special_search == 'mybookmarks') {
    $Links[] = 'special_search=mybookmarks';
    $innerjoin = ' INNER JOIN bookmarks b ON (b.torrentid = t.id)';
    $WHERE .= ' AND b.userid = ?';
    $params[] = $CURUSER['id'];
} elseif ($special_search == 'mytorrents') {
    $Links[] = 'special_search=mytorrents';
    $WHERE .= ' AND t.owner = ?';
    $params[] = $CURUSER['id'];
} elseif ($special_search == 'weaktorrents') {
    $Links[] = 'special_search=weaktorrents';
    $WHERE .= " AND t.visible = 'no' OR (t.leechers > 0 AND t.seeders = 0) OR (t.leechers = 0 AND t.seeders = 0)";
}

$extraquery = array();
$extra_params = []; // Параметры для дополнительных условий

if ($keywords AND $search_type) {
    $OrjKeywords = $keywords;
    $Links[] = 'keywords='.htmlspecialchars_uni($keywords);
    $Links[] = 'search_type='.htmlspecialchars_uni($search_type);
    
    if ($fulltextsearch == 'yes') {
        require(INC_PATH.'/function_search_clean.php');
        $keywords = clean_keywords_ft($keywords);
    }

    if ($keywords) {
        // ... (проверки минимальной длины остаются без изменений) ...

        switch ($search_type) {
            case 't_name':
                if ($fulltextsearch == 'yes') {
                    $extraquery[] = "(MATCH(t.name) AGAINST(? IN BOOLEAN MODE))";
                    $extra_params[] = $keywords;
                } else {
                    $extraquery[] = "(t.name LIKE ?)";
                    $extra_params[] = "%".$keywords."%";
                }
                break;
                
            case 't_description':
                if ($fulltextsearch == 'yes') {
                    $extraquery[] = "(MATCH(t.descr) AGAINST(? IN BOOLEAN MODE))";
                    $extra_params[] = $keywords;
                } else {
                    $extraquery[] = "(t.descr LIKE ?)";
                    $extra_params[] = "%".$keywords."%";
                }
                break;
                
            case 't_tags':
                if ($fulltextsearch == 'yes') {
                    $extraquery[] = "(MATCH(t.tags) AGAINST(? IN BOOLEAN MODE))";
                    $extra_params[] = $keywords;
                } else {
                    $extraquery[] = "(t.tags LIKE ?)";
                    $extra_params[] = "%".$keywords."%";
                }
                break;
                
            case 't_both':
                if ($fulltextsearch == 'yes') {
                    $extraquery[] = "(MATCH(t.name) AGAINST(? IN BOOLEAN MODE) OR MATCH(t.descr) AGAINST(? IN BOOLEAN MODE))";
                    $extra_params[] = $keywords;
                    $extra_params[] = $keywords;
                } else {
                    $extraquery[] = "(t.name LIKE ? OR t.descr LIKE ?)";
                    $extra_params[] = "%".$keywords."%";
                    $extra_params[] = "%".$keywords."%";
                }
                break;
                
            case 't_uploader':
                // Подготовленный запрос для поиска пользователя
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
                if ($fulltextsearch == 'yes') {
                    $extraquery[] = "(MATCH(t.t_link) AGAINST(? IN BOOLEAN MODE))";
                    $extra_params[] = $keywords;
                } else {
                    $extraquery[] = "(t.t_link LIKE ?)";
                    $extra_params[] = "%".$keywords."%";
                }
                break;
        }
        $keywords = $OrjKeywords;
    }
}

if ($category) {
    // Подготовленный запрос для подкатегорий
    $cat_query = $db->sql_query_prepared(
        "SELECT id FROM categories WHERE type='s' AND pid = ?", 
        [$category]
    );
    
    if ($db->num_rows($cat_query) > 0) {
        $squerycats = array();
        while ($squery = $db->fetch_array($cat_query)) {
            $squerycats[] = $squery['id'];
        }
        $extraquery[] = 't.category IN ('.$category.', '.implode(', ', $squerycats).')';
    } else {
        $extraquery[] = "t.category = ?";
        $extra_params[] = $category;
    }
    $Links[] = 'category='.$category;
}



//elseif ($usergroups['canemailnotify'] == 'yes' AND preg_match("#\[cat.+#i", $CURUSER['notifs']))
elseif (preg_match("#\[cat.+#i", $CURUSER['notifs']))
{
	$defaultcategories = array();
	foreach ($searcincategories as $catid)
	{
		if (strpos($CURUSER['notifs'], '[cat'.$catid.']') !== FALSE)
		{
			$defaultcategories[] = $catid;
		}
	}
	if (count($defaultcategories) > 0)
	{
		$WHERE .= ' AND t.category IN ('.implode(',', $defaultcategories).')';
		unset($defaultcategories);
	}
}

if ($special_search)
{
	$Links[] = 'special_search='.htmlspecialchars_uni($special_search);
}

if (count($extraquery) > 0) {
    $WHERE .= ' AND '.implode(' AND ', $extraquery);
    // Добавляем дополнительные параметры к основным
    $params = array_merge($params, $extra_params);
    $Links[] = 'do=search';
    $Links[] = 'keywords='.urlencode(htmlspecialchars_uni($keywords));
    $Links[] = 'search_type='.urlencode(htmlspecialchars_uni($search_type));
}

$orderby = 't.sticky, t.added DESC';

if ($sort OR $daysprune)
{
	$sort_array = array(
	'category', 'name', 'added', 'comments', 'size', 'times_completed', 'seeders', 'leechers', 'owner', 'hits', 'sticky', 'free', 'silver', 'isnuked', 'isrequest', 'doubleupload', 'ts_external', 'visible');
	$sort = (in_array($sort, $sort_array) ? $sort : false);
	$order = (strtolower($order) == 'asc' ? 'ASC' : 'DESC');
	$daysprune = ($daysprune == '-1' ? false : intval($daysprune));
	if ($sort)
	{
		//if ($xbt_active == 'yes')
		//{
		//	switch($sort)
		//	{
		//		case 'free':
		//			$orderby = 't.download_multiplier ASC';
		//		break;
		//		case 'silver':
		//			$orderby = 't.download_multiplier DESC';
		//		break;
		//		case 'doubleupload':
		//			$orderby = 't.upload_multiplier DESC';
		//		break;
		//		default:
		//			$orderby = 't.'.$sort.' '.$order;
		//		break;
			//}
		//}
		//else
		//{
			$orderby = 't.'.$sort.' '.$order;
		//}
		$Links[] = 'sort='.htmlspecialchars_uni($sort);
		$Links[] = 'order='.htmlspecialchars_uni($order);
	}
	if ($daysprune)
	{
		$WHERE .= " AND t.added >= ".(TIMENOW - ($daysprune * 86400));
		$Links[] = 'daysprune='.htmlspecialchars_uni($daysprune);
	}
}



$torrentsperpage = ($CURUSER['torrentsperpage'] <> 0 ? intval($CURUSER['torrentsperpage']) : $ts_perpage);

$threadcount = 0;

// Подготовленный запрос для подсчета
$count_sql = 'SELECT t.id, c.name, u.usergroup, g.gid 
              FROM torrents t'.$innerjoin.' 
              LEFT JOIN users u ON (t.owner=u.id) 
              LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
              LEFT JOIN categories c ON (t.category=c.id)'.$WHERE.' 
              ORDER BY '.$orderby;

$countquery = $db->sql_query_prepared($count_sql, $params);
$threadcount = $db->num_rows($countquery);


// How many pages are there?
if(!$torrentsperpage || (int)$torrentsperpage < 1)
{
	$torrentsperpage = 20;
}

$perpage = $torrentsperpage;


//if($mybb->input['page'] > 0)
if(isset($mybb->input['page']) && intval($mybb->input['page']) > 0)
{
	$page = $mybb->input['page'];
	$start = ($page-1) * $perpage;
	$pages = $threadcount/ $perpage;
	$pages = ceil($pages);
	if($page > $pages || $page <= 0)
	{
		$start = 0;
		$page = 1;
	}
}
else
{
	$start = 0;
	$page = 1;
}

$end = $start + $perpage;
$lower = $start + 1;
$upper = $end;

if($upper > $threadcount)
{
	$upper = $threadcount;
}


$page_url = $_SERVER['SCRIPT_NAME'].'?'.(is_array($Links) && count($Links) > 0 ? implode('&amp;', $Links) : '').'';
$multipage = multipage($threadcount, $perpage, $page, $page_url);


$ListTorrents = '
<script type="text/javascript" src="'.$BASEURL.'/scripts/ts_update.js?v='.O_SCRIPT_VERSION.'"></script>
'.($is_mod ? '
<script type="text/javascript">
	function check_it(wHAT)
	{
		if (wHAT.value == "move")
		{
			document.getElementById("movetorrent").style.display = "block";
		}
		else
		{
			document.getElementById("movetorrent").style.display = "none";
		}
	}
</script>


<form method="post" action="'.$BASEURL.'/admin/index.php?act=manage_torrents" name="manage_torrents">
<input type="hidden" name="do" value="update" />
<input type="hidden" name="return" value="yes" />
<input type="hidden" name="return_address" value="'.$_SERVER['SCRIPT_NAME'].'?page='.intval(isset($_GET['page']) ? $_GET['page'] : 0).'&amp;'.(isset($pagelinks) && count($pagelinks) > 0 ? implode('&amp;', $pagelinks).'&amp;' : '').(isset($pagelinks2) && count($pagelinks2) > 0 ? implode('&amp;', $pagelinks2) : '').'" />
' : '').'


<div id="listtorrents" style="display: inline;">

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
			

'.($is_mod ? '
<td class="thead text-center">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            checkall="group1" 
            onclick="return select_deselectAll(\'manage_torrents\', this, \'group1\');" 
            id="checkAllSwitch"
            role="switch" />
    </div>
</td>' : '').'

</tr>';



$torrentspeed = 'no';
$groupby = $torrentspeed == 'yes' ? ' GROUP BY t.id ' : '';

// Строим SQL с плейсхолдерами
$sql = 'SELECT t.*, c.name as catname, u.username, u.usergroup 
        FROM torrents t '.$innerjoin.' 
        LEFT JOIN categories c ON (t.category=c.id) 
        LEFT JOIN users u ON (t.owner=u.id) '
        .($torrentspeed == 'yes' ? ' LEFT JOIN '.($xbt_active == 'yes' ? 'xbt_files_users p ON (t.id=p.fid)' : 'peers p ON (t.id=p.torrent)') : '')
        .$WHERE.$groupby.' ORDER BY '.$orderby.' LIMIT ?, ?';

// Добавляем параметры пагинации к уже существующим параметрам
$data_params = $params;
$data_params[] = (int)$start;
$data_params[] = (int)$perpage;

// Выполняем подготовленный запрос
$Query = $db->sql_query_prepared($sql, $data_params);

$queryhash = md5($sql . serialize($data_params)); // Хэш с учетом параметров
$TotalTorrents = array();

if ($db->num_rows($Query)) 
{
    while($t = mysqli_fetch_assoc($Query)) 
    {
        $TotalTorrents[] = $t;
    }
}



if ($TotalTorrents AND count($TotalTorrents))
{
	require_once(INC_PATH.'/functions_imdb_rating.php');
	//require_once(INC_PATH.'/functions_get_torrent_flags.php');
	$worked = 0;
	foreach($TotalTorrents as $Torrent)
	{
		$ShowImdb=false;
		if ($IMDBRating = TSSEGetIMDBRatingImage($Torrent['t_link']))
		{
			$ShowImdb=true;
		}

		
		
		
		if (empty($Torrent["tags"])) 
        {
           $keywords2 = '';
        } 
        else 
        {
           $tags = explode(",", $Torrent['tags']);
           $keywords2 = "";
           foreach ($tags as $tag) 
	       {
             
			  $keywords2 .= '<a href="'.$BASEURL.'/browse.php?do=search&keywords='.$tag.'&search_type=t_tags" title="'.$tag.'" class="badge bg-primary">'.$tag.'</a> ';
		
		   }
           $keywords2 = substr($keywords2, 0, (strlen($keywords2) - 1));
        }

		
         $table_cat_width2 = '';
		 $table_cat_height2 = '';
		 
		$SEOLink = get_torrent_link($Torrent['id']);
		$SEOLinkC = ts_seo($Torrent['category'],$Torrent['catname'],'c');
		
		//$count = 0;
		
		
		$categoryIcon = 'fa-solid fa-question'; // Иконка по умолчанию
        foreach ($_categoriesC as $category) 
		{
            if ($category['name'] == $Torrent['catname']) {
                $categoryIcon = $category['icon'];
                break;
            }
        }
		
		
		$catssss = '
		<td class="trow1" align="center" style="width: {$table_cat_width2}px; height: {$table_cat_height2}px;" class="unsortable2">
                 <a href="'.$SEOLinkC.'">
                    <i class="'.$categoryIcon.'" style="font-size: 30px; transition: all 0.3s ease;" title="'.htmlspecialchars($Torrent['catname']).'"></i>
                 </a>
        </td>';
		
		
		
		
		
		
		$d_link ='<a href="'.get_download_link($Torrent['id']).'" data-toggle="tooltip" data-placement="top" alt="'.sprintf($lang->browse['torrentdown'], $Torrent['name']).'" title="'.sprintf($lang->browse['torrentdown'], $Torrent['name']).'">
		<i class="fa-sharp fa-solid fa-file-arrow-down fa-lg" style="color: #055df5;"></i></a>';
		
		$act = "";
		//$bookmark = "onclick=bookmark(".$Torrent['id'].",".$count.");";
		$act .= ($act ? "<br />" : "")."
		
		".$d_link."
		
		
		<a id=\"bookmark".$count."\" onclick=bookmark(".$Torrent['id'].",".$count.")>".get_torrent_bookmark_state($CURUSER['id'], $Torrent['id'])."</a>";
		
		
		
		
		
		
		
		
		
     
		$zax = cutename($Torrent['name']);
		
		
		$ss =  '
		
		  <a href="'.$SEOLink.'" data-toggle="popover" data-img="'.htmlspecialchars_uni($Torrent['t_image']).'" title="'.$zax.'">
		 
		 '.(!empty($keywords) ? highlight(htmlspecialchars_uni($keywords), cutename($Torrent['name'], ($ShowImdb ? 35 : 50))) : cutename($Torrent['name'], ($ShowImdb ? 35 : 50))).'
		 
		 </a>';
		 
		 
		 
		 

        $zz = GetTorrentTags($Torrent);

        $ads = my_datee('relative', $Torrent['added']);

        $meksize = mksize($Torrent['size']);
		

		 $para = ts_nf($Torrent['times_completed']);

        $sedars = ts_nf($Torrent['seeders']);
		
		 $lechars = ts_nf($Torrent['leechers']);
		
		
		
		 //$meksize = '<span class="badge bg-secondary me-1"><i class="fa-solid fa-database me-1"></i>' . mksize($Torrent['size']) . '</span>';
		
		
		//$para =  '<span class="badge bg-primary text-white">
        //<i class="fa-solid fa-circle-check me-1"></i> ' . ts_nf($Torrent['times_completed']) . '</span>';
		
		
		//$sedars = '<span class="badge bg-success text-white">
        //<i class="fa-solid fa-arrow-up me-1"></i> ' . $Torrent['seeders'] . '</span>';
		
		//$lechars =  '<span class="badge bg-danger text-white">
        //<i class="fa-solid fa-arrow-down me-1"></i> ' . ts_nf($Torrent['leechers']) . '</span>';
	   
	   
	   
	   
	   
	   
	   
 
 
        $waza =  (!$is_mod && $Torrent['owner'] != $CURUSER['id'] && $Torrent['anonymous'] == 'yes' ? '
				<div>
					'.$lang->global['anonymous'].'
				</div>' : '
				<a href="'.get_profile_link($Torrent['owner']).'">'.format_name($Torrent['username'], $Torrent['usergroup']).'</a>
				'.($Torrent['anonymous'] == 'yes' ? '
				<div>
					'.$lang->global['anonymous'].' 
				</div>
				' : '').'
				');
				
				
	    $mazaa = ($is_mod ? '
<td align="center" class="unsortable2">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="torrentid_' . $Torrent['id'] . '" 
            name="torrentid[]" 
            value="' . $Torrent['id'] . '" 
            checkme="group1"
            role="switch"
        />
    </div>
</td>' : '');

		
		
		
		
		eval("\$ListTorrentsss = \"".$templates->get("browses")."\";");
		
		
		
		$ListTorrents .= 
		
		
		''.$ListTorrentsss.'';
		$count++;
			
	}
	
}
else
{
	$ListTorrents .= '
		

<tr>
    <td colspan="'.($is_mod ? '10' : '9').'">
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



eval("\$bedit = \"".$templates->get("browse_edit")."\";");

$ListTorrents .= '
	</tbody>
</table>

'.($is_mod ?'


'.$bedit.'



' : '').'
</div>';
$SortOptions = '
<!-- begin: Quick Sort -->
<form method="post" action="'.$_SERVER['SCRIPT_NAME'].'">
<input type="hidden" name="do" value="quick_sort" />
<input type="hidden" name="page" value="'.intval(TS_Global('page')).'" />
<input type="hidden" name="category" value="'.$category.'" />
<input type="hidden" name="search_type" value="'.$search_type.'" />
<input type="hidden" name="keywords" value="'.htmlspecialchars_uni($keywords).'" />


<table>
	<tbody>
		<tr>
			<td>
				'.$lang->browse['qtitlemain'].'
			</td>
		</tr>
		
		<tr>
			<td>
				<table>
					<tr>
						<td class="none">
							<div>
								<b>'.$lang->browse['sortby1'].'</b>
							</div>
							<select class="form-select" name="sort" id="sort">
								<option value="category"'.($sort == 'category' ? ' selected="selected"' : '').'>'.$lang->browse['type'].'</option>
								<option value="name"'.($sort == 'name' ? ' selected="selected"' : '').'>'.$lang->browse['t_name'].'</option>
								<option value="added"'.($sort == 'added' ? ' selected="selected"' : '').'>'.$lang->browse['added'].'</option>
								<option value="comments"'.($sort == 'comments' ? ' selected="selected"' : '').'>'.$lang->browse['sortby3'].'</option>
								<option value="size"'.($sort == 'size' ? ' selected="selected"' : '').'>'.$lang->browse['sortby6'].'</option>
								<option value="times_completed"'.($sort == 'times_completed' ? ' selected="selected"' : '').'>'.$lang->browse['sortby7'].'</option>
								<option value="seeders"'.($sort == 'seeders' ? ' selected="selected"' : '').'>'.$lang->browse['sortby4'].'</option>
								<option value="leechers"'.($sort == 'leechers' ? ' selected="selected"' : '').'>'.$lang->browse['sortby5'].'</option>
								<option value="owner"'.($sort == 'owner' ? ' selected="selected"' : '').'>'.$lang->browse['sortby8'].'</option>
								<option value="hits"'.($sort == 'hits' ? ' selected="selected"' : '').'>'.$lang->browse['views'].'</option>
								<option value="sticky"'.($sort == 'sticky' ? ' selected="selected"' : '').'>'.$lang->browse['sortby9'].'</option>
								<option value="free"'.($sort == 'free' ? ' selected="selected"' : '').'>'.$lang->browse['sortby10'].'</option>
								<option value="silver"'.($sort == 'silver' ? ' selected="selected"' : '').'>'.$lang->browse['sortby11'].'</option>
								<option value="doubleupload"'.($sort == 'doubleupload' ? ' selected="selected"' : '').'>'.$lang->browse['sortby14'].'</option>
								<option value="isnuked"'.($sort == 'isnuked' ? ' selected="selected"' : '').'>'.$lang->browse['sortby12'].'</option>
								<option value="isrequest"'.($sort == 'isrequest' ? ' selected="selected"' : '').'>'.$lang->browse['sortby13'].'</option>
								<option value="ts_external"'.($sort == 'ts_external' ? ' selected="selected"' : '').'>'.$lang->browse['sortby15'].'</option>
								<option value="visible"'.($sort == 'visible' ? ' selected="selected"' : '').'>'.$lang->browse['sortby16'].'</option>
						
							</select>
						</td>
						<td class="none">
							<div>
								<b>'.$lang->browse['orderby1'].'</b>
							</div>
							<select class="form-select" name="order" id="order">
								<option value="desc"'.(strtolower($order) == 'desc' ? ' selected="selected"' : '').'>'.$lang->browse['orderby2'].'</option>
								<option value="asc"'.(strtolower($order) == 'asc' ? ' selected="selected"' : '').'>'.$lang->browse['orderby3'].'</option>
							</select>
						</td>
						<td class="none">
							<div>
									<b>'.$lang->browse['qtitle'].'</b>
							</div>
							<select class="form-select" name="daysprune" id="daysprune">
								<option value="1"'.($daysprune == '1' ? ' selected="selected"' : '').'>'.$lang->browse['qorder3'].'</option>
								<option value="2"'.($daysprune == '2' ? ' selected="selected"' : '').'>'.$lang->browse['qorder4'].'</option>
								<option value="7"'.($daysprune == '7' ? ' selected="selected"' : '').'>'.$lang->browse['qorder5'].'</option>
								<option value="10"'.($daysprune == '10' ? ' selected="selected"' : '').'>'.$lang->browse['qorder6'].'</option>
								<option value="14"'.($daysprune == '14' ? ' selected="selected"' : '').'>'.$lang->browse['qorder7'].'</option>
								<option value="30"'.($daysprune == '30' ? ' selected="selected"' : '').'>'.$lang->browse['qorder8'].'</option>
								<option value="45"'.($daysprune == '45' ? ' selected="selected"' : '').'>'.$lang->browse['qorder9'].'</option>
								<option value="60"'.($daysprune == '60' ? ' selected="selected"' : '').'>'.$lang->browse['qorder10'].'</option>
								<option value="75"'.($daysprune == '75' ? ' selected="selected"' : '').'>'.$lang->browse['qorder11'].'</option>
								<option value="100"'.($daysprune == '100' ? ' selected="selected"' : '').'>'.$lang->browse['qorder12'].'</option>
								<option value="365"'.($daysprune == '365' ? ' selected="selected"' : '').'>'.$lang->browse['qorder13'].'</option>
								<option value="-1"'.($daysprune == '-1' || !$daysprune || $daysprune == '' ? ' selected="selected"' : '').'>'.$lang->browse['qorder14'].'</option>
							</select>
						</td>
						<td class="none">
							<div>
								&nbsp;
							</div>
						
							<button type="submit" class="btn btn-primary" name="submit" value="'.$lang->browse['tsearch'].'"><i class="fa-solid fa-magnifying-glass"></i> &nbsp;Search</button>
							
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</tbody>
</table>
</form>
<!-- end: Quick Sort -->
';



stdhead($lang->browse['btitle']);


$showimages = 'yes';
$i_torrent_limit = '15';

$torrent_cache = $cache->read('torrents');


// Preprocess the data first
$carouselItems = [];
foreach ($torrent_cache as $row2) 
{
    if (!empty($row2['t_image'])) 
	{
        $carouselItems[] = $row2;
    }
}
$total = count($carouselItems);





?>
  


<?php if ($showimages == 'yes'): ?>


<?php if ($total > 0): ?>



<div class="container mt-3">
<div id="cachedTorrentCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
    
    <!-- Carousel Indicators (dots) -->
    <div class="carousel-indicators">
        <?php for ($i = 0; $i < $total; $i++): ?>
            <button type="button" data-bs-target="#cachedTorrentCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $i + 1 ?>"></button>
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
			     <img src="<?= $image ?>" class="d-block w-100" alt="<?= $title ?>" style="max-height: 300px; object-fit: cover;">
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


<?php endif; ?>
<?php endif; ?>



<?



echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/bookmark.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/tooltip_image.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/tooltip.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/autocomplete.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/category-highlight.js"></script>';
echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/autocomplete.css">';



if ($is_mod)
{
	$actionns = $lang->browse['acction'];
}
else
{
    $actionns = '';
}


eval("\$table = \"".$templates->get("browse_table")."\";");




















echo '

'.$___notice.'

'.$categories.'

'.$SearchTorrent.'
				
'.$table.'

';


stdfoot();





function GetTorrentTags($t)
{
	global $lang, $BASEURL, $pic_base_url, $is_mod, $CURUSER;
	$ShowImage = (TIMENOW - $t['ts_external_lastupdate'] < 3600) ? (!$is_mod ? false : true) : true;
	$I = array();
	if ($t['added'] > $CURUSER['last_login']) $I[] = '
	
	 <span class="badge bg-danger" alt="'.$lang->browse['newtorrent'].'" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['newtorrent'].'" >New</span>
	
	';
	if ($t['free'] == 'yes') $I[] = '
	
	
	
	<span class="badge bg-success" alt="'.$lang->browse['freedownload'].'" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['freedownload'].'">F</span>
	
	';
	if ($t['silver'] == 'yes') $I[] = '
	
		
	<span class="badge bg-secondary" alt="'.$lang->browse['silverdownload'].'" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['silverdownload'].'">S</span>
	
	';
	//if ($t['isnuked'] == 'yes') $I[] = '<img src="'.$BASEURL.'/'.$pic_base_url.'isnuked.gif" border="0" alt="'.htmlspecialchars_uni($t['WhyNuked']).'" title="'.htmlspecialchars_uni($t['WhyNuked']).'" />';
	
	
	if ($t['isnuked'] == 'yes') $I[] = '
	
	
	<i class="fa-solid fa-circle-radiation fa-lg" style="color: #e70808;" data-toggle="tooltip" data-placement="top" alt="'.htmlspecialchars_uni($t['WhyNuked']).'" title="'.htmlspecialchars_uni($t['WhyNuked']).'" /></i>
	';
	
	
	
	
	
	if ($t['isrequest'] == 'yes') $I[] = '
	

	
	<span class="badge bg-primary" alt="'.$lang->browse['requested'].'" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['requested'].'">R</span>
	
	';
	if ($t['doubleupload'] == 'yes') $I[] = '
	
	
	<span class="badge bg-dark" alt="'.$lang->browse['dupload'].'" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['dupload'].'">x2</span>
	';
	
	//if ($t['ts_external'] == 'yes' AND $ShowImage === true) $I[] = '<span id="isexternal_'.$t['id'].'"><a href="#showtorrent'.$t['id'].'" onclick="UpdateExternalTorrent(\'include/ts_external_scrape/ts_update.php\', \'id='.$t['id'].'&ajax_update=true\', '.$t['id'].')"><img src="'.$BASEURL.'/'.$pic_base_url.'external.gif" border="0" alt="'.$lang->browse['update'].'" title="'.$lang->browse['update'].'" /></a></span>';
	
	$canUpdateExternal= 'yes';
	
	//if ($t["ts_external"] == "yes" && $ShowImage === true) 
	//{
        //$I[] = "<span id=\"isexternal_" . $t["id"] . "\">" . ($canUpdateExternal ? "<a href=\"javascript:void(0)\" onclick=\"UpdateExternalTorrent('include/ts_external_scrape/ts_update.php', 'id=" . $t["id"] . "&ajax_update=true', " . $t["id"] . ")\"><img src=\"pic/external.gif\" border=\"0\" alt=\"" . $lang->browse["update"] . "\" title=\"" . $lang->browse["update"] . "\" class=\"inlineimg\" /></a>" : "<img src=\"" . $Imagedir . "external.gif\" border=\"0\" alt=\"" . $lang->browse["sortby15"] . "\" title=\"" . $lang->browse["sortby15"] . "\" class=\"inlineimg\" />") . "</span>";
    //}
	
	
	if ($t["ts_external"] == "yes" && $ShowImage === true) 
	{
        $I[] = "<span id=\"isexternal_" . $t["id"] . "\">
		" . ($canUpdateExternal ? "<a href=\"javascript:void(0)\" onclick=\"UpdateExternalTorrent('include/ts_external_scrape/ts_update.php', 'id=" . $t["id"] . "&ajax_update=true', " . $t["id"] . ")\">
		<i class=\"fa-solid fa-circle-notch\" style=\"color: #0b59e0;\" data-toggle=\"tooltip\" data-placement=\"top\" alt=\"" . $lang->browse["update"] . "\" title=\"" . $lang->browse["update"] . "\"></i>
		
		
		</a>" : "<img src=\"" . $Imagedir . "external.gif\" border=\"0\" alt=\"" . $lang->browse["sortby15"] . "\" title=\"" . $lang->browse["sortby15"] . "\" class=\"inlineimg\" />") . "</span>";
    }
	
	
	
	if ($t['sticky'] == 'yes') $I[] = 
	
	'<i class="fa-solid fa-bolt fa-lg" style="color: #0e5ce1;" alt="'.$lang->browse['stickya'].'" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['stickya'].'" /></i>';
	
	
	return (count($I) ? implode(' ', $I) : '');
}
?>