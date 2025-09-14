<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/



  require_once INC_PATH . '/functions_multipage.php';


  function show__errors ()
  {
    global $_errors;
    global $lang;
    if (0 < count ($_errors))
    {
      $errors = implode ('<br />', $_errors);
      echo '
			<table class="main" border="1" cellspacing="0" cellpadding="5" width="100%">
			<tr>
				<td class="thead">
					' . $lang->global['error'] . '
				</td>
			</tr>
			<tr>
				<td>
					<font color="red">
						<strong>
							' . $errors . '
						</strong>
					</font>
				</td>
			</tr>
			</table>
			<br />
		';
    }

  }

  function get_torrent_flags ($torrents)
  {
    global $BASEURL;
    global $pic_base_url;
    global $lang;
    global $rootpath;
    $isfree = ($torrents['free'] == 'yes' ? 
	'<a href="#" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['freedownload'].'"><span class="badge bg-success">F</span></a>' : '');
	
    $issilver = ($torrents['silver'] == 'yes' ? 
	'<a href="#" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['silverdownload'].'"><span class="badge bg-secondary">S</span></a>' : '');
    
	
	$isrequest = ($torrents['isrequest'] == 'yes' ? 
	'
	<a href="#" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['requested'].'">
	<span class="badge bg-primary">R</span></a>' : '');
	
	
    $isnuked = ($torrents['isnuked'] == 'yes' ? '<img src="' . $BASEURL . '/' . $pic_base_url . 'isnuked.gif" class="inlineimg" alt="' . sprintf ($lang->browse['nuked'], $torrents['WhyNuked']) . '" title="' . sprintf ($lang->browse['nuked'], $torrents['WhyNuked']) . '" />' : '');
    $issticky = ($torrents['sticky'] == 'yes' ? '<img src="' . $BASEURL . '/' . $pic_base_url . 'sticky.png" alt="' . $lang->browse['sticky'] . '" title="' . $lang->browse['sticky'] . '" />' : '');
    $anonymous = ($torrents['anonymous'] == 'yes' ? 
	'
	
	
	
	<a href="#" data-toggle="tooltip" data-placement="top" title="Anonymous torrent"><span class="badge bg-danger">A</span></a>
	
	' : '');
	
	
    $isbanned = ($torrents['banned'] == 'yes' ? 
	
	'
	
	<a href="#" data-toggle="tooltip" data-placement="top" title="Banned torrent"><span class="badge bg-danger">B</span></a>
	
	' : '');
    $isexternal = (($torrents['ts_external'] == 'yes' AND $_GET['tsuid'] != $torrents['id']) ? 
	'<a onclick=\'ts_show("loading-layer")\' data-toggle="tooltip" data-placement="top" title="'.$lang->browse['update'].'" href=\'' . $BASEURL . '/include/ts_external_scrape/ts_update.php?id=' . intval ($torrents['id']) . '\'>
	
	
	
	
	
	<span class="badge bg-primary">E</span>
	
	
	</a>' : ((isset ($_GET['tsuid']) AND $_GET['tsuid'] == $torrents['id']) ? '<img src=\'' . $BASEURL . '/' . $pic_base_url . 'input_true.gif\' class=\'inlineimg\' border=\'0\' alt=\'' . $lang->browse['updated'] . '\' title=\'' . $lang->browse['updated'] . '\' />' : ''));
    
	
	
	$isvisible = ($torrents['visible'] == 'yes' ? 
	'
	
	<a href="#" data-toggle="tooltip" data-placement="top" title="Active Torrent"><span class="badge bg-success">AC</span></a>
	
	' : '<img src="' . $BASEURL . '/' . $pic_base_url . 'input_error.gif" class="inlineimg" alt="Dead Torrent" title="Dead Torrent" />');
	
	
    $isdoubleupload = ($torrents['doubleupload'] == 'yes' ? 
	'
	
	<a href="#" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['dupload'].'"><span class="badge bg-dark">x2</span></a>
	
	' : '');
	
	
    $isclosed = ($torrents['allowcomments'] == 'no' ? '<img src="' . $BASEURL . '/' . $pic_base_url . 'commentpos.gif" alt="Closed for Comment Posting" title="Closed for Comment Posting" class="inlineimg" />' : '');
    return '' . $isvisible . ' ' . $isfree . ' ' . $issilver . ' ' . $isrequest . ' ' . $isnuked . ' ' . $issticky . ' ' . $isexternal . ' ' . $anonymous . ' ' . $isbanned . ' ' . $isdoubleupload . ' ' . $isclosed;
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('MT_VERSION', 'v0.8 by xam');
  $do = (isset ($_POST['do']) ? htmlspecialchars ($_POST['do']) : (isset ($_GET['do']) ? htmlspecialchars ($_GET['do']) : ''));
  $_errors = array ();
  $browsecategory = (isset ($_GET['browsecategory']) ? intval ($_GET['browsecategory']) : (isset ($_POST['browsecategory']) ? intval ($_POST['browsecategory']) : ''));
  $searchword = (isset ($_GET['searchword']) ? $_GET['searchword'] : (isset ($_POST['searchword']) ? $_POST['searchword'] : ''));
  $searchtype = (isset ($_GET['searchtype']) ? $_GET['searchtype'] : (isset ($_POST['searchtype']) ? $_POST['searchtype'] : ''));
  if (($browsecategory != '' AND is_valid_id ($browsecategory)))
  {
    //$query = $db->sql_query ('' . 'SELECT type FROM categories WHERE id = ' . $browsecategory);
	
	$query = $db->simple_select('categories', 'type', "id = '{$browsecategory}'");
	
    if ($db->num_rows($query))
    {
      
	  $Result = $db->fetch_array($query);
      $cate_type = $Result["type"];
	  
	  
      if ($cate_type == 's')
      {
        $extraquery1 = '' . ' WHERE category = ' . $browsecategory;
        $extraquery2 = '' . ' WHERE t.category = ' . $browsecategory . ' ';
        $extralink = '' . 'browsecategory=' . $browsecategory . '&amp;';
      }
      else
      {
        
		$array_cats = [];
        $array_cats[] = $browsecategory;
		
		//$query = $db->sql_query ('' . 'SELECT id FROM categories WHERE pid = ' . $browsecategory);
		$query = $db->simple_select('categories', 'id', "pid = '{$browsecategory}'");
		
		
        while ($sub_cats = $db->fetch_array ($query))
        {
          $array_cats[] = $sub_cats['id'];
        }

        $extraquery1 = ' WHERE category IN (0,' . implode (',', $array_cats) . ')';
        $extraquery2 = ' WHERE t.category IN (0,' . implode (',', $array_cats) . ')';
        $extralink = '' . 'browsecategory=' . $browsecategory . '&amp;';
      }
    }
  }

  if ($searchword != '')
  {
    if ($extraquery1)
    {
      $extraquery1 .= ' AND (name LIKE ' . $db->sqlesc ('%' . $searchword . '%') . ')';
      $extraquery2 .= ' AND (t.name LIKE ' . $db->sqlesc ('%' . $searchword . '%') . ') ';
      $extralink .= 'searchword=' . htmlspecialchars_uni ($browsecategory) . '&amp;';
    }
    else
    {
      $extraquery1 .= ' WHERE (name LIKE ' . $db->sqlesc ('%' . $searchword . '%') . ')';
      $extraquery2 .= ' WHERE (t.name LIKE ' . $db->sqlesc ('%' . $searchword . '%') . ') ';
      $extralink .= 'searchword=' . htmlspecialchars_uni ($searchword) . '&amp;';
    }
  }

  if ($searchtype != '')
  {
    switch ($searchtype)
    {
      case 'deadonly':
      {
        if ($extraquery1)
        {
          $extraquery1 .= ' AND (visible = \'no\' OR (seeders=0 AND leechers=0))';
          $extraquery2 .= ' AND (t.visible = \'no\' OR (t.seeders=0 AND t.leechers=0))';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }
        else
        {
          $extraquery1 .= ' WHERE (visible = \'no\' OR (seeders=0 AND leechers=0))';
          $extraquery2 .= ' WHERE (t.visible = \'no\' OR (t.seeders=0 AND t.leechers=0)) ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }

        break;
      }

      case 'internal':
      {
        if ($extraquery1)
        {
          $extraquery1 .= ' AND ts_external = \'no\'';
          $extraquery2 .= ' AND ts_external = \'no\' ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }
        else
        {
          $extraquery1 .= ' WHERE ts_external = \'no\'';
          $extraquery2 .= ' WHERE ts_external = \'no\' ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }

        break;
      }

      case 'external':
      {
        if ($extraquery1)
        {
          $extraquery1 .= ' AND ts_external = \'yes\'';
          $extraquery2 .= ' AND ts_external = \'yes\' ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }
        else
        {
          $extraquery1 .= ' WHERE ts_external = \'yes\'';
          $extraquery2 .= ' WHERE ts_external = \'yes\' ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }

        break;
      }

      case 'silver':
      {
        if ($extraquery1)
        {
          $extraquery1 .= ' AND silver = \'yes\'';
          $extraquery2 .= ' AND silver = \'yes\' ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }
        else
        {
          $extraquery1 .= ' WHERE silver = \'yes\'';
          $extraquery2 .= ' WHERE silver = \'yes\' ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }

        break;
      }

      case 'free':
      {
        if ($extraquery1)
        {
          $extraquery1 .= ' AND free = \'yes\'';
          $extraquery2 .= ' AND free = \'yes\' ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }
        else
        {
          $extraquery1 .= ' WHERE free = \'yes\'';
          $extraquery2 .= ' WHERE free = \'yes\' ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }

        break;
      }

      case 'recommend':
      {
        if ($extraquery1)
        {
          $extraquery1 .= ' AND sticky = \'yes\'';
          $extraquery2 .= ' AND sticky = \'yes\' ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }
        else
        {
          $extraquery1 .= ' WHERE sticky = \'yes\'';
          $extraquery2 .= ' WHERE sticky = \'yes\' ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }

        break;
      }

      case 'doubleuploads':
      {
        if ($extraquery1)
        {
          $extraquery1 .= ' AND doubleupload = \'yes\'';
          $extraquery2 .= ' AND doubleupload = \'yes\' ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
          break;
        }
        else
        {
          $extraquery1 .= ' WHERE doubleupload = \'yes\'';
          $extraquery2 .= ' WHERE doubleupload = \'yes\' ';
          $extralink .= 'searchtype=' . htmlspecialchars_uni ($searchtype) . '&amp;';
        }
      }
    }
  }

  if ($do == 'update')
  {
    if (is_valid_id ($_POST['page']))
    {
      $page = $_GET['page'] = intval ($_POST['page']);
    }

    $torrentid = $_POST['torrentid'];
    $actiontype = $_POST['actiontype'];
    $category = intval ($_POST['category']);
    if (empty ($actiontype))
    {
      $_errors[] = 'Please select action type!';
    }
    else
    {
      if ((!is_array ($torrentid) OR count ($torrentid) < 1))
      {
        $_errors[] = 'Please select a torrent!';
      }
      else
      {
        $torrentids = implode (',', $torrentid);
        if ($torrentids)
        {
          switch ($actiontype)
          {
            case 'move':
            {
              if (is_valid_id ($category))
              {
                ($db->sql_query ('UPDATE torrents SET category = ' . $db->sqlesc ($category) . ('' . ' WHERE id IN (' . $torrentids . ')')));
              }
              else
              {
                $_errors[] = 'Invalid Category!';
              }

              break;
            }

            case 'delete':
            {
              require_once INC_PATH . '/functions_deletetorrent.php';
              foreach ($torrentid as $id)
              {
                deletetorrent ($id);
              }

              break;
            }

            case 'sticky':
            {
              ($db->sql_query ('' . 'UPDATE torrents SET sticky = IF(sticky = \'yes\', \'no\', \'yes\') WHERE id IN (0,' . $torrentids . ')'));
              break;
            }

            case 'free':
            {
              ($db->sql_query ('' . 'UPDATE torrents SET free = IF(free = \'yes\', \'no\', \'yes\') WHERE id IN (0,' . $torrentids . ')'));
              break;
            }

            case 'silver':
            {
              ($db->sql_query ('' . 'UPDATE torrents SET silver = IF(silver = \'yes\', \'no\', \'yes\') WHERE id IN (0,' . $torrentids . ')'));
              break;
            }

            case 'visible':
            {
              ($db->sql_query ('' . 'UPDATE torrents SET visible = IF(visible = \'yes\', \'no\', \'yes\') WHERE id IN (0,' . $torrentids . ')'));
              break;
            }

            case 'anonymous':
            {
              ($db->sql_query ('' . 'UPDATE torrents SET anonymous = IF(anonymous = \'yes\', \'no\', \'yes\') WHERE id IN (0,' . $torrentids . ')'));
              break;
            }

            case 'banned':
            {
              ($db->sql_query ('' . 'UPDATE torrents SET banned = IF(banned = \'yes\', \'no\', \'yes\') WHERE id IN (0,' . $torrentids . ')'));
              break;
            }

            case 'nuke':
            {
              ($db->sql_query ('' . 'UPDATE torrents SET isnuked = IF(isnuked = \'yes\', \'no\', \'yes\') WHERE id IN (0,' . $torrentids . ')'));
              break;
            }

            case 'doubleupload':
            {
              ($db->sql_query ('' . 'UPDATE torrents SET doubleupload = IF(doubleupload = \'yes\', \'no\', \'yes\') WHERE id IN (0,' . $torrentids . ')'));
              break;
            }

            case 'openclose':
            {
              ($db->sql_query ('' . 'UPDATE torrents SET allowcomments = IF(allowcomments = \'yes\', \'no\', \'yes\') WHERE id IN (0,' . $torrentids . ')'));
            }
          }

          if (($_POST['return'] == 'yes' AND !empty ($_POST['return_address'])))
          {
            $returnto = fix_url ($_POST['return_address']);
            redirect ($returnto);
            exit ();
          }
        }
        else
        {
          $_errors[] = 'I can not implode torrent ids!';
        }
      }
    }
  }

  stdhead ('Manage Torrents', true, 'supernote');
  show__errors ();

  echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css">';


  $what = $_GET['what'];
  if ((empty ($what) OR $what == 'asc'))
  {
    $what = 'desc';
  }
  else
  {
    $what = 'asc';
  }

  $orderby = 't.added ' . strtoupper ($what);
  $allowedlist = array ('name', 'owner', 'category', 'date');
  if ((isset ($_GET['orderby']) AND in_array ($_GET['orderby'], $allowedlist)))
  {
    $orderby = 't.' . $_GET['orderby'] . ' ' . strtoupper ($what);
    $link = 'orderby=' . htmlspecialchars ($_GET['orderby']) . '&amp;what=' . htmlspecialchars ($_GET['what']) . '&amp;';
  }
  
  
  
?> 
<script>
$(document).ready(function(){
  $('[data-toggle="tooltip"]').tooltip();   
});
</script>
<?
  
  
  

  $query = $db->sql_query ('' . 'SELECT * FROM torrents' . $extraquery1);
  $threadcount = $db->num_rows ($query);
  //$torrentsperpage = ($CURUSER['torrentsperpage'] != 0 ? intval ($CURUSER['torrentsperpage']) : $ts_perpage);
  ///list ($pagertop, $pagerbottom, $limit) = pager ($torrentsperpage, $count, $_this_script_ . '&amp;' . $link . $extralink);
  
  
 // $threadcount = $db->num_rows($countquery);

//list($pagertop, $pagerbottom, $limit) = pager($torrentsperpage, $count, $_SERVER['SCRIPT_NAME'].'?'.(is_array($Links) && count($Links) > 0 ? implode('&amp;', $Links) : '').'&amp;');

  // How many pages are there?
if(!$torrentsperpage || (int)$torrentsperpage < 1)
{
	$torrentsperpage = 20;
}

$perpage = $torrentsperpage;

if($mybb->input['page'] > 0)
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


$page_url = str_replace("{fid}", $fid, $_this_script_ . '&amp;' . $link . $extralink);
$multipage = multipage($threadcount, $perpage, $page, $page_url);
  
  
  require_once INC_PATH . '/functions_category.php';
  $catdropdown = ts_category_list ('category', $category, '<option value="0">Select Category</option>');
  $catdropdown2 = ts_category_list ('browsecategory', $browsecategory, '<option value="0">--select category--</option>');
  $searchtype_dropdown = '
<label>
<select class="form-control form-control-sm border" name="searchtype">
	<option value="0">--select search type--</option>
';
  foreach (array ('deadonly' => 'Show Dead Torrents', 'internal' => 'Show Internal Torrents', 'external' => 'Show External Torrents', 'silver' => 'Show Silver Torrents', 'free' => 'Show Free Torrents', 'recommend' => 'Show Recommend Torrents', 'doubleuploads' => 'Show x2 Torrents') as $valuename => $description)
  {
    $searchtype_dropdown .= '
	
	<option value="' . $valuename . '"' . ($searchtype == $valuename ? ' selected="selected"' : '') . '>' . $description . '</option>';
  }

  $searchtype_dropdown .= '
</select></label>';
  //_form_header_open_ ('Select Category');
 
 
 
 
 
 

echo '
<form method="post" action="' . $_this_script_ . '">
<div class="container mt-3 position-relative">
    
        Search Word(s): 
		<div class="input-group mb-3">
            
            <input type="text" class="form-control" name="searchword" id="torrent-search" autocomplete="off" value="' . htmlspecialchars_uni($searchword) . '">
            <div id="autocomplete-results" class="dropdown-menu"></div>
         
		   
        </div>
		
		&nbsp;&nbsp; ' . $catdropdown2 . '&nbsp;&nbsp;' . $searchtype_dropdown . '
         &nbsp;&nbsp;<input type="submit" class="btn btn-primary" value="Search">  
    
</div>
</form>
';

echo '<div class="container mt-3">' . $multipage . '</div>';

echo '
<form method="post" action="' . $_this_script_ . '" name="update">
<input type="hidden" name="do" value="update">
<input type="hidden" name="page" value="' . intval($_GET['page']) . '">
<input type="hidden" name="searchword" value="' . htmlspecialchars_uni($searchword) . '">
<input type="hidden" name="browsecategory" value="' . $browsecategory . '">

<div class="container mt-3">
<div class="card">
<table class="table table-hover">
<thead>
<tr>
    <th><a href="' . $_this_script_ . '&amp;orderby=name&amp;what=' . $what . (isset($_GET['page']) ? '&amp;page=' . intval($_GET['page']) : '') . '">Name</a></th>
    <th>Flags</th>
    <th><a href="' . $_this_script_ . '&amp;orderby=owner&amp;what=' . $what . (isset($_GET['page']) ? '&amp;page=' . intval($_GET['page']) : '') . '">Uploader</a></th>
    <th><a href="' . $_this_script_ . '&amp;orderby=category&amp;what=' . $what . (isset($_GET['page']) ? '&amp;page=' . intval($_GET['page']) : '') . '">Category</a></th>
    <th><a href="' . $_this_script_ . '&amp;orderby=added&amp;what=' . $what . (isset($_GET['page']) ? '&amp;page=' . intval($_GET['page']) : '') . '">Added</a></th>
    <th><input type="checkbox" class="form-check-input" checkall="group" onclick="return select_deselectAll(\'update\', this, \'group\');"></th>
</tr>
</thead>
<tbody>
';

$str = '';
$query = $db->sql_query("
    SELECT t.*, u.username, u.usergroup, c.name as categoryname, c.cat_desc
    FROM torrents t
    LEFT JOIN users u ON (t.owner = u.id)
    LEFT JOIN categories c ON (t.category = c.id)
    $extraquery2
    ORDER BY $orderby
    LIMIT $start, $perpage
");

while ($torrent = $db->fetch_array($query)) 
{
    $seolink3 = ts_seo($torrent['id'], $torrent['name'], 'd');
    $downloadinfo = sprintf($lang->browse['downloadinfo'], $torrent['name']);

    $flags = get_torrent_flags($torrent);
    $flags .= $torrent['sticky'] ? ' <span class="badge bg-warning text-dark">Sticky</span>' : '';
    $flags .= $torrent['free'] ? ' <span class="badge bg-success">Free</span>' : '';
    $flags .= $torrent['nuked'] ? ' <span class="badge bg-danger">Nuked</span>' : '';

    $str .= '
    <tr>
        <td>
		
            <a href="#" 
                data-bs-toggle="modal" data-bs-target="#manageTorrentModal"
				data-name="' . htmlspecialchars($torrent['name']) . '"
                data-id="' . $torrent['id'] . '"
                data-image="' . $torrent['t_image'] . '"
				data-infohash="'.$torrent['info_hash'].'"
                data-seeders="'.$torrent['seeders'].'"
                data-leechers="'.$torrent['leechers'].'"
                data-completed="'.$torrent['times_completed'].'">
                <i class="fa-solid fa-circle-user"></i> &nbsp;' . htmlspecialchars($torrent['name']) . '
            </a>
			
			
			
			
		

			
			
			
			
			
			
			
				
			
       

	   </td>
        <td>' . $flags . '</td>
        <td><a href="' . $BASEURL . '/' . get_profile_link($torrent['owner']) . '">' . format_name($torrent['username'], $torrent['usergroup']) . '</a></td>
        <td><a href="' . $BASEURL . '/browse.php?category=' . $torrent['category'] . '" title="' . $torrent['cat_desc'] . '">' . $torrent['categoryname'] . '</a></td>
        
        


       <td>
    <span class="small text-muted">
        <i class="bi bi-calendar me-1"></i> ' . my_datee($dateformat, $torrent['added']) . '
    </span><br>
    <span class="small text-muted">
        <i class="bi bi-clock me-1"></i> ' . my_datee($timeformat, $torrent['added']) . '
    </span>
</td>
















        <td align="center"><input type="checkbox" class="form-check-input" name="torrentid[]" value="' . $torrent['id'] . '" checkme="group"></td>
    </tr>';
}

echo $str;

echo '
<tr>
    <td colspan="6" align="center">
        <p>
            Select Action:
            <select class="form-select form-select-sm border pe-5 w-auto d-inline" name="actiontype" onchange="check_it(this)">
                <option value="0">Select action</option>
                <option value="move">Move selected torrents</option>
                <option value="delete">Delete selected torrents</option>
                <option value="sticky">Sticky/Unsticky</option>
                <option value="free">Free/NonFree</option>
                <option value="silver">Silver/NonSilver</option>
                <option value="visible">Visible/Invisible</option>
                <option value="anonymous">Anonymize/De-anonymize</option>
                <option value="banned">Ban/Unban</option>
                <option value="nuke">Nuke/Unnuke</option>
                <option value="doubleupload">Double Upload Y/N</option>
                <option value="openclose">Open/Close Comments</option>
            </select>
        </p>
        <p id="movetorrent" style="display:none;">Select Category: ' . $catdropdown . '</p>
        <p>
            <input type="submit" class="btn btn-primary" value="Do it">
            <input type="reset" class="btn btn-secondary" value="Reset">
        </p>
    </td>
</tr>
</tbody>
</table>
</div>
</div>
</form>

<div class="container mt-3">' . $multipage . '</div>
';
?>


<!-- MODAL -->
<div class="modal fade" id="manageTorrentModal" tabindex="-1" aria-labelledby="manageTorrentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="manageTorrentModalLabel">Manage Torrent</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="manageTorrentContent">
        <div class="text-center p-3">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (isset($_SESSION['action_success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toastHTML = `
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div class="toast show bg-success text-white">
                <div class="toast-header">
                    <strong class="me-auto">Success</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    <?= $_SESSION['action_success']; ?>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', toastHTML);
});
</script>
<?php unset($_SESSION['action_success']); endif; ?>







<style>
    #autocomplete-results {
      position: absolute;
      z-index: 1000;
      width: 100%;
      border: 1px solid #ccc;
      background-color: #fff;
      display: none;
    }
    #autocomplete-results.show {
      display: block;
    }
	
	
mark {
  background-color: #ffeb3b;
  color: inherit;
  padding: 0 2px;
  border-radius: 2px;
}
	
	

	
	
  </style>




<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('manageTorrentModal');
    const content = document.getElementById('manageTorrentContent');
    const baseurl = "<?= $BASEURL ?>";  // Make sure this works!

    
	function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Link copied to clipboard!');
        });
    }
    window.copyToClipboard = copyToClipboard;          
	
	
	
	

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const name = button.getAttribute('data-name');
        const id = button.getAttribute('data-id');
        const image = button.getAttribute('data-image');
        const infohash = button.getAttribute('data-infohash') || ''; // Add data-infohash to button if possible

        const imagePreview = image
            ? `<img src="${image}" class="img-fluid rounded shadow-sm" alt="Preview">`
            : '<div class="alert alert-secondary">No image preview</div>';

        // You should fetch these values dynamically if available:
        const seeders = button.getAttribute('data-seeders') || 'N/A';
        const leechers = button.getAttribute('data-leechers') || 'N/A';
        const completed = button.getAttribute('data-completed') || 'N/A';

        const html = `
            <h5><i class="fa-solid fa-magnet"></i> ${name}</h5>
            <div class="row g-3 mt-3">
                <div class="col-md-4">
                    ${imagePreview}
                    <div class="mt-3 text-center">
                        <a href="${baseurl}/download.php?id=${id}" class="btn btn-success btn-sm">
                            <i class="fa fa-download"></i> Download .torrent
                        </a>
                        <a href="magnet:?xt=urn:btih:${infohash}" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-magnet"></i> Magnet Link
                        </a>
                    </div>
                </div>
                <div class="col-md-8">
                    <ul class="list-group list-group-flush shadow-sm">
                        <li class="list-group-item"><a href="${baseurl}/details.php?id=${id}"><i class="fa fa-eye"></i> View Torrent</a></li>
                        <li class="list-group-item"><a href="${baseurl}/details.php?id=${id}#startcomments"><i class="fa fa-comments"></i> View Comments</a></li>
                        <li class="list-group-item"><a href="${baseurl}/upload.php?id=${id}"><i class="fa fa-edit"></i> Edit Torrent</a></li>
                        <li class="list-group-item"><a href="${baseurl}/admin/index.php?act=torrent_info&id=${id}"><i class="fa fa-info-circle"></i> Torrent Info</a></li>
                        <li class="list-group-item"><a href="${baseurl}/admin/index.php?act=viewstats&id=${id}"><i class="fa fa-chart-bar"></i> Torrent Stats</a></li>
                        <li class="list-group-item"><a href="${baseurl}/admin/index.php?act=nuketorrent&id=${id}" class="text-danger"><i class="fa fa-skull-crossbones"></i> Nuke Torrent</a></li>
                        <li class="list-group-item"><a href="${baseurl}/admin/index.php?act=fastdelete&id=${id}" class="text-danger"><i class="fa fa-trash"></i> Delete Torrent</a></li>
                    </ul>
                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-success"><i class="fa fa-seedling"></i> Seeders: ${seeders}</span>
                            <span class="badge bg-danger"><i class="fa fa-user-slash"></i> Leechers: ${leechers}</span>
                            <span class="badge bg-secondary"><i class="fa fa-arrow-down"></i> Completed: ${completed}</span>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm" onclick="copyToClipboard('${baseurl}/details.php?id=${id}')">
                            <i class="fa fa-copy"></i> Copy Link
                        </button>
                    </div>
                </div>
            </div>
        `;

        content.innerHTML = html;

        // Fetch extra torrent info if you want to override seeders/leechers/completed etc
        fetch(`${baseurl}/admin/torrent_extra.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                content.querySelector('.col-md-8').insertAdjacentHTML('beforeend', `
                    <hr>
                    <p><strong>Size:</strong> ${data.size}</p>
                    <p><strong>Files:</strong> ${data.file_count}</p>
                   
                `);
            });
    });

    
	
	
	
	// Live search functionality
$(document).ready(function () {
  const $input = $("#torrent-search");
  const $results = $("#autocomplete-results");

  let debounceTimer;

  $input.on("input", function () {
    const query = $(this).val().trim();

    clearTimeout(debounceTimer);
    if (query.length < 3) {
      $results.removeClass("show").empty();
      return;
    }

    debounceTimer = setTimeout(() => {
      $.ajax({
        url: "<?= $BASEURL ?>/search_torrents.php",
        dataType: "json",
        data: { input: query },
        success: function (data) {
          $results.empty();

          if (!Array.isArray(data) || data.length === 0) {
            $results.append('<a class="dropdown-item disabled">No results found</a>').addClass("show");
            return;
          }

         
	      data.forEach(item => {
  if (!item.name || !item.id) return;
  const img = item.image_url ? `<img src="${item.image_url}" alt="" style="width:40px;height:auto;margin-right:10px;">` : "";

  // Highlight match in name
  const regex = new RegExp("(" + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ")", "ig");
  const highlightedName = item.name.replace(regex, '<mark>$1</mark>');

  const $option = $(`<a class="dropdown-item d-flex align-items-center" href="${baseurl}/details.php?id=${item.id}">
                      ${img}<span>${highlightedName}</span>
                     </a>`);
  $results.append($option);
});
		  
		  
		  
		 
		  
		  
		  
		  
		  
		  
		  
		  
		  
		  

          $results.addClass("show");
        },
        error: function () {
          $results.html('<a class="dropdown-item disabled">Error retrieving results</a>').addClass("show");
        }
      });
    }, 300); // Debounce delay
  });

  // Hide dropdown when clicking outside
  $(document).on("click", function (e) {
    if (!$(e.target).closest("#torrent-search, #autocomplete-results").length) {
      $results.removeClass("show").empty();
    }
  });
});
	
	
	
	
	
	
	
	

    // Hover preview fix:
    let hoverPreviewDiv = null;
    document.querySelectorAll('.torrent-btn').forEach(btn => {
        btn.addEventListener('mouseenter', function () {
            const img = this.getAttribute('data-image');
            if (!img) return;

            if (hoverPreviewDiv) hoverPreviewDiv.remove();

            hoverPreviewDiv = document.createElement('div');
            hoverPreviewDiv.id = 'hoverPreview';
            hoverPreviewDiv.style.position = 'absolute';
            hoverPreviewDiv.style.zIndex = 9999;
            hoverPreviewDiv.style.top = (this.getBoundingClientRect().top + window.scrollY + 30) + 'px';
            hoverPreviewDiv.style.left = (this.getBoundingClientRect().left + window.scrollX + 30) + 'px';
            hoverPreviewDiv.innerHTML = `<img src="${img}" class="img-thumbnail" style="max-width:150px;">`;
            document.body.appendChild(hoverPreviewDiv);
        });

        btn.addEventListener('mouseleave', () => {
            if (hoverPreviewDiv) {
                hoverPreviewDiv.remove();
                hoverPreviewDiv = null;
            }
        });
    });

    // Show/hide category dropdown based on selected action (looks okay)
    const actionSelect = document.querySelector('select[name="actiontype"]');
    if(actionSelect) {
        actionSelect.addEventListener('change', function () {
            const moveBlock = document.getElementById('movetorrent');
            moveBlock.style.display = this.value === 'move' ? 'block' : 'none';
        });
    }
});

</script>




<?
stdfoot();
?>