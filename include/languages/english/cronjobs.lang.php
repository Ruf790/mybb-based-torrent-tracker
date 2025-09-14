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
Translation by xam Version: 0.4
*/

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// cronjobs.php New since v3.6
$language['cronjobs'] = array 
(
	'r_subject'	=> 'Gift from Referral System!',
	'r_message' => 'Hi,

	Thank you for using our Referral System.

	You have been earned {1} credit(s).

	Kind regards.',// Updated in v5.4
	'invite_subject'	 => 'Automatic Invite!',
	'invite_message'	=> 'Congratulations, you have received {1} invite(s).

	If you would like to invite your friends, please click [url=invite.php?id={2}]here[/url].',// Updated in v5.4
	'donor_subject'	=> 'Donor status removed by system.',
	'donor_message'	=>	 'Hi,
	
	Your Donor status has timed out and has been auto-removed by the system, and your VIP status has been removed. 
	
	We would like to thank you once again for your support. 
	
	If you wish to re-new your donation, you can do so by clicking [url=donate.php]here[/url]. 
	
	Kind Regards.',// Updated in v5.4
	'vip_subject'	=>'VIP status removed by system.',
	'vip_message'	=>'Hi,
	
	Your VIP status has timed out and has been auto-removed by the system, and your VIP status has been removed. 
	
	Become a VIP again by donating us or exchanging some Karma Bonus Points.
	
	Kind Regards.',// Updated in v5.4
	'promote_subject'	 =>'Account Promote!',
	'promote_message'	=>'Congratulations, you have been auto-promoted to [b]Power User[/b]. :)',
	'demote_subject'	=>'Account Demote!',
	'demote_message'	=>'You have been auto-demoted from [b]Power User[/b] to [b]User[/b] because your share ratio has dropped below {1}',
	'lwarning_subject'	 =>'You have been Leech-Warned!',
	'lwarning_message'	=>'You have been warned because of having low ratio. You need to get a ratio {1} before next {2} weeks or your account will be banned.',
	'hr_warn_subject'=>'Hit and Run Warning!',//Added in v5.5
	'hr_warn_message'=>'[b]{1}[/b],

You have been warned for Hit & Run on the following torrent:
[b]{2}[/b]

You have seeded this torrent [b]{3}[/b] hour(s) but it must be seeded [b]{4}[/b] hour(s).

Please Re-Start to seed this torrent or you will be warned again soon.
If you don\'t have this torrent on your computer, please click on the following link to download & seed it.
[b]{5}[/b]

All torrents must be seeded at least [b]{6}[/b] hour(s) after finished otherwise users will get [b]+1[/b] warn count per torrent.
Please note: Once your total warnings will be reached the global limit (default 7), your account will be suspended.

Thank you for your understanding and support.
Have a great day.'//Added in v5.5
);
?>
