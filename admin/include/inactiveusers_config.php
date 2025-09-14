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
if(!defined('IUM_VERSION'))
	die("<font face='verdana' size='2' color='darkred'><b>Error!</b> Direct initialization of this file is not allowed.</font>");

$show_per_page = 30; // Show max. X users per page.
$maxdays = 60; // Enter the amount of Days after which inactive Users should be informed about their upcoming removal.
$deleteafter = 15; // Enter the amount of Days that should pass after the Warning before the User is being deleted.
$deleteinativeusers = "yes"; // Shall I delete inactive users? YES or NO
$waitbeforesend = 5; // Leave this high for better performance..
$postmaillimit = 5; // Leave this low for better performance.

// Message Body
$body = array(
	'inactive'				=> '<p>Dear %s,</p>
								<p>It has come to our attention that you have registered at <b>'.$SITENAME.'</b> more then <b>'.$maxdays.' days ago</b>, but didn\'t login again since.</p>
								<p>Did you forget about us?</p>
								<p>We would be happy to see you around again!</p>
								<p>If you don\'t login again within <b>'.$deleteafter.' days</b> from now, we will <b><font color=red>delete</font></b> your account.</p>
								<p>&nbsp;</p>
								<p>Sincerely,</p>
								<p>'.$SITENAME.' Team</p>
								<p><a href="'.$BASEURL.'">'.$BASEURL.'</a></p>
								<p>&nbsp;</p>
								<p><b>DO NOT REPLY TO THIS EMAIL!</b></p>',

	'deleted'				=> '<p>Dear %s,</p>
								<p>You have not logged in at <b>'.$SITENAME.'</b> for more then <b>'.$maxdays.' days</b>.</p>
								<p>You also didn\'t respond to our eMail we sent to you <b>'.$deleteafter.' days ago</b>.</p>
								<p>Therefor we have decided to <b><font color=red>delete</font></b> your Account, as it seems you are not interested in our site any longer.</p>
								<p>We are sorry to see that you left us, feel free to come back at any time.</p>
								<p>&nbsp;</p>
								<p>Sincerely,</p>
								<p>'.$SITENAME.' Team</p>
								<p><a href="'.$BASEURL.'">'.$BASEURL.'</a></p>
								<p>&nbsp;</p>
								<p><b>DO NOT REPLY TO THIS EMAIL!</b></p>'
);

// Message Subject.
$subject = array(
	'inactive'				=> $SITENAME.' - Account Inactive!',
	'deleted'				=> $SITENAME.' - Account Deleted!'
);
?>
