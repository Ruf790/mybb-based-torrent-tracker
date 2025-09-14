<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function seo_activate ()
  {
    global $rootpath;
    $htaccess_code = '
# //seo_mod_start
' . '%extra_code%' . '# Uncomment the following and add your tracker path if rewrites arent working properly
' . '#RewriteBase /
' . ('' . 'RewriteRule ^index.html$ index.php [L,NE]
') . ('' . 'RewriteRule ^(.*)-b-([0-9]+).ts(.*)$ browse.php?cat=$2 [QSA,L]
') . ('' . 'RewriteRule ^(.*)-c-([0-9]+).ts(.*)$ browse.php?browse_categories&category=$2 [QSA,L]
') . ('' . 'RewriteRule ^(.*)-d-([0-9]+).ts(.*)$ download.php?id=$2 [QSA,L]
') . ('' . 'RewriteRule ^(.*)-s-([0-9]+).ts(.*)$ details.php?id=$2 [QSA,L]
') . ('' . 'RewriteRule ^(.*)-a-(.*).ts(.*)$ announce.php?passkey=$2 [QSA,L]
') . ('' . 'RewriteRule ^(.*)-u?([0-9]+).ts(.*)$ userdetails.php?id=$2$3 [QSA,L]
') . ('' . 'RewriteRule ^(.*)-f([0-9]+).tsf(.*)$ tsf_forums/index.php?fid=$2$3 [QSA,L]
') . ('' . 'RewriteRule ^(.*)-fd([0-9]+).tsf(.*)$ tsf_forums/forumdisplay.php?fid=$2$3 [QSA,L]
') . ('' . 'RewriteRule ^(.*)-t([0-9]+).tsf(.*)$ tsf_forums/showthread.php?tid=$2$3 [QSA,L]
') . '# //seo_mod_end
';
    if (!file_exists (TSDIR . '/.htaccess'))
    {
      @touch (TSDIR . '/.htaccess');
    }

    if (is_writeable (TSDIR . '/.htaccess'))
    {
      $cur_htaccess = implode ('', file (TSDIR . '/.htaccess'));
      if (strstr ($cur_htaccess, '//seo_mod_start'))
      {
        return null;
      }

      $htaccess_code = str_replace ('%extra_code%', (preg_match ('#RewriteEngine.+?on#i', $cur_htaccess) ? '' : 'RewriteEngine On
'), $htaccess_code);
      $fp = fopen (TSDIR . '/.htaccess', 'w');
      fwrite ($fp, $cur_htaccess . $htaccess_code);
      fclose ($fp);
      return null;
    }

    stderr ('Error, .htaccess isn\'t writable', '<b>Copy/Paste the following code at end of a .htaccess file in your tracker root directory:</b><br /><br />' . '<textarea cols=\'90\' rows=\'8\'>' . str_replace ('%extra_code%', 'RewriteEngine On
', $htaccess_code) . '</textarea>', false);
  }

  define ('_AF___4', true);
  
  require_once $thispath . 'include/adminfunctions5.php';
 //f (!defined ('_AF____5'))
 //
 // exit ('The authentication has been blocked because of invalid file detected!');
 //

?>
