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
/* 
TS Special Edition English Language File
Translation by xam Version: 0.1

*/

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// port_check.php
$language['port_check'] = array 
(
	'head'		=>	'Port Checker (Connectable Checker)',
	'title'			=>	'A test will be performed on your computer to check if the specified port is opened.',
	'checking'	=>	'Checking port ...',
	'good'		=>	'<font color="green">OK!</font> Port <b>{1}</b> is open and accepting connections. You will be able to receive incoming BitTorrent connections. Click <a href="'.$_SERVER['SCRIPT_NAME'].'">here</a> to check another port.',
	'bad'			=>	'<font color="red">ERROR!</font> Port <b>{1}</b> does not appear to be open. Please see www.portforward.com for more information about how to map a port. Click <a href="'.$_SERVER['SCRIPT_NAME'].'">here</a> to check another port.',
	'field1'		=>	'Enter Port Number:',
	'field2'		=>	'Check Port',
);
?>
