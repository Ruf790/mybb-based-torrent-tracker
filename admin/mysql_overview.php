<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function formatbytedown ($value, $limes = 2, $comma = 0)
  {
    $dh = pow (10, $comma);
    $li = pow (10, $limes);
    $return_value = $value;
    $unit = $GLOBALS['byteUnits'][0];
    $d = 6;
    $ex = 15;
    while (1 <= $d)
    {
      if ((isset ($GLOBALS['byteUnits'][$d]) AND $li * pow (10, $ex) <= $value))
      {
        $value = round ($value / (pow (1024, $d) / $dh)) / $dh;
        $unit = $GLOBALS['byteUnits'][$d];
        break;
      }

      --$d;
      $ex -= 3;
    }

    if ($unit != $GLOBALS['byteUnits'][0])
    {
      $return_value = number_format ($value, $comma, '.', ',');
    }
    else
    {
      $return_value = number_format ($value, 0, '.', ',');
    }

    return array ($return_value, $unit);
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  if ((isset ($_GET['Do']) AND isset ($_GET['table'])))
  {
    $Do = ($_GET['Do'] === 'T' ? sqlesc ($_GET['Do']) : '');
    if (!ereg ('[^A-Za-z_]+', $_GET['table']))
    {
      $Table = '`' . $_GET['table'] . '`';
    }
    else
    {
      print_no_permission (true);
    }

    $sql = '' . 'OPTIMIZE TABLE ' . $Table;
    if (preg_match ('@^(CHECK|ANALYZE|REPAIR|OPTIMIZE)[[:space:]]TABLE[[:space:]]' . $Table . '$@i', $sql))
    {
      if (!($db->sql_query ($sql)))
      {
        exit ('<b>Something was not right!</b>.
<br />Query: ' . $sql . '<br />
Error: (' . mysqli_errno () . ') ' . htmlspecialchars (mysql_error ()));
        ;
      }

      $return_url = $_this_script_ . '&Do=F';
      header ('Location:  ' . $_this_script_ . '&Do=F');
      exit ();
    }
  }

  $GLOBALS['byteUnits'] = array ('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB');
  stdhead ('Stats');
  //echo '<table align="center" border="0" class="tborder" cellpadding="0" cellspacing="0" width="100%">';
  //echo '<tbody><tr><td><table class="tback" border="0" cellpadding="6" cellspacing="0" width="100%"><tbody><tr><td class="thead" colspan="4" align="center">Mysql Server Table Status</td></tr>';
  
  echo '
  
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Mysql Server Table Status
	</div>
	 </div>
		</div>';
  
  
  
  echo '


<!-- <td>Max_data_length</td> -->




<div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Name</th>
        <th>Size</th>
        <th>Rows</th>
		<th>Avg row length</th>
        <th>Data length</th>
        <th>Index length</th>
		<th>Overhead</th>
      </tr>
    </thead>





<!-- <td>Auto_increment</td> -->

<!-- <td>Timings</td> -->



<!-- End table headers -->

';
  $count = 0;
  if (!($res = @$db->sql_query ('SHOW TABLE STATUS FROM `' . $config['database']['database'] . '`')))
  {
    exit (mysqli_error ());
    ;
  }

  while ($row = mysqli_fetch_array ($res))
  {
    list ($formatted_Avg, $formatted_Abytes) = formatbytedown ($row['Avg_row_length']);
    list ($formatted_Dlength, $formatted_Dbytes) = formatbytedown ($row['Data_length']);
    list ($formatted_Ilength, $formatted_Ibytes) = formatbytedown ($row['Index_length']);
    list ($formatted_Dfree, $formatted_Fbytes) = formatbytedown ($row['Data_free']);
    $tablesize = $row['Data_length'] + $row['Index_length'];
    list ($formatted_Tsize, $formatted_Tbytes) = formatbytedown ($tablesize, 3, (0 < $tablesize ? 1 : 0));
    $thispage = '&Do=T&table=' . $row['Name'];
    $overhead = (0 < $formatted_Dfree ? '<a href=' . $_this_script_ . $thispage . '><font color=\'red\'><b>' . $formatted_Dfree . ' ' . $formatted_Fbytes . '</b></font></a>' : $formatted_Dfree . ' ' . $formatted_Fbytes);
    echo '' . '<tr align="right"><td align="left">' . $row['Name'] . '</td>' . ('' . '<td>' . $formatted_Tsize . ' ' . $formatted_Tbytes . '</td>') . ('' . '<td>' . $row['Rows'] . '</td>') . ('' . '<td>' . $formatted_Avg . ' ' . $formatted_Abytes . '</td>') . ('' . '<td>' . $formatted_Dlength . ' ' . $formatted_Dbytes . '</td>') . ('' . '<td>' . $formatted_Ilength . ' ' . $formatted_Ibytes . '</td>') . ('' . '<td>' . $overhead . '</td></tr>') . ('' . '<tr><td colspan="7" align="right"><i><b>Row Format:</b></i> ' . $row['Row_format']) . ('' . '<br /><i><b>Create Time:</b></i> ' . $row['Create_time']) . ('' . '<br /><i><b>Update Time:</b></i> ' . $row['Update_time']) . ('' . '<br /><i><b>Check Time:</b></i> ' . $row['Check_time'] . '</td></tr>');
    ++$count;
  }

  echo '' . '<tr><td><b>Tables: ' . $count . '</b></td><td colspan="6" align="right">If it\'s <font color="red"><b>RED</b></font> it probably needs optimising!!</td></tr>';
  echo '<!-- End table -->
</table></div></div>

';
  stdfoot ();
?>
