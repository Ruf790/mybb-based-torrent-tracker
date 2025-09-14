<?

  require_once 'global.php';
  define ('CA_VERSION', '0.4 by xam');
 
  if (!$CURUSER)
  {
    exit ();
  }

  header ('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
  header ('Last-Modified: ' . gmdate ('D, d M Y H:i:s') . 'GMT');
  header ('Cache-Control: no-cache, must-revalidate');
  header ('Pragma: no-cache');
  header ('' . 'Content-type: text/html; charset=' . $shoutboxcharset);
  
  if (($CURUSER AND $CURUSER['announce_read'] == 'no'))
  {
   
	$update_read = array(
		"announce_read" => 'yes'						
    );
	
    $db->update_query("users", $update_read, "announce_read='no' AND id='{$CURUSER['id']}'");
	
  }

  //exit ($lang->clear_ann['cleared']);
  redirect('browse.php');
?>
