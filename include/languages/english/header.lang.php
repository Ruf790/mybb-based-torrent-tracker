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
Translation by xam Version: 0.2

*/

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// header.php
$language['header'] = array 
(	
	

	
	
'welcome_newposts' => "View New Posts",
'welcome_todaysposts' => "View Today's Posts",
'welcome_pms' => "Private Messages",
'welcome_pms_usage' => "(Unread {1}, Total {2})",

'welcome_usercp' => "User CP",
'welcome_modcp' => "Mod CP",
'welcome_admin' => "Admin CP",
'welcome_logout' => "Log Out",
	
	
	'dismiss_notice' => "Dismiss this notice",
	
	'newpm_notice_one222' => '<strong>You have one unread private message</strong> from {1} titled <a href=\"{2}/private.php?action=read&amp;pmid={3}\" style=\"font-weight: bold;\">{4}</a>',
    'newpm_notice_multiple222' => '<strong>You have {1} unread private messages.</strong> The most recent is from {2} titled <a href=\"{3}/messages.php?action=read&amp;pmid={4}\" style=\"font-weight: bold;\">{5}</a>',
	
	
	'newpm_notice_one' => '<strong>You have one unread private message</strong> from {1} titled <a href="{2}/messages.php?userid={5}&do=showpm&pmid={3}" style=\"font-weight: bold;\">{4}</a>',
  
	'newpm_notice_multiple' => '<strong>You have {1} unread private messages.</strong> The most recent is from {2} titled 
	 <a href="{3}/messages.php?userid={6}&do=showpm&pmid={4}" style=\"font-weight: bold;\">{5}</a>',

	
	'anntitle'				=>'ANNOUCEMENT TITLE:',	
	'anncreated'		=>'CREATED ON:',
	'annby'				=>'BY:',
	'annmsg'				=>'MESSAGE:',
	'annclose'			=>'Click {1} here</a> clear this announcement.',
	'login'					=>'Login',
	'register'				=>'Register',
	'recoverpassword'	=>'Recover Password:',
	'viaemail'				=>'via Email',
	'viaquestion'		=>'via Question',
	'donate'				=>'click here to donate us',
	'thanks'				=>'thanks',
	'warned'				=>'You are now warned for having a low ratio. You need to get a {1} ratio for your warning be removed.<br />If you don\'t get it in {2}, your account will be banned.',
	
	
	
	'newmessage'		=>'You have {1} new private message(s), click here to read.', // Updated in v5.4
	'trackeroffline'		=>'<b>WARNING</b>: The tracker is currently offline! Click <a href="{1}/admin/settings.php?action=mainsettings">here</a> to change settings.',
	'newann'				=>'There is a new announcement since your last visit. Click here to read the Latest Annoucement.',
	'newreport'			=>'There is {1} new report(s)!',
	'staffmess'			=>'There is {1} new staff message(s)!',
	'warndonor'			=>'Your access to the paid subscription "VIP" is about to expire. To renew this subscription you must visit <a href="{1}/donate.php">donate page.</a> If you do not extend your subscription, your "VIP" access will be removed! ({2} left)',
	'extramembers'		=>'MemberList',
	'extrafriends'		=>'FriendList',
	'extrarssfeed'		=>'RSS Feeds'
);
?>
