<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function show_msg ($message = '', $error = false)
  {
    global $shoutboxcharset;
    header ('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
    header ('Last-Modified: ' . gmdate ('D, d M Y H:i:s') . 'GMT');
    header ('Cache-Control: no-cache, must-revalidate');
    header ('Pragma: no-cache');
    header ('' . 'Content-type: text/html; charset=' . $shoutboxcharset);
    if ($error)
    {
      exit ('<error>' . $message . '</error>');
    }

    exit ($message);
  }

  define ('SKIP_LOCATION_SAVE', true);
  define ('DEBUGMODE', false);
  require 'global.php';
  gzip ();

  define ('TS_AJAX_VERSION', '1.2.0 ');
  define ('TU_VERSION', '2.6.6 ');
  if ((((!defined ('IN_SCRIPT_TSSEv56') OR strtoupper ($_SERVER['REQUEST_METHOD']) != 'POST') OR !$CURUSER) OR !is_mod ($usergroups)))
  {
    exit ();
  }

  if ((!isset ($_POST['tid']) OR !is_valid_id ($_POST['tid'])))
  {
    show_msg ($lang->global['notorrentid'], true);
  }

  
  
  $id = (int)$_POST['tid']; // кастим к int для безопасности

  $sql = "SELECT t_link FROM torrents WHERE id = ?";
  $params = [$id];

  $Query = $db->sql_query_prepared($sql, $params);

  
  
  
  if ($db->num_rows ($Query) == 0)
  {
    show_msg ($lang->global['notorrentid'], true);
  }

  
  $Result = $db->fetch_array($Query);
  $oldt_link = $Result["t_link"];

  if (!$oldt_link)
  {
    show_msg ($lang->global['notorrentid'], true);
  }

  preg_match ('@<a href=\'(.*)\'@U', $oldt_link, $imdblink);
  $t_link = $imdblink[1];
  if ($t_link)
  {
    include_once INC_PATH . '/ts_imdb.php';
    if ($t_link)
    {
      
	  
	  $sql = "UPDATE torrents SET t_link = ? WHERE id = ?";
      $params = [$t_link, (int)$id];

      $db->sql_query_prepared($sql, $params);
	  
	  
      require_once INC_PATH . '/functions_imdb_rating.php';
      if ($IMDBRating = tssegetimdbratingimage ($t_link))
      {
        $t_link = str_replace ('<b>User Rating:</b>', '<b>User Rating:</b> ' . $IMDBRating['image'], $t_link);
      }

      show_msg ($t_link);
    }
  }

?>
