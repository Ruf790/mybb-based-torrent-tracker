<?php


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


//How many torrents that you want to fix per page. Lower this for better performance.. (default 10)
$config['fixhash_perpage'] = 10;

//Who can reset pincodes? Enter username below! (Note: User must have permission to view Setting panel!)
$config['reset_pincode'] = 'xam';
?>
