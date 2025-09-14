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
Translation by xam Version: 0.3

*/

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// Transfer.php
$language['transfer'] = array 
(
	'head'		=> 'Transfer Data',
	'field1'	=>	 'Username: ',
	'field2'	=>	 'Transfer Amount:',
	'button'	=>	 'Transfer',
	'head2'	=>	 'Status/Info',
	'info'		=> '1) Enter username<br />2) Enter transfer amount<br /> 3) Click to \'transfer\' button.<br /><br />Note: Max. {1} allowed.',
	'info2'		=> 'The data transfer has been completed successfuly.<br />User {1} has received {2} Upload amount from you.<br />Thank you.',
	'noway'	=>	 'You have no transfer amount left.<br />Upload a torrent or continue seed to get ul amount.',
	'noway2'	 =>'You can not send transfer data to yourself!',
	'msgsubject' => 'Transfer Amount!',
	'msgbody'	=>'Hello {1},

	Your friend {2} has transfered {3} upload amount to your account.

	Have a nice day.
	',
	'noway3'=>'You can only transfer once a day, Please try again tomorrow!', //Added v4.1
	'noway4'=>'This user has already got transfer amount today from someone else.. Please try again tomorrow!', //Added v4.1
	'goback'=>'Click here to go back', //Added v4.3
	'head3'=>'Calculate', //Added v4.3
	'result'=>'Result: ', //Added v4.3
	'amount'=>'Amount: ', //Added v4.3
);
?>
