<?php


//Staff Tool Hit and Run settings → moved to site settings: Admin → Settings → Cleanup → Hit & Run
// (hr_min_ratio, hr_per_page, hr_skip_groups — the last one is shared with the H&R cron weekly_cleanups.php)

//ts_tags.php settings (Search Cloud)
$__min = 10; // Min. font size.
$__max = 30; // Max. font size.
$sc_displaycharminimum = 2; // Display Min. Char. size.

//Staff Tool Uploaders config.
$config['uploaders']['query_limit'] = '30'; // Show max. X uploaders per page.


//How many torrents that you want to fix per page. Lower this for better performance.. (default 10)
$config['fixhash_perpage'] = 10;

//Who can reset pincodes? Enter username below! (Note: User must have permission to view Setting panel!)
$config['reset_pincode'] = 'aaa';
?>
