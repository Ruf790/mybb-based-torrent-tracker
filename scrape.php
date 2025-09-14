<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


 
  if (!($db = mysqli_connect ('localhost', 'user', 'pass','dbname')))
  {
      exit ('Error: Mysql Connection!');
      ;
  }


  function hash_where($hash) 
  {
    global $db;
    return "info_hash = '" . mysqli_real_escape_string ($db, bin2hex($hash) ) . "'";

  }


  //@error_reporting (E_ALL & ~E_NOTICE);
  //@ini_set ('error_reporting', E_ALL & ~E_NOTICE);
  //@ini_set ('display_errors', '0');
  define ('S_VERSION', '0.6 ');
  if (!isset ($_GET['info_hash']))
  {
    exit ('Permission denied!');
  }


  $r = 'd5:files';

  if (!($res = mysqli_query ($db,'SELECT info_hash,seeders,times_completed,leechers FROM torrents WHERE ' . hash_where ($_GET['info_hash']) . ' LIMIT 1')))
  {
    exit ('Mysql error!');
    ;
  }

  while ($row = mysqli_fetch_assoc ($res))
  {  
     $r .= 'd20:'.pack('H*', $row['info_hash'])."d8:completei{$row['seeders']}e10:downloadedi{$row['times_completed']}e10:incompletei{$row['leechers']}eeee";
  }
  $r .= 'ee';
  header ('Content-Type: text/plain');
  if ((isset ($_SERVER['HTTP_ACCEPT_ENCODING']) AND $_SERVER['HTTP_ACCEPT_ENCODING'] == 'gzip'))
  {
    header ('Content-Encoding: gzip');
    echo gzencode ($x, 9, FORCE_GZIP);
  }
  else
  {
    echo $r;
  }

  unset ($r);
?>
