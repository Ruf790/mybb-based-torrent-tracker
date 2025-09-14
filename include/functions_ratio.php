<?php
/*
************************************************
*==========[TS Special Edition v.5.6]==========*
************************************************
*              Special Thanks To               *
*        DrNet - wWw.SpecialCoders.CoM         *
*          Vinson - wWw.Decode4u.CoM           *
*    MrDecoder - wWw.Fearless-Releases.CoM     *
*           Fynnon - wWw.BvList.CoM            *
*==============================================*
*   Note: Don't Modify Or Delete This Credit   *
*     Next Target: TS Special Edition v5.7     *
*     TS SE WILL BE ALWAYS FREE SOFTWARE !     *
************************************************
*/
if(!defined('IN_TRACKER'))
	die("<font face='verdana' size='2' color='darkred'><b>Error!</b> Direct initialization of this file is not allowed.</font>");
# Function get_user_ratio v.0.2
function get_user_ratio($uploaded,$downloaded,$white=false) 
{
	if ($downloaded > 0)
	{
		$ratio = $uploaded / $downloaded;
		$ratio = number_format($ratio, 2);
		$color = get_ratio_color($ratio);
		if ($color && $ratio < 1)
			$ratio = '<font color=\''.$color.'\'>'.$ratio.'</font>';
		else
			$ratio = '<font color=\''.($white ? '#ffffff' :'#000000').'\'>'.$ratio.'</font>';
	}
	elseif ($uploaded > 0)
		$ratio = '<font color=\'#9f040b\'>Inf.</font>';
	else
		$ratio = '<font color=\'#9f040b\'>--</font>';
	return $ratio;
}
# Function get_ratio_color v.0.2
function get_ratio_color($ratio)
{
	if ($ratio < 0.1) return "#ff0000";
	if ($ratio < 0.2) return "#ee0000";
	if ($ratio < 0.3) return "#dd0000";
	if ($ratio < 0.4) return "#cc0000";
	if ($ratio < 0.5) return "#bb0000";
	if ($ratio < 0.6) return "#aa0000";
	if ($ratio < 0.7) return "#990000";
	if ($ratio < 0.8) return "#880000";
	if ($ratio < 0.9) return "#770000";
	if ($ratio < 1) return "#660000";
	return "green";
}
?>
