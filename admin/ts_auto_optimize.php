<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function fast_db_connect ()
  {
    global $rootpath;
    $dbfile = $rootpath . 'config/DATABASE';
    if (!file_exists ($dbfile))
    {
      return false;
    }

    $data = file_get_contents ($dbfile);
    $data = unserialize ($data);
    if ((!$data OR !$connect = mysql_connect ($data['mysql_host'], $data['mysql_user'], $data['mysql_pass'])))
    {
      return false;
    }

    if (!mysql_select_db ($data['mysql_db'], $connect))
    {
      return false;
    }

    $GLOBALS['mysql_db'] = $data['mysql_db'];
    unset ($data);
    return true;
  }

  error_reporting (E_ALL & ~E_NOTICE);
  ini_set ('error_reporting', E_ALL & ~E_NOTICE);
  ini_set ('display_errors', '0');
  set_time_limit (0);
  define ('TAO_VERSION', '0.2 by xam');
  $thispath = './';
  $rootpath = './../';
  require_once $thispath . 'include/staff_languages.php';
  clearstatcache ();
  $_config_file = $rootpath . '/config/MAIN';
  $_continue = 0;
  if (!function_exists ('file_put_contents'))
  {
    function file_put_contents ($filename, $contents)
    {
      if (is_writable ($filename))
      {
        if ($handle = fopen ($filename, 'w'))
        {
          if (fwrite ($handle, $contents) === FALSE)
          {
            return false;
          }

          fclose ($filename);
          return true;
        }
      }

      return false;
    }
  }

  if (((fast_db_connect () AND file_exists ($_config_file)) AND $_contents = file_get_contents ($_config_file)))
  {
    if ($__contents = unserialize ($_contents))
    {
      if (!empty ($__contents['cache']))
      {
        $_progress_file = $rootpath . $__contents['cache'] . '/auto_optimize.dat';
        if ((file_exists ($_progress_file) AND is_writable ($_progress_file)))
        {
          if (file_put_contents ($_progress_file, time () . ':yes'))
          {
            $_continue = 1;
          }
        }
      }
    }
  }

  $alltables = sql_query ('SHOW TABLE STATUS FROM `' . $mysql_db . '`');
  while ($table = mysql_fetch_assoc ($alltables))
  {
    if ((0 < $table['Data_free'] AND $table['Engine'] == 'MyISAM'))
    {
      sql_query ('OPTIMIZE TABLE `' . $table['Name'] . '`');
      echo $table['Name'] . ' has been optimized.. <br />';
      continue;
    }
  }

  exit ($adminlang['ts_auto_optimize']['update_message']);
?>
