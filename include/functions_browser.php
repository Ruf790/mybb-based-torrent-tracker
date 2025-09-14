<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function is_browser ($browser, $version = 0)
  {
    static $is;
    if (!is_array ($is))
    {
      $useragent = strtolower ($_SERVER['HTTP_USER_AGENT']);
      $is = array ('opera' => 0, 'ie' => 0, 'mozilla' => 0, 'firebird' => 0, 'firefox' => 0, 'camino' => 0, 'konqueror' => 0, 'safari' => 0, 'webkit' => 0, 'webtv' => 0, 'netscape' => 0, 'mac' => 0);
      if (strpos ($useragent, 'opera') !== false)
      {
        preg_match ('#opera(/| )([0-9\\.]+)#', $useragent, $regs);
        $is['opera'] = $regs[2];
      }

      if ((strpos ($useragent, 'msie ') !== false AND !$is['opera']))
      {
        preg_match ('#msie ([0-9\\.]+)#', $useragent, $regs);
        $is['ie'] = $regs[1];
      }

      if (strpos ($useragent, 'mac') !== false)
      {
        $is['mac'] = 1;
      }

      if (strpos ($useragent, 'applewebkit') !== false)
      {
        preg_match ('#applewebkit/(\\d+)#', $useragent, $regs);
        $is['webkit'] = $regs[1];
        if (strpos ($useragent, 'safari') !== false)
        {
          preg_match ('#safari/([0-9\\.]+)#', $useragent, $regs);
          $is['safari'] = $regs[1];
        }
      }

      if (strpos ($useragent, 'konqueror') !== false)
      {
        preg_match ('#konqueror/([0-9\\.-]+)#', $useragent, $regs);
        $is['konqueror'] = $regs[1];
      }

      if (((strpos ($useragent, 'gecko') !== false AND !$is['safari']) AND !$is['konqueror']))
      {
        preg_match ('#gecko/(\\d+)#', $useragent, $regs);
        $is['mozilla'] = $regs[1];
        if (((strpos ($useragent, 'firefox') !== false OR strpos ($useragent, 'firebird') !== false) OR strpos ($useragent, 'phoenix') !== false))
        {
          preg_match ('#(phoenix|firebird|firefox)( browser)?/([0-9\\.]+)#', $useragent, $regs);
          $is['firebird'] = $regs[3];
          if ($regs[1] == 'firefox')
          {
            $is['firefox'] = $regs[3];
          }
        }

        if ((strpos ($useragent, 'chimera') !== false OR strpos ($useragent, 'camino') !== false))
        {
          preg_match ('#(chimera|camino)/([0-9\\.]+)#', $useragent, $regs);
          $is['camino'] = $regs[2];
        }
      }

      if (strpos ($useragent, 'webtv') !== false)
      {
        preg_match ('#webtv/([0-9\\.]+)#', $useragent, $regs);
        $is['webtv'] = $regs[1];
      }

      if (preg_match ('#mozilla/([1-4]{1})\\.([0-9]{2}|[1-8]{1})#', $useragent, $regs))
      {
        $is['netscape'] = '' . $regs['1'] . '.' . $regs['2'];
      }
    }

    $browser = strtolower ($browser);
    if (substr ($browser, 0, 3) == 'is_')
    {
      $browser = substr ($browser, 3);
    }

    if ($is['' . $browser])
    {
      if ($version)
      {
        if ($version <= $is['' . $browser])
        {
          return $is['' . $browser];
        }
      }
      else
      {
        return $is['' . $browser];
      }
    }

    return 0;
  }

  if (!defined ('IN_TRACKER'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

?>
