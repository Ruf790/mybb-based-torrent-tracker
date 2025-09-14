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

  define ('C_VERSION', '1.0 by xam');
  require $rootpath . 'ratings/includes/rating_functions.php';
  $rating = gettoprated (10, 'torrents', 'id', 'name');
  $rating .= '
<link rel="stylesheet" type="text/css" href="' . $BASEURL . '/ratings/css/rating_style.css?v=' . O_SCRIPT_VERSION . '" />
<script type="text/javascript" language="javascript" src="' . $BASEURL . '/ratings/js/rating_update.js?v=' . O_SCRIPT_VERSION . '"></script>';
  stdhead ('Top 10 Rated Torrents');
  _form_header_open_ ('Top 10 Rated Torrents');
  echo '<tr><td>' . $rating . '</td></tr>';
  _form_header_close_ ();
  stdfoot ();
?>
