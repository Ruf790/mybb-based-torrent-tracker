<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function print_download_error ($messsage = '')
  {
    global $action_type;
    if (((!$action_type OR $action_type == '') OR $action_type != 'rss'))
    {
      print_no_permission (true);
      return null;
    }

    exit ($message);
  }

  require_once 'global.php';
  define ('DL_VERSION', '2.4.6');
  
  $lang->load ('download');
  
  $action_type = (isset ($_GET['type']) ? $_GET['type'] : '');
  //require INC_PATH . '/readconfig_download.php';
  if ($action_type == 'rss')
  {
    define ('SKIP_LOCATION_SAVE', true);

    $secret_key = (isset ($_GET['secret_key']) ? htmlspecialchars ($_GET['secret_key']) : '');
    if ((empty ($secret_key) OR strlen ($secret_key) != 32))
    {
      print_download_error ();
    }

    if ((empty ($_GET['id']) OR !is_valid_id ($_GET['id'])))
    {
      print_download_error ();
    }

    $ip = getip ();
    require_once INC_PATH . '/functions_isipbanned.php';
    if (isipbanned ($ip))
    {
      print_download_error ();
    }

    $res = $db->sql_query ('SELECT * FROM users WHERE passkey=' . $db->sqlesc ($secret_key) . ' LIMIT 1');
	
    if ($db->num_rows ($res) == 0)
    {
      print_download_error ();
    }

    $row = mysqli_fetch_assoc ($res);
    require TSDIR . '/cache/usergroups.php';
    $group_data_results = $usergroupscache[$row['usergroup']];
    $GLOBALS['usergroups'] = $group_data_results;
    $GLOBALS['CURUSER'] = $row;
    if ((($group_data_results['isbannedgroup'] == '1' OR $row['enabled'] != 'yes') OR $row['status'] != 'confirmed'))
    {
      unset ($GLOBALS[CURUSER]);
      unset ($GLOBALS[usergroups]);
      unset ($group_data_results);
      unset ($usergroupscache);
      print_download_error ();
    }

    unset ($row);
    unset ($group_data_results);
    unset ($usergroupscache);
  }
  else
  {
 
    
    maxsysop ();
    //parked ();
  }

  @ini_set ('zlib.output_compression', 'Off');
  @set_time_limit (0);
  if ((@ini_get ('output_handler') == 'ob_gzhandler' AND @ob_get_length () !== false))
  {
    @ob_end_clean ();
    @header ('Content-Encoding:');
  }

  $id = (isset ($_GET['id']) ? intval ($_GET['id']) : (isset ($_POST['id']) ? intval ($_POST['id']) : 0));
  if (!is_valid_id ($id))
  {
    print_download_error ();
  }

  $gigs = $CURUSER['downloaded'] / (1024 * 1024 * 1024);
  $ratio = (0 < $CURUSER['downloaded'] ? $CURUSER['uploaded'] / $CURUSER['downloaded'] : 0);
  $is_mod = is_mod ($usergroups);
  ($res = $db->sql_query ('SELECT t.id, t.name, t.filename, t.ts_external, t.size, t.owner, t.free FROM torrents t LEFT JOIN categories c ON t.category = c.id WHERE t.id = ' . $db->sqlesc ($id)));
  $row = mysqli_fetch_assoc ($res);
  ($query = $db->sql_query ('SELECT candownload FROM ts_u_perm WHERE userid = ' . $db->sqlesc ($CURUSER['id'])));
  if (0 < $db->num_rows ($query))
  {
    $downperm = mysqli_fetch_assoc ($query);
    if ($downperm['candownload'] == '0')
    {
      print_download_error ();
    }
  }

  //if (($usergroups['candownload'] != 'yes' OR ((((((($hitrun_gig < $gigs AND $ratio <= $hitrun_ratio) AND $CURUSER['downloaded'] != 0) AND !$is_mod) AND $hitrun == 'yes') AND $usergroups['isvipgroup'] != 'yes') AND $row['owner'] != $CURUSER['id']) AND $row['free'] != 'yes')))
  //{
  //  print_download_error ();
  //}
  
  

$xbt_active = "no";
  
  $ratio = 0 < $CURUSER["downloaded"] ? $CURUSER["uploaded"] / $CURUSER["downloaded"] : 0;
  //if ($usergroups["candownload"] != "yes" || $ratio <= $hitrun_ratio && $CURUSER["downloaded"] != 0 && !$is_mod && $hitrun == "yes" && $usergroups["isvipgroup"] != "yes" && $row["owner"] != $CURUSER["id"] && $row["free"] != "yes") 
  if ($ratio <= $hitrun_ratio && $CURUSER["downloaded"] != 0 && !$is_mod && $hitrun == "yes" && $usergroups["isvipgroup"] != "yes" && $row["owner"] != $CURUSER["id"] && $row["free"] != "yes") 
  {
    
    $has = $db->num_rows($db->sql_query("SELECT torrentid FROM snatched WHERE torrentid = " . $db->sqlesc($id) . " AND userid = " . $db->sqlesc($CURUSER["id"]) . " AND finished = \"yes\""));
    
    if (!$has) 
	{
        $percentage = $ratio * 100;
        $warning_message = 
		
		stderr(sprintf($lang->download["downloadwarning"], number_format($ratio, 2), mksize($percentage), $hitrun_ratio, 
		"<a href=\"" . $BASEURL . "/" . ($xbt_active == "yes" ? "mysnatchlist" : "userdetails") . ".php\">" . $BASEURL . "/" . ($xbt_active == "yes" ? "mysnatchlist" : "userdetails") . ".php</a>"), true);
		
		
        stdhead();
        exit($warning_message);
    }
  }
  
  

  $external = ($row['ts_external'] == 'yes' ? true : false);
  //if (($usergroups['canviewviptorrents'] != 'yes' AND $row['vip'] == 'yes'))
  //{
   // print_download_error ();
  //}

  
  $id = intval ($row['id']);
  $fn = $torrent_dir . '/' . $id . '.torrent';
  if (!$row)
  {
    print_download_error ($lang->download['error1']);
  }
  else
  {
    if (!is_file ($fn))
    {
      print_download_error ($lang->download['error2']);
    }
    else
    {
      if (!is_readable ($fn))
      {
        print_download_error ($lang->download['error3']);
      }
    }
  }


  
  $update_array['hits'] = 'hits+1';
  $db->update_query("torrents", $update_array, "id='{$id}'", 1, true);
  
  
  
  
 
  
  require_once __DIR__ . '/vendor/autoload.php'; // Подкорректируй путь к autoload

  use Arokettu\Torrent\TorrentFile;
  use Arokettu\Bencode\Bencode;

  // Загружаем торрент из файла
  $torrentFileObj = TorrentFile::load($fn);
  
  // Если торрент не внешний, то подменяем announce URL
  if (!$external) 
  {
    $AnnounceURL = ts_seo($CURUSER['passkey'], $row['filename'], "a");
    
	// Устанавливаем только один announce URL
    $torrentFileObj->setAnnounce($AnnounceURL);

  }
  
  $TorrentContents = $torrentFileObj->storeToString();



  
  if (($usezip != 'yes' OR $action_type == 'rss'))
  {
    require_once INC_PATH . '/functions_browser.php';
    if (is_browser ('ie'))
    {
      header ('Pragma: public');
      header ('Expires: 0');
      header ('Cache-Control: must-revalidate, post-check=0, pre-check=0');
      header ('Content-Disposition: attachment; filename=' . basename ($row['filename']) . ';');
      header ('Content-Transfer-Encoding: binary');
    }
    else
    {
      header ('Expires: Tue, 1 Jan 1980 00:00:00 GMT');
      header ('Last-Modified: ' . gmdate ('D, d M Y H:i:s') . ' GMT');
      header ('Cache-Control: no-store, no-cache, must-revalidate');
      header ('Cache-Control: post-check=0, pre-check=0', false);
      header ('Pragma: no-cache');
      header ('X-Powered-By: ' . VERSION . ' (c) ' . date ('Y') . ' ' . $SITENAME . '');
      header ('Accept-Ranges: bytes');
      header ('Connection: close');
      header ('Content-Transfer-Encoding: binary');
      header ('Content-Type: application/x-bittorrent');
      header ('Content-Disposition: attachment; filename=' . basename ($row['filename']) . ';');
    }

    ob_implicit_flush (true);
    print $TorrentContents;
    return 1;
  }

  require_once INC_PATH . '/class_zip.php';
  $createZip = new createZip ();
  $fileContents2 = 'This torrent was downloaded from ' . $BASEURL;
  $createZip->addFile ($fileContents2, 'readme.txt');
  $createZip->addFile (benc ($dict), $row['filename']);
  $fileName = $row['filename'] . '.zip';
  $fd = fopen ('cache/' . $fileName, 'wb');
  $out = fwrite ($fd, $createZip->getZippedfile ());
  fclose ($fd);
  $createZip->forceDownload ('cache/' . $fileName);
?>
