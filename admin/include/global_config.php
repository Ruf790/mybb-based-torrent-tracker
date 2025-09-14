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

//Staff Tool Hit and Run settings
$config['ts_hit_and_run']['min_share_ratio'] = '1.0'; // Min. Share Ratio for each torrent.
$config['ts_hit_and_run']['query_limit'] = '20'; // Show max. X users per page.
$config['ts_hit_and_run']['skip_usergroups'] =   array(UC_BANNED, UC_VIP, UC_ADMINISTRATOR, UC_SYSOP, UC_MODERATOR); // Skip users in these groups.. 

//ts_tags.php settings (Search Cloud)
$__min = 10; // Min. font size.
$__max = 30; // Max. font size.
$sc_displaycharminimum = 2; // Display Min. Char. size.

//Staff Tool Uploaders config.
$config['uploaders']['query_limit'] = '30'; // Show max. X uploaders per page.

//ts_subtitles.php
$config['subtitles']['max_upload_size'] = 90000; // Max upload size of Subtitles.. Default 90kb.
$config['subtitles']['allowed_file_types'] = array('rar','zip'); // Allowed file types of Subtitles. Default rar and zip.

//ts_auto_torrent_submit.php (External Tracker List - 'name' => 'upload link')
$config['ts_auto_torrent_submit'] =
	array
	(
		'Mininova'			=>		'http://www.mininova.org/upload',
		'Demonoid'			=>		'http://www.demonoid.com/torrent_upload.php5',
		'Thepiratebay'		=>		'http://www.thepiratebay.org/upload',
		'Meganova'			=>		'http://www.meganova.org/upload.html',
		'Torrentvalley'		=>		'http://www.torrentvalley.com/upload.php',
		'Torrentspy'			=>		'http://www.torrentspy.com/uploadtorrent.asp',
	);

//How many torrents that you want to fix per page. Lower this for better performance.. (default 10)
$config['fixhash_perpage'] = 10;

//Who can reset pincodes? Enter username below! (Note: User must have permission to view Setting panel!)
$config['reset_pincode'] = 'xam';
?>
