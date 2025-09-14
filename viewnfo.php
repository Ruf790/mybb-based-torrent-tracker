<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  define ('DEBUGMODE', false);
  require_once 'global.php';
  gzip ();

  //loggedinorreturn ();
  maxsysop ();
  parked ();
  define ('VN_VERSION', '0.5 ');
  $id = (isset ($_GET['id']) ? intval ($_GET['id']) : 0);
  $Error = false;
  $NFO = array ();
  if (!is_valid_id ($id))
  {
    $Error = true;
    $NFO['nfo'] = $lang->global['no_permission'];
  }

  if (!$Error)
  {
    if (version_compare ('4.0.6', phpversion ()) == 1)
    {
      $Error = true;
      $NFO['nfo'] = 'This version of PHP is not fully supported. You need 4.0.6 or above.';
    }
  }

  if (!$Error)
  {
    if ((extension_loaded ('gd') == false AND !dl ('gd.so')))
    {
      $Error = true;
      $NFO['nfo'] = 'Missing GD / GD-2 Library.';
    }
  }

  if (!$Error)
  {
    $query = $db->sql_query ('SELECT nfo FROM ts_nfo WHERE id = ' . $db->sqlesc ($id));
    if ($db->num_rows ($query) < 1)
    {
      $Error = true;
      $NFO['nfo'] = $lang->global['nopermission'];
    }
    else
    {
      $NFO = mysqli_fetch_assoc ($query);
    }
  }

  if (empty ($NFO['nfo']))
  {
    $NFO['nfo'] = $lang->global['nopermission'];
  }

  $red = 0;
  $green = 0;
  $blue = 0;
  $colour = 0;
  $fontset = imagecreatefrompng ($pic_base_url . 'nfogen.png');
  $x = 0;
  $y = 0;
  $fontx = 5;
  $fonty = 12;
  $colour = $colour * $fonty;
  $nfo = explode ('
', $NFO['nfo']);
  $image_height = count ($nfo) * 12;
  $image_width = 0;
  $c = 0;
  while ($c < count ($nfo))
  {
    $line = $nfo[$c];
    $temp_len = strlen ($line);
    if ($image_width < $temp_len)
    {
      $image_width = $temp_len;
    }

    ++$c;
  }

  $image_width = $image_width * $fontx;
  if (1600 < $image_width)
  {
    $image_width = 1600;
  }

  $im = imagecreatetruecolor ($image_width, $image_height);
  $bgc = imagecolorallocate ($im, $red, $green, $blue);
  imagefill ($im, 0, 0, $bgc);
  $c = 0;
  while ($c < count ($nfo))
  {
    $x = $fontx;
    $line = $nfo[$c];
    $i = 0;
    while ($i < strlen ($line))
    {
      $current_char = substr ($line, $i, 1);
      if (($current_char !== '
' AND $current_char !== '
'))
      {
        $offset = ord ($current_char) * 5;
        imagecopy ($im, $fontset, $x, $y, $offset, $colour, $fontx, $fonty);
        $x += $fontx;
      }

      ++$i;
    }

    $y += $fonty;
    ++$c;
  }

  header ('Content-type: image/png');
  imagepng ($im);
  imagedestroy ($im);
  exit ();
?>
