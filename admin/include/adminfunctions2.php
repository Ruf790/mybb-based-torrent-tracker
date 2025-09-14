<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function get_user_class_name ($class = '')
  {
    //global $cache;
    if ($class == 'all')
    {
      return 'ALL Usergroups';
    }

    require TSDIR . '/cache/usergroups.php';
    foreach ($usergroups as $arr)
    {
      if ($arr['gid'] == $class)
      {
        return $arr['title'];
      }
    }

    return 'ALL Usergroups';
  }

  define ('_AF_2', true);
  
  require_once $thispath . 'include/adminfunctions3.php';
  // (!defined ('_AF__3'))
 //
 // exit ('The authentication has been blocked because of invalid file detected!');
 //

?>
