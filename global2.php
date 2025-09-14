<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  if (!defined ('TSF_FORUMS_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('TSF_FORUMS_GLOBAL_TSSEv56', true);
  define ('TSF_VERSION', 'v1.5 by xam');
  //define ('IN_FORUMS', true );
  
  
  require_once('global.php');
  require_once INC_PATH . '/tsf_functions.php';
  
  
  include_once INC_PATH . '/functions_multipage.php';
  require_once INC_PATH . '/readconfig.php';
  
  
  include_once INC_PATH . '/class_parser.php';
  $parser = new postParser;
  
 

  $parser_options = array(
	"allow_html" => 1,
	"allow_mycode" => 1,
	"allow_smilies" => 1,
	"allow_imgcode" => 1,
	"allow_videocode" => 1,
	"filter_badwords" => 1
  );
  
  
  gzip ();
  maxsysop ();
  //parked ();
  
  
  $lang->load ('tsf_forums');
  
  if (!defined ('IN_SCRIPT_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  //require_once './include/init.php';
  if ((@function_exists ('mb_internal_encoding') AND !empty ($charset)))
  {
    @mb_internal_encoding ($charset);
  }

  //$navbits = array ();
  //$navbits[0]['name'] = $f_forumname;
  //$navbits[0]['url'] = '' . $BASEURL . '/index2.php';
  
  // Add our main parts to the navigation
  $navbits = array();
  $navbits[0]['name'] = $SITENAME;
  $navbits[0]['url'] = $BASEURL.'/index2.php';
  
  
  
  
  //$permissions = forum_permissions ();
  //if ((($usergroups['canstaffpanel'] == '1' OR $usergroups['cansettingspanel'] == '1') OR $usergroups['issupermod'] == '1'))
  //{
    //$moderator = true;
  //}
  //else
  //{
    //$moderator = false;
  //}

  //$action = (isset ($_GET['action']) ? htmlspecialchars_uni ($_GET['action']) : (isset ($_POST['action']) ? htmlspecialchars_uni ($_POST['action']) : ''));
  //$forumtokencode = md5 ($CURUSER['username'] . $securehash . $CURUSER['id']);
  //$posthash = (isset ($_POST['hash']) ? htmlspecialchars_uni ($_POST['hash']) : (isset ($_GET['hash']) ? htmlspecialchars_uni ($_GET['hash']) : ''));
  //$pagenumber = (isset ($_GET['page']) ? intval ($_GET['page']) : (isset ($_POST['page']) ? intval ($_POST['page']) : ''));
  //$perpage = $f_threadsperpage;
  unset ($warningmessage);
  
  
  //if ($usergroups['canviewboardclosed'] != '1')
  //if($SITEONLINE != 'yes' && $usergroups['canviewboardclosed'] == 1)
	 
  //{
    //stderr ($lang->global['error'], $f_offlinemsg);
    //exit ();
    //return 1;
  //}

  //if ($f_forum_online == 'no')
  //{
    //$warningmessage = show_notice ($lang->tsf_forums['warningmsg'], true);
  //}

?>
