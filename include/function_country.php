<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function detect_user_country ()
  {
    global $ip;
    $numbers = preg_split ('/\\./', $ip);
    include INC_PATH . '/ip_files/' . $numbers[0] . '.php';
    $code = $numbers[0] * 16777216 + $numbers[1] * 65536 + $numbers[2] * 256 + $numbers[3];
    foreach ($ranges as $key => $value)
    {
      if ($key <= $code)
      {
        if ($code <= $ranges[$key][0])
        {
          $country = $ranges[$key][1];
          break;
        }

        continue;
      }
    }

    if ($country == '')
    {
      $country = 'unkown';
    }

    return $country;
  }

?>
