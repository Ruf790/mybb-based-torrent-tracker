<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  define ('IN_TRACKER', true);
  define ('IN_SCRIPT_TSSEv56', true);
  define ('O_SCRIPT_VERSION', '5.6');
  define ('TIMENOW', time ());
  //define("TIMENOW", time());
  define ('TSDIR', dirname (__FILE__));
  define ('INC_PATH', TSDIR . '/include');
  define ('CONFIG_DIR', TSDIR . '/config');
  $rootpath = (isset ($rootpath) ? $rootpath : TSDIR);
  if (!defined ('DEBUGMODE'))
  {
    $GLOBALS['ts_start_time'] = array_sum (explode (' ', microtime ()));
  }
  
  
# IMPORTANT CONSTANTS - DO NOT CHANGE!
define ('VERSION','');
define ('TS_MESSAGE','Powered by');
define ('TSF_PREFIX', 'tsf_');
 # Session Timeout in Seconds.
define ('TS_TIMEOUT', 3600); #This is the time in seconds that a user must remain inactive before their login session expires. This setting also controls how long a user will remain on Who's Online after their last activity.
define ('PROFILE_MAX_VISITOR', 5); // Store max. visitor message per user. remove profile visits beyond the first PROFILE_MAX_VISITOR

# Default Usergroups for Tracker

/* Do not remove this line */
define ('UC_GUEST', 0);
define ('UC_USER', 1);
define ('UC_POWER_USER', 2);
define ('UC_VIP', 3);
define ('UC_UPLOADER', 4);
define ('UC_MODERATOR', 5);
define ('UC_ADMINISTRATOR', 6);
define ('UC_SYSOP', 7);
define ('UC_BANNED', 9);


if(!defined("SCRIPTNAME") ) 
{
   define("SCRIPTNAME", $_SERVER["SCRIPT_NAME"]);
}

	

	
  
  require_once INC_PATH."/error_handler.php";
  
  
  
  
  
  if ((isset ($_REQUEST['GLOBALS']) OR isset ($_FILES['GLOBALS'])))
  {
    define ('errorid', 1);
    include_once TSDIR . '/ts_error.php';
    exit ();
  }

  if ((strtoupper ($_SERVER['REQUEST_METHOD']) == 'POST' AND !defined ('SKIP_REFERRER_CHECK')))
  {
    if (($_SERVER['HTTP_HOST'] OR $_ENV['HTTP_HOST']))
    {
      $http_host = ($_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : $_ENV['HTTP_HOST']);
    }
    else
    {
      if (($_SERVER['SERVER_NAME'] OR $_ENV['SERVER_NAME']))
      {
        $http_host = ($_SERVER['SERVER_NAME'] ? $_SERVER['SERVER_NAME'] : $_ENV['SERVER_NAME']);
      }
    }

  }
  
 
 
 if(!function_exists('json_encode') || !function_exists('json_decode'))
 {
	require_once INC_PATH.'/3rdparty/json/json.php';
 }
 
 

 
 require_once INC_PATH."/class_templates.php";
 $templates = new templates;
 
 
 require_once INC_PATH . '/ts_functions.php';
 //require_once INC_PATH . '/functions.php';
 require_once INC_PATH . '/functions_tsseo.php';
 
 require_once INC_PATH."/class_timers.php";
 $maintimer = new timer();
 
 
 require_once INC_PATH . '/class_core.php';
 $mybb = new MyBB;
 
 
 require_once INC_PATH.'/class_plugins.php';
 $plugins = new pluginSystem;
 
 
 
 
 
  // Load DB interface
 require_once INC_PATH . '/config.php';
 $mybb->config = &$config;
 
 
 require_once INC_PATH . '/db_base.php';
 require_once INC_PATH . '/AbstractPdoDbDriver.php';

 //require_once INC_PATH."/db_mysqli.php";
 require_once INC_PATH."/db_".$config['database']['type'].".php";

 switch($config['database']['type'])
 {
	case "sqlite":
		$db = new DB_SQLite;
		break;
	case "pgsql":
		$db = new DB_PgSQL;
		break;
	case "pgsql_pdo":
		$db = new PostgresPdoDbDriver();
		break;
	case "mysqli":
		$db = new DB_MySQLi;
		break;
	case "mysql_pdo":
		$db = new MysqlPdoDbDriver();
		break;
	default:
		$db = new DB_MySQL;
  }


  // Connect to Database
  define("TABLE_PREFIX", $config['database']['table_prefix']);
  $db->connect($config['database']);
  $db->set_table_prefix(TABLE_PREFIX);
  $db->type = $config['database']['type'];
  
  
  
  require_once INC_PATH . '/class_datacache.php';
  $cache = new datacache;
  
  
 

  // Load cache
  $cache->cache();
  
  
  $mybb->parse_cookies();
  $mybb->cache = &$cache;
  $mybb->asset_url = $mybb->get_asset_url();
  
  


  // Load Settings
  if(file_exists(INC_PATH . '/settings.php'))
  {
	require_once INC_PATH . '/settings.php';
  }

  if(!file_exists(INC_PATH . '/settings.php'))
  {
	if(function_exists('rebuild_settings'))
	{
		rebuild_settings();
	}
	else
	{
		$options = array(
			"order_by" => "title",
			"order_dir" => "ASC"
		);
		
		$query = $db->simple_select("settings", "value, name", "", $options);
		while($setting = $db->fetch_array($query))
		{
			$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
			$settings[$setting['name']] = $setting['value'];
		}
		$db->free_result($query);
	}
  }
  
  
  
  // Read the usergroups cache as well as the moderators cache
  $groupscache = $cache->read('usergroups');

  // If the groups cache doesn't exist, update it and re-read it
  if(!is_array($groupscache))
  {
	$cache->update_usergroups();
	$groupscache = $cache->read('usergroups');
  }
  
  
  

  require_once INC_PATH . '/class_language.php';
  $lang = new trackerlanguage ();
  $lang->set_path (INC_PATH . '/languages');
  if ((empty ($_COOKIE['ts_language']) OR !file_exists (INC_PATH . '/languages/' . $_COOKIE['ts_language'])))
  {
    $lang->set_language ($defaultlanguage);
  }
  else
  {
    $lang->set_language ($_COOKIE['ts_language']);
  }
  
  $lang->load ('global');
 
  
  
/* URL Definitions */
if($seourls == "yes" || ($seourls == "auto" && isset($_SERVER['SEO_SUPPORT']) && $_SERVER['SEO_SUPPORT'] == 1))
{
   
   $mybb->seo_support = true;
   
   define('FORUM_URL', "forum-{fid}.html");
   define('FORUM_URL_PAGED', "forum-{fid}-page-{page}.html");
   define('THREAD_URL', "thread-{tid}.html");
   define('THREAD_URL_PAGED', "thread-{tid}-page-{page}.html");
   define('THREAD_URL_ACTION', 'thread-{tid}-{action}.html');
   define('THREAD_URL_POST', 'thread-{tid}-post-{pid}.html');
   define('POST_URL', "post-{pid}.html");
   
   define('PROFILE_URL', "user-{id}.html");
   define('PROFILE_URL_PAGED', "user-{id}-page-{page}.html");
   
   define('TORRENT_URL', "torrent-{id}.html");
   define('TORRENT_URL_PAGED', "torrent-{id}-page-{page}.html");
   
   define('DOWNLOAD_URL', "download-{id}.html");
   
   
   define('TORRENT_URL_COMMENT', 'torrent-{id}-comment-{pid}.html');
   define('COMMENT_URL', "comment-{pid}.html");
   
   define('ANNOUNCEMENT_URL', "announcement-{aid}.html");
   
}
else
{
   define('PROFILE_URL', "member.php?action=profile&id={id}");
   define('PROFILE_URL_PAGED', "userdetails.php?id={id}&page={page}");
   
   define('FORUM_URL', "forumdisplay.php?fid={fid}");
   define('FORUM_URL_PAGED', "forumdisplay.php?fid={fid}&page={page}");
   define('THREAD_URL', "showthread.php?tid={tid}");
   define('THREAD_URL_PAGED', "showthread.php?tid={tid}&page={page}");
   define('THREAD_URL_ACTION', 'showthread.php?tid={tid}&action={action}');
   define('THREAD_URL_POST', 'showthread.php?tid={tid}&pid={pid}');
   define('POST_URL', "showthread.php?pid={pid}");
   
   define('TORRENT_URL', "details.php?id={id}");
   define('TORRENT_URL_PAGED', "details.php?id={id}&page={page}");
   
   define('TORRENT_URL_COMMENT', 'details.php?id={id}&pid={pid}');
   define('COMMENT_URL', "details.php?pid={pid}");
   
   define('DOWNLOAD_URL', "download.php?id={id}");
  	
}
  
define("USERIPADDRESS", get_ip());

// Create session for this user
require_once INC_PATH.'/class_session.php';


$session = new session;
$session->init();
$mybb->session = &$session;






  
  
  
  // Set our POST validation code here
  $mybb->post_code = generate_post_check();
  
  // Post code
   $post_code_string = '';
   if (isset($CURUSER['id'])) 
   {
       $post_code_string = '&amp;my_post_key=' . $mybb->post_code;
   }
  
  


 
   
   
   // Load Main Templates and Cached Templates
   if(isset($templatelist))
   {
	  $templatelist .= ',';
   }
   else
   {
	  $templatelist = '';
   }
   
   
   $templates->cache($db->escape_string($templatelist));
   
  
  
	
   $shutdown_queries = $shutdown_functions = array();
  
   if($mybb->use_shutdown == true)
   {
	  register_shutdown_function('run_shutdown');
   }
	
	

  
  
  require_once INC_PATH . '/ctracker.php';
  

  if ((!isset ($HTTP_POST_VARS) AND isset ($_POST)))
  {
    $HTTP_POST_VARS = $_POST;
    $HTTP_GET_VARS = $_GET;
    $HTTP_SERVER_VARS = $_SERVER;
    $HTTP_COOKIE_VARS = $_COOKIE;
    $HTTP_ENV_VARS = $_ENV;
    $HTTP_POST_FILES = $_FILES;
  }

  
  
  
?>
