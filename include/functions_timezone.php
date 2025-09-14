<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  
 function get_supported_timezones()
 {
	global $lang;
	$timezones = array(
		"-12" => $lang->usercp['timezone_gmt_minus_1200'],
		"-11" => $lang->usercp['timezone_gmt_minus_1100'],
		"-10" => $lang->usercp['timezone_gmt_minus_1000'],
		"-9.5" => $lang->usercp['timezone_gmt_minus_950'],
		"-9" => $lang->usercp['timezone_gmt_minus_900'],
		"-8" => $lang->usercp['timezone_gmt_minus_800'],
		"-7" => $lang->usercp['timezone_gmt_minus_700'],
		"-6" => $lang->usercp['timezone_gmt_minus_600'],
		"-5" => $lang->usercp['timezone_gmt_minus_500'],
		"-4.5" => $lang->usercp['timezone_gmt_minus_450'],
		"-4" => $lang->usercp['timezone_gmt_minus_400'],
		"-3.5" => $lang->usercp['timezone_gmt_minus_350'],
		"-3" => $lang->usercp['timezone_gmt_minus_300'],
		"-2" => $lang->usercp['timezone_gmt_minus_200'],
		"-1" => $lang->usercp['timezone_gmt_minus_100'],
		"0" => $lang->usercp['timezone_gmt'],
		"1" => $lang->usercp['timezone_gmt_100'],
		"2" => $lang->usercp['timezone_gmt_200'],
		"3" => $lang->usercp['timezone_gmt_300'],
		"3.5" => $lang->usercp['timezone_gmt_350'],
		"4" => $lang->usercp['timezone_gmt_400'],
		"4.5" => $lang->usercp['timezone_gmt_450'],
		"5" => $lang->usercp['timezone_gmt_500'],
		"5.5" => $lang->usercp['timezone_gmt_550'],
		"5.75" => $lang->usercp['timezone_gmt_575'],
		"6" => $lang->usercp['timezone_gmt_600'],
		"6.5" => $lang->usercp['timezone_gmt_650'],
		"7" => $lang->usercp['timezone_gmt_700'],
		"8" => $lang->usercp['timezone_gmt_800'],
		"8.5" => $lang->usercp['timezone_gmt_850'],
		"8.75" => $lang->usercp['timezone_gmt_875'],
		"9" => $lang->usercp['timezone_gmt_900'],
		"9.5" => $lang->usercp['timezone_gmt_950'],
		"10" => $lang->usercp['timezone_gmt_1000'],
		"10.5" => $lang->usercp['timezone_gmt_1050'],
		"11" => $lang->usercp['timezone_gmt_1100'],
		"11.5" => $lang->usercp['timezone_gmt_1150'],
		"12" => $lang->usercp['timezone_gmt_1200'],
		"12.75" => $lang->usercp['timezone_gmt_1275'],
		"13" => $lang->usercp['timezone_gmt_1300'],
		"14" => $lang->usercp['timezone_gmt_1400']
	);
	return $timezones;
 }




 function build_timezone_select($name, $selected=0, $short=false)
 {
	global $mybb, $lang, $templates, $timeformat;

	$timezones = get_supported_timezones();

	$selected = str_replace("+", "", $selected);
	$timezone_option = '';
	foreach($timezones as $timezone => $label)
	{
		$selected_add = "";
		if($selected == $timezone)
		{
			$selected_add = " selected=\"selected\"";
		}
		if($short == true)
		{
			$label = '';
			if($timezone != 0)
			{
				$label = $timezone;
				if($timezone > 0)
				{
					$label = "+{$label}";
				}
				if(strpos($timezone, ".") !== false)
				{
					$label = str_replace(".", ":", $label);
					$label = str_replace(":5", ":30", $label);
					$label = str_replace(":75", ":45", $label);
				}
				else
				{
					$label .= ":00";
				}
			}
			$time_in_zone = my_datee($timeformat, TIMENOW, $timezone);
			$label = sprintf($lang->usercp['timezone_gmt_short'], $label." ", $time_in_zone);
		}

		
		eval("\$timezone_option .= \"".$templates->get("usercp_options_timezone_option")."\";");
	}

	eval("\$select = \"".$templates->get("usercp_options_timezone")."\";");
	
	
	
	return $select;
  }
  
  
  
  
  
  
  function fetch_timezone ($offset = 'all')
  {
    $timezones = array ('-12' => 'timezone_gmt_minus_1200', '-11' => 'timezone_gmt_minus_1100', '-10' => 'timezone_gmt_minus_1000', '-9' => 'timezone_gmt_minus_0900', '-8' => 'timezone_gmt_minus_0800', '-7' => 'timezone_gmt_minus_0700', '-6' => 'timezone_gmt_minus_0600', '-5' => 'timezone_gmt_minus_0500', '-4.5' => 'timezone_gmt_minus_0430', '-4' => 'timezone_gmt_minus_0400', '-3.5' => 'timezone_gmt_minus_0330', '-3' => 'timezone_gmt_minus_0300', '-2' => 'timezone_gmt_minus_0200', '-1' => 'timezone_gmt_minus_0100', '0' => 'timezone_gmt_plus_0000', '1' => 'timezone_gmt_plus_0100', '2' => 'timezone_gmt_plus_0200', '3' => 'timezone_gmt_plus_0300', '3.5' => 'timezone_gmt_plus_0330', '4' => 'timezone_gmt_plus_0400', '4.5' => 'timezone_gmt_plus_0430', '5' => 'timezone_gmt_plus_0500', '5.5' => 'timezone_gmt_plus_0530', '5.75' => 'timezone_gmt_plus_0545', '6' => 'timezone_gmt_plus_0600', '6.5' => 'timezone_gmt_plus_0630', '7' => 'timezone_gmt_plus_0700', '8' => 'timezone_gmt_plus_0800', '9' => 'timezone_gmt_plus_0900', '9.5' => 'timezone_gmt_plus_0930', '10' => 'timezone_gmt_plus_1000', '11' => 'timezone_gmt_plus_1100', '12' => 'timezone_gmt_plus_1200');
    return ($offset == 'all' ? $timezones : $timezones['' . $offset]);
  }

  function show_timezone ($tzoffset = 0, $autodst = 0, $dst = 0)
  {
    global $lang, $date_format_options, $time_format_options;
    $timezoneoptions = '';
    foreach (fetch_timezone () as $optionvalue => $timezonephrase)
    {
      $timezoneoptions .= '<option value="' . $optionvalue . '"' . ($tzoffset == $optionvalue ? ' selected="selected"' : '') . '>' . $lang->timezone['' . $timezonephrase] . '</option>';
    }

    $selectdst = array ();
    if ($autodst)
    {
      $selectdst[2] = ' selected="selected"';
    }
    else
    {
      if ($dst)
      {
        $selectdst[1] = ' selected="selected"';
      }
      else
      {
        $selectdst[0] = ' selected="selected"';
      }
    }

    return '
	<fieldset class="fieldset">
		<legend><label for="sel_tzoffset">' . $lang->timezone['date_time_options'] . '</label></legend>
		<table cellpadding="0" cellspacing="0" border="0" width="100%">
	    
		<tr>
           <td><span class="smalltext"><b>'.$lang->timezone['date_format'].'</b></span></td>
        </tr>
        <tr>
        <td>
       <select class="form-select form-select-sm pe-5 w-auto" name="dateformat">
          <option value="0">'.$lang->timezone['use_default'].'</option>
       '.$date_format_options.'
       </select>
       </td>
       </tr>
       <tr>
       <td><span class="smalltext"><b>'.$lang->timezone['time_format'].'</b></span></td>
       </tr>
       <tr>
       <td>
        <select class="form-select form-select-sm pe-5 w-auto" name="timeformat">
        <option value="0">'.$lang->timezone['use_default'].'</option>
        '.$time_format_options.'
        </select>


		
		<tr>
			<td class="none">' . $lang->timezone['time_auto_corrected_to_location'] . '</td>
		</tr>
		<tr>
			<td class="none">
				<span style="float:right">
				<select class="form-select form-select-sm pe-5 w-auto" name="tzoffset" id="sel_tzoffset">
					' . $timezoneoptions . '
				</select>
				</span>
				<label for="sel_tzoffset"><b>' . $lang->timezone['time_zone'] . ':</b></label>
			</td>
		</tr>
		<tr>
			<td class="none">' . $lang->timezone['allow_daylight_savings_time'] . '</td>
		</tr>
		<tr>
			<td class="none">
				<span style="float:right">
				<select class="form-select form-select-sm pe-5 w-auto" name="dst" id="sel_dst">
					<option value="2"' . (isset ($selectdst[2]) ? $selectdst[2] : '') . '>' . $lang->timezone['dstauto'] . '</option>
					<option value="1"' . (isset ($selectdst[1]) ? $selectdst[1] : '') . '>' . $lang->timezone['dston'] . '</option>
					<option value="0"' . (isset ($selectdst[0]) ? $selectdst[0] : '') . '>' . $lang->timezone['dstoff'] . '</option>
				</select>
				</span>
				<label for="sel_dst"><b>' . $lang->timezone['dst_correction_option'] . ':</b></label>
			</td>
		</tr>
		</table>
	</fieldset>
	';
  }

  if (!defined ('IN_TRACKER'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  $lang->load ('timezone');
  $lang->load ('usercp');
?>
