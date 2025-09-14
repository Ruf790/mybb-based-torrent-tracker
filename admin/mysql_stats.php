<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function formatbytedown ($value, $limes = 6, $comma = 0)
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

  function timespanformat ($seconds)
  {
    $return_string = '';
    $days = floor ($seconds / 86400);
    if (0 < $days)
    {
      $seconds -= $days * 86400;
    }

    $hours = floor ($seconds / 3600);
    if ((0 < $days OR 0 < $hours))
    {
      $seconds -= $hours * 3600;
    }

    $minutes = floor ($seconds / 60);
    if (((0 < $days OR 0 < $hours) OR 0 < $minutes))
    {
      $seconds -= $minutes * 60;
    }

    return (string)$days . ' Days ' . (string)$hours . ' Hours ' . (string)$minutes . ' Minutes ' . (string)$seconds . ' Seconds ';
  }

  function localiseddate ($timestamp = -1, $format = '')
  {
    global $datefmt;
    global $month;
    global $day_of_week;
    if ($format == '')
    {
      $format = $datefmt;
    }

    if ($timestamp == 0 - 1)
    {
      $timestamp = time ();
    }

    $date = preg_replace ('@%[aA]@', $day_of_week[(int)strftime ('%w', $timestamp)], $format);
    $date = preg_replace ('@%[bB]@', $month[(int)strftime ('%m', $timestamp) - 1], $date);
    return strftime ($date, $timestamp);
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  $GLOBALS['byteUnits'] = array ('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB');
  $day_of_week = array ('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
  $month = array ('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
  $datefmt = '%B %d, %Y at %I:%M %p';
  $timespanfmt = '%s days, %s hours, %s minutes and %s seconds';
  stdhead ('Stats');
  echo '<h2>' . '
' . '    Mysql Server Status' . '
' . '</h2>' . '
';
  if (!($res = $db->sql_query ('SHOW STATUS')))
  {
    exit (mysqli_error ());
    ;
  }

  while ($row = mysqli_fetch_row ($res))
  {
    $serverStatus[$row[0]] = $row[1];
  }

  @mysqli_free_result ($res);
  unset ($res);
  unset ($row);
  $res = $db->sql_query ('SELECT UNIX_TIMESTAMP() - ' . $serverStatus['Uptime']);
  $row = mysqli_fetch_row ($res);
  echo '
	<table id="torrenttable" border="1"><tr><td>

';
  print 'This MySQL server has been running for ' . timespanformat ($serverStatus['Uptime']) . '. It started up on ' . localiseddate ($row[0]) . '
';
  echo '
	</td></tr></table>

';
  @mysqli_free_result ($res);
  unset ($res);
  unset ($row);
  $queryStats = array ();
  $tmp_array = $serverStatus;
  foreach ($tmp_array as $name => $value)
  {
    if (substr ($name, 0, 4) == 'Com_')
    {
      $queryStats[str_replace ('_', ' ', substr ($name, 4))] = $value;
      unset ($serverStatus[$name]);
      continue;
    }
  }

  unset ($tmp_array);
  echo '
<ul>
    <li>
        <!-- Server Traffic -->
        <b>Server traffic:</b> These tables show the network traffic statistics of this MySQL server since its startup
        <br />
        <table border="0">
            <tr>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lig';
  echo 'htgrey">&nbsp;Traffic&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&nbsp;Per Hour&nbsp;</th>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Received&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo join (' ', formatbytedown ($serverStatus['Bytes_received']));
  echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo join (' ', formatbytedown ($serverStatus['Bytes_received'] * 3600 / $serverStatus['Uptime']));
  echo '&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Sent&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo join (' ', formatbytedown ($serverStatus['Bytes_sent']));
  echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo join (' ', formatbytedown ($serverStatus['Bytes_sent'] * 3600 / $serverStatus['Uptime']));
  echo '&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="lightgrey">&nbsp;Total&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;';
  echo join (' ', formatbytedown ($serverStatus['Bytes_received'] + $serverStatus['Bytes_sent']));
  echo '&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;';
  echo join (' ', formatbytedown (($serverStatus['Bytes_received'] + $serverStatus['Bytes_sent']) * 3600 / $serverStatus['Uptime']));
  echo '&nbsp;</td>
                        </tr>
                    </table>
                </td>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Connections&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per Hour&nbsp;</th>
    ';
  echo '                        <th bgcolor="lightgrey">&nbsp;%&nbsp;</th>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Failed Attempts&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo number_format ($serverStatus['Aborted_connects'], 0, '.', ',');
  echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo number_format ($serverStatus['Aborted_connects'] * 3600 / $serverStatus['Uptime'], 2, '.', ',');
  echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo (0 < $serverStatus['Connections'] ? number_format ($serverStatus['Aborted_connects'] * 100 / $serverStatus['Connections'], 2, '.', ',') . '&nbsp;%' : '---');
  echo '&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Aborted Clients&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo number_format ($serverStatus['Aborted_clients'], 0, '.', ',');
  echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo number_format ($serverStatus['Aborted_clients'] * 3600 / $serverStatus['Uptime'], 2, '.', ',');
  echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo (0 < $serverStatus['Connections'] ? number_format ($serverStatus['Aborted_clients'] * 100 / $serverStatus['Connections'], 2, '.', ',') . '&nbsp;%' : '---');
  echo '&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="lightgrey">&nbsp;Total&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;';
  echo number_format ($serverStatus['Connections'], 0, '.', ',');
  echo '&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;';
  echo number_format ($serverStatus['Connections'] * 3600 / $serverStatus['Uptime'], 2, '.', ',');
  echo '&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;';
  echo number_format (100, 2, '.', ',');
  echo '&nbsp;%&nbsp;</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </li>
    <br />
    <li>
        <!-- Queries -->
        ';
  print '<b>Query Statistics:</b> Since it\'s start up, ' . number_format ($serverStatus['Questions'], 0, '.', ',') . ' queries have been sent to the server.
';
  echo '        <table border="0">
            <tr>
                <td colspan="2">
                    <br />
                    <table id="torrenttable" border="0" align="right">
                        <tr>
                            <th bgcolor="lightgrey">&nbsp;Total&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                          ';
  echo '  <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Minute&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Second&nbsp;</th>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo number_format ($serverStatus['Questions'], 0, '.', ',');
  echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo number_format ($serverStatus['Questions'] * 3600 / $serverStatus['Uptime'], 2, '.', ',');
  echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo number_format ($serverStatus['Questions'] * 60 / $serverStatus['Uptime'], 2, '.', ',');
  echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
  echo number_format ($serverStatus['Questions'] / $serverStatus['Uptime'], 2, '.', ',');
  echo '&nbsp;</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Query&nbsp;Type&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp';
  echo ';&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;%&nbsp;</th>
                        </tr>
';
  $useBgcolorOne = TRUE;
  $countRows = 0;
  foreach ($queryStats as $name => $value)
  {
    echo '                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;';
    echo htmlspecialchars ($name);
    echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
    echo number_format ($value, 0, '.', ',');
    echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
    echo number_format ($value * 3600 / $serverStatus['Uptime'], 2, '.', ',');
    echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
    echo number_format ($value * 100 / ($serverStatus['Questions'] - $serverStatus['Connections']), 2, '.', ',');
    echo '&nbsp;%&nbsp;</td>
                        </tr>
';
    $useBgcolorOne = !$useBgcolorOne;
    if (++$countRows == ceil (count ($queryStats) / 2))
    {
      $useBgcolorOne = TRUE;
      echo '                    </table>
                </td>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Query&nbsp;Type&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th bgcolo';
      echo 'r="lightgrey">&nbsp;%&nbsp;</th>
                        </tr>
';
      continue;
    }
  }

  unset ($countRows);
  unset ($useBgcolorOne);
  echo '                    </table>
                </td>
            </tr>
        </table>
    </li>
';
  unset ($serverStatus[Aborted_clients]);
  unset ($serverStatus[Aborted_connects]);
  unset ($serverStatus[Bytes_received]);
  unset ($serverStatus[Bytes_sent]);
  unset ($serverStatus[Connections]);
  unset ($serverStatus[Questions]);
  unset ($serverStatus[Uptime]);
  if (!empty ($serverStatus))
  {
    echo '    <br />
    <li>
        <!-- Other status variables -->
        <b>More status variables</b><br />
        <table border="0">
            <tr>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th bgcolor="lightgrey">&nbsp;Variable&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;V';
    echo 'alue&nbsp;</th>
                        </tr>
';
    $useBgcolorOne = TRUE;
    $countRows = 0;
    foreach ($serverStatus as $name => $value)
    {
      echo '                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;';
      echo htmlspecialchars (str_replace ('_', ' ', $name));
      echo '&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;';
      echo htmlspecialchars ($value);
      echo '&nbsp;</td>
                        </tr>
';
      $useBgcolorOne = !$useBgcolorOne;
      if ((++$countRows == ceil (count ($serverStatus) / 3) OR $countRows == ceil (count ($serverStatus) * 2 / 3)))
      {
        $useBgcolorOne = TRUE;
        echo '                    </table>
                </td>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th bgcolor="lightgrey">&nbsp;Variable&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;Value&nbsp;</th>
                        </tr>
';
        continue;
      }
    }

    unset ($useBgcolorOne);
    echo '                    </table>
                </td>
            </tr>
        </table>
    </li>
';
  }

  echo '</ul>


';
  stdfoot ();
?>
