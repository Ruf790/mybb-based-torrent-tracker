<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function ts_seo ($id, $text, $type = 'u', $ext = '.ts')
  {
    global $BASEURL;
    global $seourls;
    if ($seourls == 'yes')
    {
      $find = array ('/[^a-zA-Z0-9\\s]/', '/\\s+/');
      $replace = array ('_', '_');
      $text = strtolower (preg_replace ($find, $replace, $text));
      $text = preg_replace ('@__+@', '', $text);
      if ($type != 'u')
      {
        return $BASEURL . '/' . $text . '-' . $type . '-' . htmlspecialchars ($id) . $ext;
      }

      return $BASEURL . '/' . $text . '-u' . intval ($id) . $ext;
    }

    if ($type == 'a')
    {
      return '' . $BASEURL . '/announce.php?passkey=' . htmlspecialchars ($id);
    }

    if ($type == 'b')
    {
      return '' . $BASEURL . '/browse.php?cat=' . intval ($id);
    }

    if ($type == 'c')
    {
      return '' . $BASEURL . '/browse.php?browse_categories&amp;category=' . intval ($id);
    }

    if ($type == 'd')
    {
      return '' . $BASEURL . '/download.php?id=' . intval ($id);
    }

    if ($type == 's')
    {
      return '' . $BASEURL . '/details.php?id=' . intval ($id);
    }

    if ($type == 'u')
    {
      return '' . $BASEURL . '/userdetails.php?id=' . intval ($id);
    }

  }

  function tsf_seo_clean_text ($output, $type, $id, $extra = '', $ext = 'tsf')
  {
    global $BASEURL;
    global $ts_seo;
    if ($ts_seo == 'yes')
    {
      $find = array ('/[^a-zA-Z0-9\\s]/', '/\\s+/');
      $replace = array ('_', '_');
      $output = strtolower (preg_replace ($find, $replace, $output));
      $output = preg_replace ('@__+@', '', $output);
      $output = $BASEURL . '/' . htmlspecialchars ($output) . '-' . $type . intval ($id) . '.' . $ext . $extra;
    }
    else
    {
      switch ($type)
      {
        case 'f':
        {
          $output = $BASEURL . '/index2.php?fid=' . $id . $extra;
          break;
        }

        case 'fd':
        {
          $output = $BASEURL . '/forumdisplay.php?fid=' . $id . $extra;
          break;
        }

        case 't':
        {
          $output = $BASEURL . '/showthread.php?tid=' . $id . $extra;
          break;
        }

        case 'u':
        {
          $output = $BASEURL . '/userdetails.php?id=' . $id . $extra;
          break;
        }

        default:
        {
          $output = $BASEURL . '/index2.php';
          break;
        }
      }
    }

    return $output;
  }

  if ((!defined ('IN_SCRIPT_TSSEv56') AND !defined ('IN_CRON')))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

?>
