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
  
  function get_torrent_flags ($torrents)
  {
    global $BASEURL;
    global $pic_base_url;
    global $lang;
    global $rootpath;
    $lang->load ('browse');
    $isfree = ($torrents['free'] == 'yes' ? '
	
	
	<span class="badge bg-success" data-toggle="tooltip" data-placement="top" alt="'.$lang->browse['freedownload'].'" title="'.$lang->browse['freedownload'].'">F</span>
	
	
	
	' : '');
    $issilver = ($torrents['silver'] == 'yes' ? '
	
	<span class="badge bg-secondary" data-toggle="tooltip" data-placement="top" alt="'.$lang->browse['silverdownload'].'" title="'.$lang->browse['silverdownload'].'">S</span>
	
	
	' : '');
    $isrequest = ($torrents['isrequest'] == 'yes' ? '
	
	<span class="badge bg-primary" data-toggle="tooltip" data-placement="top" alt="'.$lang->browse['requested'].'" title="'.$lang->browse['requested'].'">R</span>
	
	
	' : '');
    $isnuked = ($torrents['isnuked'] == 'yes' ? '<img src="' . $BASEURL . '/' . $pic_base_url . 'isnuked.gif" class="inlineimg" alt="' . sprintf ($lang->browse['nuked'], $torrents['WhyNuked']) . '" title="' . sprintf ($lang->browse['nuked'], $torrents['WhyNuked']) . '" />' : '');
    $issticky = ($torrents['sticky'] == 'yes' ? '<img src="' . $BASEURL . '/' . $pic_base_url . 'sticky.gif" alt="' . $lang->browse['sticky'] . '" title="' . $lang->browse['sticky'] . '" />' : '');
    $anonymous = ($torrents['anonymous'] == 'yes' ? '
	
	
	
		
	<span class="badge bg-danger" data-toggle="tooltip" data-placement="top" alt="Anonymous torrent" title="Anonymous torrent" >A</span>
	
	
	
	' : '');
    $isbanned = ($torrents['banned'] == 'yes' ? '<img src="' . $BASEURL . '/' . $pic_base_url . 'disabled.gif" alt="Banned torrent" title="Banned torrent" />' : '');
    $isexternal = (($torrents['ts_external'] == 'yes' AND $_GET['tsuid'] != $torrents['id']) ? '<a onclick=\'ts_show("loading-layer")\' href=\'' . $BASEURL . '/include/ts_external_scrape/ts_update.php?id=' . intval ($torrents['id']) . '\'><img src=\'' . $BASEURL . '/' . $pic_base_url . 'external.gif\' class=\'inlineimg\'  border=\'0\' alt=\'' . $lang->browse['update'] . '\' title=\'' . $lang->browse['update'] . '\' /></a>' : ((isset ($_GET['tsuid']) AND $_GET['tsuid'] == $torrents['id']) ? '<img src=\'' . $BASEURL . '/' . $pic_base_url . 'input_true.gif\' class=\'inlineimg\' border=\'0\' alt=\'' . $lang->browse['updated'] . '\' title=\'' . $lang->browse['updated'] . '\' />' : ''));
    $isvisible = ($torrents['visible'] == 'yes' ? '
	
	
	
	<span class="badge bg-success" data-toggle="tooltip" data-placement="top" alt="Active Torrent" title="Active Torrent">AC</span>
	
	
	' : '<img src="' . $BASEURL . '/' . $pic_base_url . 'input_error.gif" class="inlineimg" alt="Dead Torrent" title="Dead Torrent" />');
    $isdoubleupload = ($torrents['doubleupload'] == 'yes' ? '
	
	<span class="badge bg-dark" data-toggle="tooltip" data-placement="top" alt="'.$lang->browse['dupload'].'" title="'.$lang->browse['dupload'].'">x2</span>
	
	
	' : '');
    $isclosed = ($torrents['allowcomments'] == 'no' ? '<img src="' . $BASEURL . '/' . $pic_base_url . 'commentpos.gif" alt="Closed for Comment Posting" title="Closed for Comment Posting" class="inlineimg" />' : '');
    return '' . $isvisible . ' ' . $isfree . ' ' . $issilver . ' ' . $isrequest . ' ' . $isnuked . ' ' . $issticky . ' ' . $isexternal . ' ' . $anonymous . ' ' . $isbanned . ' ' . $isdoubleupload . ' ' . $isclosed;
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('AU_VERSION', '0.6 by xam');
  $do = (isset ($_GET['do']) ? $_GET['do'] : (isset ($_POST['do']) ? $_POST['do'] : ''));
 
  
 
 
 $sql = "SELECT s.port, s.ip, s.last_action, s.startdat, s.agent, s.userid, s.uploaded, s.downloaded, s.torrentid,
               t.seeders, t.leechers, t.name,
               u.downloaded AS usercurrentdownload, u.uploaded AS usercurrentupload, u.username,
               g.namestyle, g.title
        FROM snatched s
        LEFT JOIN torrents t ON (s.torrentid = t.id)
        INNER JOIN users u ON (u.id = s.userid)
        LEFT JOIN usergroups g ON (u.usergroup = g.gid)
        WHERE s.downloaded = 0
          AND s.uploaded > 0
          AND s.leechtime = 0
          AND u.enabled = 'yes'
          AND u.usergroup NOT IN (?)";

$params = [UC_BANNED];

$query = $db->sql_query_prepared($sql, $params);

  
  
  
  
  
   $threadcount = $db->num_rows ($query);
  
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


$page_url = str_replace("{fid}", $fid, $_this_script_ . '&amp;');
$multipage = multipage($threadcount, $perpage, $page, $page_url);
  
  
  
   $sql = "SELECT s.port, s.ip, s.last_action, s.startdat, s.agent, s.userid, s.uploaded, s.downloaded, s.torrentid,
               t.name, t.free, t.silver, t.isrequest, t.isnuked, t.sticky, t.anonymous, t.banned, t.ts_external, 
               t.visible, t.doubleupload, t.allowcomments, t.seeders, t.leechers, 
               u.downloaded as usercurrentdownload, u.uploaded as usercurrentupload, u.username, u.usergroup,
               u.added, u.avatar, u.lastactive, u.enabled, u.donor, u.leechwarn, u.warned, 
               p.canupload, p.candownload, p.cancomment, 
               g.namestyle, g.title
        FROM snatched s
        LEFT JOIN torrents t ON (s.torrentid = t.id)
        INNER JOIN users u ON (u.id = s.userid)
        LEFT JOIN ts_u_perm p ON (u.id = p.userid)
        LEFT JOIN usergroups g ON (u.usergroup = g.gid)
        WHERE s.downloaded = 0 
          AND s.uploaded > 0 
          AND s.leechtime = 0 
          AND u.enabled = 'yes' 
          AND u.usergroup NOT IN (?)
        ORDER BY u.username 
        LIMIT ?, ?";

  $params = [UC_BANNED, (int)$start, (int)$perpage];

  $query = $db->sql_query_prepared($sql, $params);

  
  
  $lang->load ('tsf_forums');
  include_once INC_PATH . '/functions_icons.php';
  include_once INC_PATH . '/functions_ratio.php';
  stdhead ();
  echo $multipage;
  
  
  
  
?> 
<script>
$(document).ready(function(){
  $('[data-toggle="tooltip"]').tooltip();   
});
</script>
<?


  echo '
  
   <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		TS Detect Cheaters (Columns with a <span class="highlight"><font color="black">different background color</font></span> means: 69% Cheat!)
	</div>
	 </div>
		</div>';

  
  
  echo '


<div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Username</th>
        <th>Torrent Name</th>
        <th>Uploaded/Port</th>
		<th>Agent/IP</th>
        <th>Time</th>
   
      </tr>
    </thead>';







  while ($s = $db->fetch_array ($query))
  {
    $sticky = (($s['usercurrentdownload'] == 0 AND $s['free'] == 'no') ? true : false);
    $lastseen = my_datee ($dateformat, $s['lastactive']) . ' ' . my_datee ($timeformat, $s['lastactive']);
    
	
	
	// Новый безопасный вариант:
$downloaded = (int)$s['usercurrentdownload']; // в байтах
$uploaded   = (int)$s['usercurrentupload'];   // в байтах

// вычисляем ratio
if ($downloaded > 0) {
    $ratio = $uploaded / $downloaded;
    $ratio = number_format($ratio, 2);
} else {
    $ratio = ($uploaded > 0 ? '∞' : '0.00'); // защита от деления на ноль
}

// для отображения в таблице используем mksize()
$downloaded_h = mksize($downloaded);
$uploaded_h   = mksize($uploaded);
	

	
	
    $tooltip = '' . $lang->tsf_forums['jdate'] . '' . my_datee ($dateformat, $s['added']) . '' . sprintf ($lang->tsf_forums['tooltip'], $lastseen, $downloaded_h, $uploaded_h, $ratio);
    
	$profilelink_plain22 = $BASEURL.'/'.get_profile_link($s['userid']);
	
	
	echo '
	<tr' . ($sticky ? ' class="highlight"' : '') . '>
		<td>
		
		
		
		
		<a href="' . $profilelink_plain22 . '" data-toggle="tooltip" data-placement="top" title="'.$tooltip.'">' . format_name($s['username'], $s['usergroup']) . '</a>
	
		
		
		
		<br />' . $s['title'] . '</td>
		
		<td><a href="' . $BASEURL . '/'.get_torrent_link($s['torrentid']) . '" target="_blank">' . cutename ($s['name'], 80) . '</a> ' . get_torrent_flags ($s) . '<br />
		<b>Seeders:</b> ' . ts_nf ($s['seeders']) . ' / <b>Leechers:</b> ' . ts_nf ($s['leechers']) . '</td>
		<td>' . mksize ($s['uploaded']) . '<br /><b>Port:</b> ' . intval ($s['port']) . '</td>
		<td>' . htmlspecialchars_uni ($s['agent']) . '<br /><b>IP</b>: ' . htmlspecialchars_uni ($s['ip']) . '</td>
		<td><b>Started at:</b> ' . my_datee ($dateformat, $s['startdat']) . ' ' . my_datee ($timeformat, $s['startdat']) . '<br />
		<b>Last Action:</b> ' . my_datee ($dateformat, $s['last_action']) . ' ' . my_datee ($timeformat, $s['last_action']) . '</td>
	</tr>
	';
  }


 
 echo '</table></div></div>';
 
  echo $multipage;
  stdfoot ();
?>
