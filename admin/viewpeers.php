<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }
  
  
  
function getagent($httpagent='', $peer_id="")
{
		global $lang;
		return ($httpagent ? $httpagent : ($peer_id ? $peer_id : $lang->global['unknown']));
}

function htmlsafechars($txt='') {

  $txt = preg_replace("/&(?!#[0-9]+;)(?:amp;)?/s", '&amp;', $txt );
  $txt = str_replace( array("<",">",'"',"'"), array("&lt;", "&gt;", "&quot;", '&#039;'), $txt );

  return $txt;
}
  
  

  define ('VP_VERSION', '0.2 by xam');
  stdhead ('Peerlist');
  
  
  
  
  
  
  require_once INC_PATH . '/functions_multipage.php';
  
  
  
?> 
<script>
$(document).ready(function(){
  $('[data-toggle="tooltip"]').tooltip();   
});
</script>
<?

  
  
  
  ($res4 = $db->sql_query ('SELECT COUNT(*) FROM peers'));
  $row11 = mysqli_fetch_array ($res4);
  $count = $row11[0];

 
 
  $threadcount = $count;

   
  
  echo '
  
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Peerlist - <font class=small>We have ' . $count . ' peers</font>
	</div>
	 </div>
		</div>';
  
  
  
  
  
  
  // How many pages are there?
if(!$ts_perpage || (int)$ts_perpage < 1)
{
	$ts_perpage = 20;
}

$perpage = $ts_perpage;

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


$multipage = multipage($threadcount, $perpage, $page, $_this_script_ . '&');
  
  
  $sql = '' . 'SELECT p.*, t.name, u.username, u.usergroup
		FROM peers p 
		LEFT JOIN torrents t ON (p.torrent=t.id)
		LEFT JOIN users u ON (p.userid=u.id)
		ORDER BY p.started DESC LIMIT '.$start.','.$perpage.'';
  ($result = $db->sql_query ($sql));
  if ($db->num_rows ($result) != 0)
  {
   
   
   print '
	<div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Us</th>
        <th>Tort</th>
        <th>IP-P</th>
		<th>Upl</th>
        <th>Dow</th>
        <th>P-ID</th>
		<th>Con</th>
		<th>Status</th>
		<th>Start</th>
		<th>L<br />Act</th>
		<th>P.<br />Act</th>
		<th>Upl<br />Off</th>
		<th>Dow<br />Off</th>
		<th>To<br />Go</th>
      </tr>
    </thead>';
   
   
   
   
   
   
   
    while ($row = mysqli_fetch_array ($result))
    {
      print '<tr>';
      print '<td class="trow2"><a href="' . $BASEURL . '/' . get_profile_link($row['userid']) . '">' . format_name(htmlspecialchars_uni ($row['username']), $row['usergroup']) . '</a></td>';
      print '<td class="trow2">
	  
	  
	   
	  
	  	<a href="' . $BASEURL . '/' . get_torrent_link($row['torrent']) . '" data-toggle="tooltip" data-placement="top" title="' . htmlspecialchars_uni ($row['name']) . '">
		' . htmlspecialchars_uni (cutename ($row['name'], 5)) . '</a>
	
	  
	  
	  </td>';
      print '<td align=center class="trow2">' . htmlspecialchars_uni ($row['ip']) . '<br />' . htmlspecialchars_uni ($row['port']) . '</td>';
      if ($row['uploaded'] < $row['downloaded'])
      {
        print '<td align=center class="trow2"><font color=red>' . mksize ($row['uploaded']) . '</font></td>';
      }
      else
      {
        if ($row['uploaded'] == '0')
        {
          print '<td align=center class="trow2">' . mksize ($row['uploaded']) . '</td>';
        }
        else
        {
          print '<td align=center class="trow2"><font color=green>' . mksize ($row['uploaded']) . '</font></td>';
        }
      }

      print '<td align=center class="trow2">' . mksize ($row['downloaded']) . '</td>';
      print '<td align=center class="trow2">' . htmlsafechars(getagent($row["agent"], $row['peer_id'])) . '</td>';
      if ($row['connectable'] == 'yes')
      {
        print '<td align=center class="trow2"><span class="badge bg-success" alt="seed" title="seed">' . $row['connectable'] . '</span></td>';
      }
      else
      {
        print '<td align=center class="trow2"><span class="badge bg-danger" alt="leech" title="leech" >' . $row['connectable'] . '</span></td>';
      }

      if ($row['seeder'] == 'yes')
      {
        print '<td align=center class="trow2">
		
		<span class="badge bg-success" alt="seed" title="seed">seed</span></td>
		';
      }
      else
      {
        print '<td align=center class="trow2">
		
		<span class="badge bg-danger" alt="leech" title="leech" >leech</span>
		</td>';
      }

      print '<td align=center class="trow2">' . my_datee ($dateformat, $row['started']) . '<br />' . my_datee ($timeformat, $row['started']) . '</td>';
      print '<td align=center class="trow2">' . my_datee ($dateformat, $row['last_action']) . '<br />' . my_datee ($timeformat, $row['last_action']) . '</td>';
      print '<td align=center class="trow2">' . my_datee ($dateformat, $row['prev_action']) . '<br />' . my_datee ($timeformat, $row['prev_action']) . '</td>';
      print '<td align=center class="trow2">' . mksize ($row['uploadoffset']) . '</td>';
      print '<td align=center class="trow2">' . mksize ($row['downloadoffset']) . '</td>';
      print '<td align=center class="trow2">' . mksize ($row['to_go']) . '</td>';
      print '</tr>';
    }

    //print '</div>';
    print $multipage;
  }
  else
  {
    print 'Nothing here!';
  }

  print '</td></tr></table></div></div>';
  
  
  stdfoot ();
?>
