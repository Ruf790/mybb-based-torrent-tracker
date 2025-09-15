<?
/***********************************************/
/*     TS Special Edition v.5.4.1 [Nulled]     */
/*              Special Thanks To              */
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  

  function critical_error ($message)
  {
    exit ('<font face="verdana" size="2" color="darkred"><b>' . $message . '<b/></font>');
  }

  function replace_url ($url)
  {
    return @str_replace (array ('http://www.', 'http://', 'www.'), '', $url);
  }

  //@error_reporting (0);
  //@ini_set ('error_reporting', '0');
  //@ini_set ('display_errors', '0');
  //@ini_set ('display_startup_errors', '0');
  //@ini_set ('ignore_repeated_errors', '1');
  //@ini_set ('log_errors', '0');
  //@set_magic_quotes_runtime (0);
  //@ignore_user_abort (1);
  if ((function_exists ('set_time_limit') AND get_cfg_var ('safe_mode') == 0))
  {
    @set_time_limit (120);
  }

  @ini_set ('session.gc_maxlifetime', '3600');
  @ini_set ('short_open_tag', 1);
  @session_cache_expire (60);
  //@session_name ('TSSE_Session');
  //@session_start ();
  define ('SHORT_SCRIPT_VERSION', '5.4');
  define ('SCRIPT_VERSION', 'TS Special Edition v.5.4');
  
  if (!defined ('WYSIWYG_EDITOR'))
  {
    exit ('<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }


?>