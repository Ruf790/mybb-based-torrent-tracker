<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function tssegetimdbratingimage ($Content = '')
  {
    global $BASEURL;
    global $pic_base_url;
    if (preg_match ('@<b>User Rating:<\\/b> (.*)\\/10@U', $Content, $IMDBRating))
    {
      $FirstLetter = $IMDBRating[1][0];
      switch ($IMDBRating[1][2])
      {
        case 0:
        {
        }

        case 1:
        {
        }

        case 2:
        {
          $SecondLetter = '';
          break;
        }

        case 3:
        {
        }

        case 4:
        {
        }

        case 5:
        {
        }

        case 6:
        {
        }

        case 7:
        {
        }

        case 8:
        {
        }

        case 9:
        {
          $SecondLetter = '.5';
        }
      }

      if ($FirstLetter)
      {
        $IMDBRatingImage = '<img src="' . $BASEURL . '/' . $pic_base_url . 'imdb_rating/' . $FirstLetter . $SecondLetter . '-10.png" border="0" alt="' . $IMDBRating[1] . '/10' . '" title="' . $IMDBRating[1] . '/10' . '" />';
        return array ('image' => $IMDBRatingImage, 'rating' => $IMDBRating[1] . '/10');
      }
    }

    return false;
  }

  if (!defined ('IN_SCRIPT_TSSEv56'))
  {
    exit ('<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

?>
