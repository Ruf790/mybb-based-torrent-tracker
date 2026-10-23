<?php


if(!defined('IN_TRACKER'))
 die('Hacking attempt!');

// takerate.php, takereseed.php
$language['takewhatever'] = array 
(
	'invalidrating'				=>'Invalid Rating!',
	'snatchbeforerate'			=>'Snatch this torrent first!',
	'cantrateowntorrent'		=>'You can\'t rate your own torrents!',
	'alreadyrated'				=>'You have already rated this torrent!',
	'takereseedhead'			=>'Reseed Request!',
	'message'					=>'Message:',
	'takereseednouser'			=>'No users found for reseed request!',
	'nodupe'					=>'No duplicate reseed request allowed!',	
	'thankyou'					=>'Thank You!',
	'reseedsubject'				=>'🌱 Reseed request (TID: {1})',
	'cantthankowntorrent'		=>'You can\'t thank your own torrents!',
	'alreadythanked'				=>'You have already thanked this torrent!',
	'reseedmsg'=>'👋 Hello {1}!

🔥 This torrent needs seeders: {2}

📋 The snatch list shows that you have downloaded it.
⏳ People are stuck on it right now, so could you please seed it again and help them finish?
💚 Your help in seeding this torrent would be greatly appreciated.

✉️ Please reply to this message and let us know whether you are seeding, and if not, why.
🙏 Please seed, so we don\'t have to send a second request.

📊 Your stats on this torrent:
⬆️ Uploaded: {3}
⬇️ Downloaded: {4}

Thank you in advance!
🤖 Please note: this is an automatically generated request.',// added in v4.0


'reseed_too_soon'    => "A reseed request for this torrent was already sent recently. Try again in {1} h.",
'reseed_bad_request' => "Invalid request. Open the torrent page and press the button again.",
'reseed_has_seeders' => "This torrent already has seeders.",





);
?>
