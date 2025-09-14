<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  define("IN_MYBB", 1);
  define ('TSCR_VERSION', '1.2 ');
  define ('IN_CRON', true);
  define ('IN_TRACKER', true);
  define ('TS_TIMEOUT', 3600);
  define ('TIMENOW', time ());
  define ('THIS_PATH', dirname (__FILE__));
  define ('CONFIG_DIR', THIS_PATH . '/config/');
  define ('CRON_PATH', THIS_PATH . '/include/cron/');
  define ('INC_PATH', THIS_PATH . '/include');
  define ('TSDIR', THIS_PATH);
  //require INC_PATH . '/init.php';
  
  require_once INC_PATH . '/settings.php';
  
  require CRON_PATH . '/cron_functions.php';
  require_once INC_PATH . '/readconfig_cleanup.php';
  
  
  // Include our base data handler class
  require_once INC_PATH . '/datahandler.php';
  
  
  require_once INC_PATH.'/class_plugins.php';
  $plugins = new pluginSystem;
 
 
  require_once INC_PATH . '/class_core.php';
  $mybb = new MyBB;
  
  require_once INC_PATH . '/class_datacache.php';
  $cache = new datacache;

  
  
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



define('GB_IN_BYTES', 1024 * 1024 * 1024);
define('DAY_IN_SECONDS', 86400);
define('WEEK_IN_SECONDS', 604800);
define('HOUR_IN_SECONDS', 3600);
 
  
  // Load DB interface
require_once INC_PATH . '/config.php';
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

  
  $_FileData = base64_decode ('R0lGODlhAQABAIAAAMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
  $_FileSize = strlen ($_FileData);
  header ('Content-type: image/gif');
  if (!(strpos ($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false AND strpos (php_sapi_name (), 'cgi') !== false))
  {
    header ('Content-Length: ' . $_FileSize);
    header ('Connection: Close');
  }

  echo $_FileData;
  flush ();
  

  //readconfig (array ('CLEANUP', 'THEME'));
  $lang = new trackerlanguage ();
  $lang->set_path (INC_PATH . '/languages');
  $lang->set_language (((isset ($_COOKIE['ts_language']) AND file_exists (INC_PATH . '/languages/' . $_COOKIE['ts_language'])) ? $_COOKIE['ts_language'] : $defaultlanguage));
  $lang->load ('cronjobs');
  $_CQuery = $db->sql_query ('SELECT cronid, minutes, filename, loglevel FROM ts_cron WHERE nextrun < \'' . TIMENOW . '\' AND active = \'1\'');
  if (0 < $db->num_rows ($_CQuery))
  {
    while ($_RunCron = mysqli_fetch_assoc ($_CQuery))
    {
      if (file_exists (CRON_PATH . $_RunCron['filename']))
      {
        $CQueryCount = 0;
        $_CStart = array_sum (explode (' ', microtime ()));
        include CRON_PATH . $_RunCron['filename'];
        if ($_RunCron['loglevel'] == '1')
        {
          logcronaction ($_RunCron['filename'], $CQueryCount, round (array_sum (explode (' ', microtime ())) - $_CStart, 4));
        }

        $db->sql_query ('UPDATE ts_cron SET nextrun = \'' . (TIMENOW + $_RunCron['minutes']) . '\' WHERE cronid = \'' . $_RunCron['cronid'] . '\'');
        continue;
      }
    }
  }

?>
