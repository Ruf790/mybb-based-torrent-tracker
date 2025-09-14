<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  $rootpath = './../';
  include $rootpath . '/global.php';
  gzip ();
  dbconn ();
  loggedinorreturn ();
  maxsysop ();
  define ('IN_CHARACTER', true);
  define ('WYSIWYG_EDITOR', true);
  define ('USE_BB_CODE', true);
  define ('USE_SMILIES', true);
  define ('USE_HTML', false);
  require $thispath . 'wysiwyg/wysiwyg.php';
  if ($CURUSER['usergroup'] <= UC_MODERATOR)
  {
    stderr ('Sorry ' . $CURUSER['username'], 'The ADMINISTRATOR does not allow your class to view the character set');
  }

  echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="en">
<head>
<title>languages, countries and character sets</title>
<link rel="stylesheet" href="';
  echo $BASEURL;
  echo '/include/templates/';
  echo $defaulttemplate;
  echo '/style/style.css" type="text/css" media="screen" />
</head>
</html>    
<table class=main border=0 cellspacing=0 cellpadding=0 width=100%><tr><td class=embedded>
<table width=100% border=1 cellspacing=0 cellpadding=10>
<table class="none" border="0" cellpadding="4" cellspacing="0" width="100%">
<tbody>
<tr>
<td class="thead" colspan="4">';
  echo '<s';
  echo 'trong><font color=white>Languages, countries, and the charsets typically used for them</a></font></strong></td>
</tr>
<tr><td><b><u>Language</u></b></td>        <td><b><u>charset</u></b></td></tr>
<tr><td>Afrikaans (af)</td>             <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Albanian (sq)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Arabic (ar)</td>                 <td>iso-8859-6</td></tr>
<tr><td>Ba';
  echo 'sque (eu)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Bulgarian (bg)</td>             <td>iso-8859-5</td></tr>
<tr><td>Byelorussian (be)</td>             <td>iso-8859-5</td></tr>
<tr><td>Catalan (ca)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Croatian (hr)</td>                 <td>iso-8859-2, windows-1250</td></tr>
<tr><td>Czech (cs)</td>                 <td>iso-8859-2</td></tr';
  echo '>
<tr><td>Danish (da)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Dutch (nl)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>English (en)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Esperanto (eo)</td>             <td>iso-8859-3*</td></tr>
<tr><td>Estonian (et)</td>                 <td>iso-8859-15</td></tr>
<tr><td>Faroese (fo)</td>                 <td>iso-8';
  echo '859-1, windows-1252</td></tr>
<tr><td>Finnish (fi)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>French (fr)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Galician (gl)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>German (de)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Greek (el)</td>                 <td>iso-8859-7</td></tr>
<tr><td>He';
  echo 'brew (iw)</td>                 <td>iso-8859-8</td></tr>
<tr><td>Hungarian (hu)</td>             <td>iso-8859-2</td></tr>
<tr><td>Icelandic (is)</td>             <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Inuit (Eskimo) languages</td>     <td>iso-8859-10*</td></tr>
<tr><td>Irish (ga)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Italian (it)</td>                 <td>iso-8859-1, windows-1252</td></tr>
';
  echo '
<tr><td>Japanese (ja)</td>                 <td>shift_jis, iso-2022-jp, euc-jp</td></tr>
<tr><td>Korean (ko)</td>                <td>euc-kr</td></tr>
<tr><td>Lapp</td>                         <td>iso-8859-10* **</td></tr>
<tr><td>Latvian (lv)</td>                 <td>iso-8859-13, windows-1257</td></tr>
<tr><td>Lithuanian (lt)</td>             <td>iso-8859-13, windows-1257</td></tr>
<tr><td>Macedonian (mk)</td>             <td';
  echo '>iso-8859-5, windows-1251</td></tr>
<tr><td>Maltese (mt)</td>                 <td>iso-8859-3*</td></tr>
<tr><td>Norwegian (no)</td>             <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Polish (pl)</td>                 <td>iso-8859-2</td></tr>
<tr><td>Portuguese (pt)</td>             <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Romanian (ro)</td>                 <td>iso-8859-2</td></tr>
<tr><td>Russian (ru)</td>       ';
  echo '          <td>koi8-r, iso-8859-5</td></tr>
<tr><td>Scottish (gd)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Serbian (sr)</td>                 <td>cyrillic    windows-1251, iso-8859-5***</td></tr>
<tr><td>Serbian (sr)</td>                 <td>latin    iso-8859-2, windows-1250</td></tr>
<tr><td>Slovak (sk)</td>                 <td>iso-8859-2</td></tr>
<tr><td>Slovenian (sl)</td>             <td>iso-8';
  echo '859-2, windows-1250</td></tr>
<tr><td>Spanish (es)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Swedish (sv)</td>                 <td>iso-8859-1, windows-1252</td></tr>
<tr><td>Turkish (tr)</td>                 <td>iso-8859-9, windows-1254</td></tr>
<tr><td>Ukrainian (uk)</td>             <td>iso-8859-5</td></tr>
<tr><td>* = scarce support in browsers</td><td>** = Lapp doesn\'t have a 2-letter code, ';
  echo 'a three letter code (lap) is proposed in NISO Z39.53.</td></tr>
<tr><td>*** = Serbian can be written in Latin (most commonly used) and Cyrillic (mostly windows-1251)</td>
<td>Note that UTF-8 can be used for all languages and is the recommended charset on the Internet. Support for it is rapidly increasing.
For Hebrew in HTML, iso-8859-8 is the same as iso-8859-8-i (\'implicit directionality\'). This is ';
  echo 'unlike e-mail, where they are different.
For more 2-letter language codes, see ISO 639.</td></tr>
</td></tr>
</table>';
?>
